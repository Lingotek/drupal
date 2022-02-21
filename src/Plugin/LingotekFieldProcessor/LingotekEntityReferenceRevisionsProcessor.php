<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationEntityRevisionResolver;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "entity_reference_revisions",
 *   weight = 5,
 * )
 */
class LingotekEntityReferenceRevisionsProcessor extends PluginBase implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekConfigTranslationServiceInterface $lingotek_config_translation, LingotekContentTranslationServiceInterface $lingotek_content_translation, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekConfigTranslation = $lingotek_config_translation;
    $this->lingotekContentTranslation = $lingotek_content_translation;
    $this->moduleHandler = $module_handler;
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
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return 'entity_reference_revisions' === $field_definition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = [], string $revision_mode = LingotekContentTranslationEntityRevisionResolver::RESOLVE_LATEST_TRANSLATION_AFFECTED) {
    $target_entity_type_id = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    foreach ($entity->get($field_name) as $delta => $field_item) {
      $embedded_entity_id = $field_item->get('target_id')->getValue();
      $embedded_entity_revision_id = $field_item->get('target_revision_id')->getValue();
      $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)->loadRevision($embedded_entity_revision_id);
      // Handle the unlikely case where a paragraph has lost its parent.
      if (!empty($embedded_entity)) {
        $embedded_data = $this->lingotekContentTranslation->getSourceData($embedded_entity, $visited, $revision_mode);
        $data[$field_name][$delta] = $embedded_data;
      }
      else {
        // If the referenced entity doesn't exist, remove the target_id
        // that may be already set.
        unset($data[$field_name]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    $paragraphTranslatable = $field_definition->isTranslatable();
    $target_entity_type_id = $field_definition->getFieldStorageDefinition()
      ->getSetting('target_type');
    if ($paragraphTranslatable) {
      $translation->{$field_name} = NULL;
    }
    $delta = 0;
    $fieldValues = [];
    foreach ($field_data as $index => $field_item) {
      $embedded_entity_id = $revision->get($field_name)->get($index)
        ->get('target_id')
        ->getValue();
      /** @var \Drupal\Core\Entity\RevisionableInterface $embedded_entity */
      $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)
        ->load($embedded_entity_id);
      if ($embedded_entity !== NULL) {
        // If there is asymmetrical paragraphs enabled, we need a new one duplicated and stored.
        if ($paragraphTranslatable && $this->moduleHandler->moduleExists('paragraphs_asymmetric_translation_widgets')) {
          /** @var \Drupal\paragraphs\ParagraphInterface $duplicate */
          $duplicate = $embedded_entity->createDuplicate();
          if ($duplicate->isTranslatable()) {
            // If there is already a translation for the language we
            // want to set as default, we have to remove it. This should
            // never happen, but there may different previous approaches
            // to translating paragraphs, so we need to make sure the
            // download does not break because of this.
            if ($duplicate->hasTranslation($langcode)) {
              $duplicate->removeTranslation($langcode);
              $duplicate->save();
            }
            $duplicate->set('langcode', $langcode);
            foreach ($duplicate->getTranslationLanguages(FALSE) as $translationLanguage) {
              try {
                $duplicate->removeTranslation($translationLanguage->getId());
              }
              catch (\InvalidArgumentException $e) {
                // Should never happen.
              }
            }
          }
          $embedded_entity = $duplicate;
        }
        $this->lingotekContentTranslation->saveTargetData($embedded_entity, $langcode, $field_item);
        // Now the embedded entity is saved, but we need to ensure
        // the reference will be saved too. Ensure it's the same revision.
        $fieldValues[$delta] = ['target_id' => $embedded_entity->id(), 'target_revision_id' => $embedded_entity->getRevisionId()];
        $delta++;
      }
    }
    // If the paragraph was not translatable, we avoid at all costs to modify the field,
    // as this will override the source and may have unintended consequences.
    if ($paragraphTranslatable) {
      $translation->set($field_name, $fieldValues);
    }
  }

}
