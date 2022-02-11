<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "entity_reference",
 *   weight = 5,
 * )
 */
class LingotekEntityReferenceProcessor extends PluginBase implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekConfigTranslationServiceInterface $lingotek_config_translation, LingotekContentTranslationServiceInterface $lingotek_content_translation) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekConfigTranslation = $lingotek_config_translation;
    $this->lingotekContentTranslation = $lingotek_content_translation;
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
      $container->get('lingotek.content_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return in_array($field_definition->getType(), ['entity_reference', 'er_viewmode', 'bricks']);
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = [], $use_last_revision = TRUE) {
    $target_entity_type_id = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    foreach ($entity->get($field_name) as $delta => $field_item) {
      $embedded_entity_id = $field_item->get('target_id')->getValue();
      $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)->load($embedded_entity_id);
      // We may have orphan references, so ensure that they exist before
      // continuing.
      if ($embedded_entity !== NULL) {
        if ($embedded_entity instanceof ContentEntityInterface) {
          // We need to avoid cycles if we have several entity references
          // referencing each other.
          if (!isset($visited[$embedded_entity->bundle()]) || !in_array($embedded_entity->id(), $visited[$embedded_entity->bundle()])) {
            $embedded_data = $this->lingotekContentTranslation->getSourceData($embedded_entity, $visited, $use_last_revision);
            $data[$field_name][$delta] = $embedded_data;
          }
          else {
            // We don't want to embed the data, but still will need the
            // references, so let's include the metadata.
            $metadata = [];
            $this->includeMetadata($embedded_entity, $metadata);
            $data[$field_name][$delta] = $metadata;
          }
        }
        elseif ($embedded_entity instanceof ConfigEntityInterface) {
          $embedded_data = $this->lingotekConfigTranslation->getSourceData($embedded_entity);
          $data[$field_name][$delta] = $embedded_data;
        }
      }
      else {
        // If the referenced entity doesn't exist, remove the target_id
        // that may be already set.
        unset($data[$field_name]);
      }
    }
  }

  protected function includeMetadata(ContentEntityInterface &$entity, &$data) {
    $data['_lingotek_metadata']['_entity_type_id'] = $entity->getEntityTypeId();
    $data['_lingotek_metadata']['_entity_id'] = $entity->id();
    $data['_lingotek_metadata']['_entity_revision'] = $entity->getRevisionId();
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    $target_entity_type_id = $field_definition->getFieldStorageDefinition()
      ->getSetting('target_type');
    $translation->{$field_name} = NULL;
    $delta = 0;
    foreach ($field_data as $index => $field_item) {
      if (isset($field_item['_lingotek_metadata'])) {
        $target_entity_type_id = $field_item['_lingotek_metadata']['_entity_type_id'];
        $embedded_entity_id = $field_item['_lingotek_metadata']['_entity_id'];
        $embedded_entity_revision_id = $field_item['_lingotek_metadata']['_entity_revision'];
      }
      else {
        // Try to get it from the revision itself. It may have been
        // modified, so this can be a source of errors, but we need this
        // because we didn't have metadata before.
        $embedded_entity_id = $revision->{$field_name}->get($index)
          ->get('target_id')
          ->getValue();
      }
      $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)
        ->load($embedded_entity_id);
      // We may have orphan references, so ensure that they exist before
      // continuing.
      if ($embedded_entity !== NULL) {
        // ToDo: It can be a content entity, or a config entity.
        if ($embedded_entity instanceof ContentEntityInterface) {
          if ($this->lingotekConfiguration->isEnabled($embedded_entity->getEntityTypeId(), $embedded_entity->bundle())) {
            $this->lingotekContentTranslation->saveTargetData($embedded_entity, $langcode, $field_item);
          }
          else {
            \Drupal::logger('lingotek')->warning('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $field_name]);
          }
        }
        elseif ($embedded_entity instanceof ConfigEntityInterface) {
          $this->lingotekConfigTranslation->saveTargetData($embedded_entity, $langcode, $field_item);
        }
        // Now the embedded entity is saved, but we need to ensure
        // the reference will be saved too.
        $translation->get($field_name)->set($delta, $embedded_entity_id);
        $delta++;
      }
    }
  }

}
