<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
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
 *   id = "block_field",
 *   weight = 5,
 * )
 */
class LingotekBlockFieldProcessor extends PluginBase implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

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
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The typed config handler.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

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
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $repository
   *   The entity repository.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $lingotek_config_translation
   *   The Lingotek config translation service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $lingotek_content_translation
   *   The Lingotek content translation service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $repository, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekConfigTranslationServiceInterface $lingotek_config_translation, LingotekContentTranslationServiceInterface $lingotek_content_translation, TypedConfigManagerInterface $typed_config, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $repository;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekConfigTranslation = $lingotek_config_translation;
    $this->lingotekContentTranslation = $lingotek_content_translation;
    $this->typedConfig = $typed_config;
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
      $container->get('entity.repository'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.config_translation'),
      $container->get('lingotek.content_translation'),
      $container->get('config.typed'),
      $container->get('logger.factory')->get('lingotek')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return 'block_field' === $field_definition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = [], string $revision_mode = LingotekContentTranslationEntityRevisionResolver::RESOLVE_LATEST_TRANSLATION_AFFECTED) {
    foreach ($entity->get($field_name) as $delta => $field_item) {
      $pluginId = $field_item->get('plugin_id')->getValue();
      $block_instance = $field_item->getBlock();
      $pluginIDName = $block_instance->getPluginDefinition()['id'];
      $blockConfig = $block_instance->getConfiguration();
      $definition = $this->typedConfig->getDefinition('block.settings.' . $pluginIDName);
      if ($definition['type'] == 'undefined') {
        $definition = $this->typedConfig->getDefinition('block_settings');
      }
      $dataDefinition = $this->typedConfig->buildDataDefinition($definition, $blockConfig);
      $schema = $this->typedConfig->create($dataDefinition, $blockConfig);
      $properties = $this->lingotekConfigTranslation->getTranslatableProperties($schema, NULL);
      $embedded_data = [];
      foreach ($properties as $property) {
        $propertyParts = explode('.', $property);
        $embedded_data[$property] = NestedArray::getValue($blockConfig, $propertyParts);
      }
      if (strpos($pluginId, 'block_content') === 0) {
        $uuid = $block_instance->getDerivativeId();
        if ($block = $this->entityRepository->loadEntityByUuid('block_content', $uuid)) {
          $embedded_data['entity'] = $this->lingotekContentTranslation->getSourceData($block, $visited, $revision_mode);
        }
      }
      $data[$field_name][$delta] = $embedded_data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    $translation->set($field_name, NULL);
    foreach ($field_data as $delta => $field_item) {
      /** @var \Drupal\Core\Block\BlockPluginInterface $block */
      $block = $revision->get($field_name)->get($delta)->getBlock();
      if ($block !== NULL) {
        $entityData = NULL;
        if (isset($field_item['entity'])) {
          $entityData = $field_item['entity'];
          unset($field_item['entity']);
        }
        $configuration = $block->getConfiguration();
        $newConfiguration = $configuration;
        foreach ($field_item as $fieldItemProperty => $fieldItemPropertyData) {
          $componentDataKeyParts = explode('.', $fieldItemProperty);
          NestedArray::setValue($newConfiguration, $componentDataKeyParts, $fieldItemPropertyData);
        }
        $translation->get($field_name)->set($delta, [
          'plugin_id' => $block->getPluginId(),
          'settings' => $newConfiguration,
        ]);
        if ($entityData !== NULL) {
          $embedded_entity_id = NULL;
          if (isset($entityData['_lingotek_metadata'])) {
            $target_entity_type_id = $entityData['_lingotek_metadata']['_entity_type_id'];
            $embedded_entity_id = $entityData['_lingotek_metadata']['_entity_id'];
            $embedded_entity_revision_id = $entityData['_lingotek_metadata']['_entity_revision'];
            $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)
              ->load($embedded_entity_id);
            // We may have orphan references, so ensure that they exist before
            // continuing.
            if ($embedded_entity !== NULL) {
              if ($embedded_entity instanceof ContentEntityInterface) {
                if ($this->lingotekConfiguration->isEnabled($embedded_entity->getEntityTypeId(), $embedded_entity->bundle())) {
                  $this->lingotekContentTranslation->saveTargetData($embedded_entity, $langcode, $entityData);
                }
                else {
                  $this->logger
                    ->warning('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $field_name]);
                }
              }
            }
          }
        }
      }
    }
  }

}
