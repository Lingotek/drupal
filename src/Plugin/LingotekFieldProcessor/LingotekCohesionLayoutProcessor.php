<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\cohesion\LayoutCanvas\ElementModel;
use Drupal\cohesion\LayoutCanvas\LayoutCanvas;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationEntityRevisionResolver;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "cohesion_layout",
 *   weight = 5,
 * )
 */
class LingotekCohesionLayoutProcessor extends PluginBase implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * The Lingotek configuration translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface
   */
  protected $lingotekConfigTranslation;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $lingotekContentTranslation;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new UploadToLingotekAction action.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $lingotek_config_translation
   *   The Lingotek config translation service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $lingotek_content_translation
   *   The Lingotek content translation service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key-value store factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekConfigTranslationServiceInterface $lingotek_config_translation, LingotekContentTranslationServiceInterface $lingotek_content_translation, KeyValueFactoryInterface $key_value_factory, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekConfigTranslation = $lingotek_config_translation;
    $this->lingotekContentTranslation = $lingotek_content_translation;
    $this->keyValueStore = $key_value_factory->get('cohesion.assets.form_elements');
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.config_translation'),
      $container->get('lingotek.content_translation'),
      $container->get('keyvalue'),
      $container->get('logger.factory')->get('lingotek')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return $field_definition->getType() === 'string_long' && $field_definition->getName() === 'json_values' && $entity->get($field_definition->getName())->getEntity()->getEntityTypeId() === 'cohesion_layout';
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = [], string $revision_mode = LingotekContentTranslationEntityRevisionResolver::RESOLVE_LATEST_TRANSLATION_AFFECTED) {
    $value = $entity->get($field_name)->value;
    $layout_canvas = new LayoutCanvas($value);
    foreach ($layout_canvas->iterateCanvas() as $element) {
      $data_layout = [];
      if ($element->isComponent() && $component = $this->entityTypeManager->getStorage('cohesion_component')->load($element->getComponentID())) {
        // Get the models of each form field of the component as an array keyed by their uuid
        $component_model = $component->getLayoutCanvasInstance()
          ->iterateModels('component_form');
        if ($elementModel = $element->getModel()) {
          $data_layout = array_merge(
            $data_layout,
            $this->extractCohesionComponentValues($component_model, $elementModel->getValues())
          );
        }
      }

      if (!empty($data_layout)) {
        $data[$field_name][$element->getModelUUID()] = $data_layout;
      }
    }
    unset($data[$field_name][0]);
  }

  protected function extractCohesionComponentValues(array $component_model, $values) {
    $field_values = [];

    foreach ($values as $key => $value) {
      // If the key does not match a UUID, then it's not a component field and we can skip it.
      if (!preg_match(ElementModel::MATCH_UUID, $key)) {
        continue;
      }

      $component = $component_model[$key] ?? NULL;
      // If we can't find a component with this uuid, we skip it.
      if (!$component) {
        continue;
      }

      $settings = $component->getProperty('settings');
      // Skip this field if the component is not translatable.
      if (($settings->translate ?? NULL) === FALSE) {
        continue;
      }

      $skippedComponentTypes = [
        'cohTypeahead',
        'cohEntityBrowser',
        'cohFileBrowser',
      ];
      $component_type = $settings->type ?? NULL;
      if (in_array($component_type, $skippedComponentTypes)) {
        continue;
      }

      // Handle Field Repeaters before checking if the field is translatable,
      // since Field Repeater fields aren't but their contents are.
      if ($component_type === 'cohArray') {
        foreach ($value as $index => $item) {
          $field_values[$key][$index] = $this->extractCohesionComponentValues($component_model, (array) $item);
        }
      }

      $form_field = $this->keyValueStore->get($component->getElement()->getProperty('uid'));
      if (($form_field['translate'] ?? NULL) !== TRUE) {
        // Skip if the form_field is not translatable.
        continue;
      }

      $schema_type = $settings->schema->type ?? NULL;
      switch ($schema_type) {
        case 'string':
          if (!empty($value)) {
            $field_values[$key] = $value;
          }

          break;

        case 'object':
          switch ($component_type) {
            case 'cohWysiwyg':
              if (!empty($value->text)) {
                $field_values[$key] = $value->text;
              }

              break;

            default:
              $this->logger
                ->warning('Unhandled component type of \'%type\' (schema type: %schema) encountered when extracting cohesion component values.', [
                  '%type' => $component_type,
                  '%schema' => $schema_type,
                ]);
              break;
          }

          break;

        default:
          $this->logger->warning(
            'Unhandled schema type of \'%type\' encountered when extracting cohesion component values.',
            ['%type' => $schema_type]
          );
          break;
      }
    }

    return $field_values;
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    $existingData = $revision->get($field_name)->get(0)->value;
    $layout_canvas = new LayoutCanvas($existingData);
    foreach ($layout_canvas->iterateCanvas() as $element) {
      if (!$element->isComponent() || !$component = $this->entityTypeManager->getStorage('cohesion_component')->load($element->getComponentID())) {
        continue;
      }

      if (!$model = $element->getModel()) {
        continue;
      }

      $component_model = $component->getLayoutCanvasInstance()
        ->iterateModels('component_form');

      $component_data = $field_data[$element->getUUID()] ?? NULL;
      if (!$component_data) {
        continue;
      }

      $this->setCohesionComponentValues($component_model, $model, $component_data);
    }
    $translation->get($field_name)->get(0)->set('value', json_encode($layout_canvas));
  }

  protected function setCohesionComponentValues(array $component_model, $model, $translations, $path = []) {
    foreach ($translations as $key => $translation) {
      // If the key does not match a UUID, then it's not a component field and we can skip it.
      if (!preg_match(ElementModel::MATCH_UUID, $key)) {
        continue;
      }

      $component = $component_model[$key] ?? NULL;
      // If we can't find a component with this uuid, we skip it.
      if (!$component) {
        continue;
      }

      // Keep track of the path to the property so we can handle nested components.
      $property_path = array_merge($path, [$key]);

      $settings = $component->getProperty('settings');
      $component_type = $settings->type ?? NULL;
      $schema_type = $settings->schema->type ?? NULL;
      switch ($schema_type) {
        case 'string':
          $model->setProperty($property_path, $translation);
          break;

        case 'array':
          foreach ($translation as $index => $item) {
            $newPath = array_merge($property_path, [$index]);
            $this->setCohesionComponentValues($component_model, $model, $item, $newPath);
          }
          break;

        case 'object':
          switch ($component_type) {
            case 'cohWysiwyg':
              $newPath = array_merge($property_path, ['text']);
              $model->setProperty($newPath, $translation);
              break;

            default:
              $this->logger
                ->warning('Unhandled component type of \'%type\' (schema type: %schema) encountered when setting cohesion component values.', [
                  '%type' => $component_type,
                  '%schema' => $schema_type,
                ]);
              break;
          }

          break;

        default:
          $this->logger->warning(
            'Unhandled schema type of \'%type\' encountered when setting cohesion component values.',
            ['%type' => $schema_type]
          );
          break;
      }
    }
  }

}
