<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "layout_builder_st",
 *   weight = 5,
 * )
 */
class LingotekLayoutBuilderSTProcessor extends PluginBase implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

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
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The typed config handler.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $lingotek_config_translation
   *   The Lingotek config translation service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $lingotek_content_translation
   *   The Lingotek content translation service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $repository, BlockManagerInterface $block_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekConfigTranslationServiceInterface $lingotek_config_translation, LingotekContentTranslationServiceInterface $lingotek_content_translation, TypedConfigManagerInterface $typed_config, ModuleHandlerInterface $module_handler, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $repository;
    $this->blockManager = $block_manager;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekConfigTranslation = $lingotek_config_translation;
    $this->lingotekContentTranslation = $lingotek_content_translation;
    $this->typedConfig = $typed_config;
    $this->moduleHandler = $module_handler;
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
      $container->get('plugin.manager.block'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.config_translation'),
      $container->get('lingotek.content_translation'),
      $container->get('config.typed'),
      $container->get('module_handler'),
      $container->get('logger.factory')->get('lingotek')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return 'layout_translation' === $field_definition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = []) {
    // TODO: This could be in applies.
    // We need to get the original data from the layout.
    $layoutBuilderST = $this->moduleHandler->moduleExists('layout_builder_st');
    if ($layoutBuilderST) {
      $data[$field_name] = ['components' => []];
      $layoutField = $entity->get(OverridesSectionStorage::FIELD_NAME);
      $layout = $layoutField->getValue();
      /** @var \Drupal\layout_builder\Section $sectionObject */
      foreach ($layout as $sectionIndex => $section) {
        $sectionObject = $section['section'];
        $components = $sectionObject->getComponents();
        /** @var \Drupal\layout_builder\SectionComponent $component */
        foreach ($components as $componentUuid => $component) {
          /** @var \Drupal\Core\Block\BlockPluginInterface $block_instance */
          // TODO: Change this to getConfiguration() when is safe to do so.
          // See https://www.drupal.org/project/drupal/issues/3180555.
          $block_instance = $this->blockManager->createInstance($component->getPluginId(), $component->get('configuration'));
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
            // The data definition will return nested keys as dot separated.
            $propertyParts = explode('.', $property);
            $embedded_data[$property] = NestedArray::getValue($blockConfig, $propertyParts);
          }
          $data[$field_name]['components'][$componentUuid] = $embedded_data;

          if (strpos($pluginIDName, 'inline_block') === 0) {
            $blockRevisionId = $blockConfig['block_revision_id'];
            if ($block = $this->entityTypeManager->getStorage('block_content')->loadRevision($blockRevisionId)) {
              $data[$field_name]['entities']['block_content'][$blockRevisionId] = $this->lingotekContentTranslation->getSourceData($block, $visited);
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    $components = [];

    // We need the original layout, as the translation must store the
    // non-translatable properties too. So we need to copy them to the
    // translated field.
    $layoutField = $translation->getUntranslated()->get(OverridesSectionStorage::FIELD_NAME);
    $layout = $layoutField->getValue();

    foreach ($field_data['components'] as $componentUuid => $componentData) {
      /** @var \Drupal\layout_builder\SectionComponent $originalComponent */
      $originalComponent = NULL;
      /** @var \Drupal\layout_builder\Section $section */
      foreach ($layout as $sectionInfo) {
        $sectionComponents = $sectionInfo['section']->getComponents();
        if (isset($sectionComponents[$componentUuid])) {
          $originalComponent = $sectionComponents[$componentUuid];
          break;
        }
      }
      $block_instance = $this->blockManager->createInstance($originalComponent->getPluginId(), $originalComponent->get('configuration'));
      $blockConfig = $block_instance->getConfiguration();

      $components[$componentUuid] = [];
      foreach ($componentData as $componentDataKey => $componentDataValue) {
        $componentDataKeyParts = explode('.', $componentDataKey);
        if (count($componentDataKeyParts) > 1) {
          // The translation must store the non-translatable properties
          // too. So we copy them from the original field. The key to be
          // copied is the complete key but the last piece.
          $originalDataKeyParts = array_slice($componentDataKeyParts, 0, -1);
          NestedArray::setValue($components[$componentUuid], $originalDataKeyParts, NestedArray::getValue($blockConfig, $originalDataKeyParts));
        }
        NestedArray::setValue($components[$componentUuid], $componentDataKeyParts, $componentDataValue);
      }
    }
    // If we are embedding content blocks, we need to translate those too.
    if (isset($field_data['entities']['block_content'])) {
      foreach ($field_data['entities']['block_content'] as $embedded_entity_revision_id => $blockContentData) {
        $target_entity_type_id = 'block_content';
        $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)
          ->loadRevision($embedded_entity_revision_id);
        // We may have orphan references, so ensure that they exist before
        // continuing.
        if ($embedded_entity !== NULL) {
          if ($embedded_entity instanceof ContentEntityInterface) {
            if ($this->lingotekConfiguration->isEnabled($embedded_entity->getEntityTypeId(), $embedded_entity->bundle())) {
              $this->lingotekContentTranslation->saveTargetData($embedded_entity, $langcode, $blockContentData);
            }
            else {
              $this->logger->warning('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $field_name]);
            }
          }
        }
      }
    }
    $translation->get($field_name)->value = ['components' => $components];
  }

}
