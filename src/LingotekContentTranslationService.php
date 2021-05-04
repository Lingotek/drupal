<?php

namespace Drupal\lingotek;

use Drupal\cohesion\LayoutCanvas\ElementModel;
use Drupal\cohesion\LayoutCanvas\LayoutCanvas;
use Drupal\cohesion_elements\Entity\Component;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Service for managing Lingotek content translations.
 */
class LingotekContentTranslationService implements LingotekContentTranslationServiceInterface {

  use StringTranslationTrait;

  /**
   * The Lingotek interface
   *
   * @var \Drupal\lingotek\LingotekInterface
   */
  protected $lingotek;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

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
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new LingotekContentTranslationService object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   An lingotek object.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $lingotek_config_translation
   *   The Lingotek config translation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   An entity manager object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection object.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekConfigTranslationServiceInterface $lingotek_config_translation, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, EntityFieldManagerInterface $entity_field_manager, Connection $connection) {
    $this->lingotek = $lingotek;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekConfigTranslation = $lingotek_config_translation;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function checkSourceStatus(ContentEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    $document_id = $this->getDocumentId($entity);
    if ($document_id) {
      // Document has successfully imported.
      if ($this->lingotek->getDocumentStatus($document_id)) {
        $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        return TRUE;
      }
      else {
        $this->checkForTimeout($entity);
        return FALSE;
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * Checks the time elapsed since the last upload and sets the entity
   * to error if the max time has elapsed.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   */
  protected function checkForTimeout(ContentEntityInterface &$entity) {
    // We set a max time of 1 hour for the import (in seconds)
    $maxImportTime = 3600;
    if ($last_uploaded_time = $this->getLastUpdated($entity) ?: $this->getLastUploaded($entity)) {
      // If document has not successfully imported after MAX_IMPORT_TIME
      // then move to ERROR state.
      if (\Drupal::time()->getRequestTime() - $last_uploaded_time > $maxImportTime) {
        $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      }
      else {
        // Document still may be importing and the MAX import time didn't
        // complete yet, so we do nothing.
      }
      // TODO: Remove the elseif clause after 4.0.0 is released
    }
    elseif ($entity->getEntityType()->entityClassImplements(EntityChangedInterface::class)) {
      $last_uploaded_time = $entity->getChangedTime();
      if (\Drupal::time()->getRequestTime() - $last_uploaded_time > $maxImportTime) {
        $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceStatus(ContentEntityInterface &$entity) {
    $source_language = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    $metadata = $entity->lingotek_metadata ? $entity->lingotek_metadata->entity : NULL;
    if ($metadata !== NULL && $metadata->translation_source && $metadata->translation_source->value !== NULL) {
      $source_language = $metadata->translation_source->value;
    }
    if ($source_language == LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $source_language = $entity->getUntranslated()->language()->getId();
    }
    return $this->getTargetStatus($entity, $source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceStatus(ContentEntityInterface &$entity, $status) {
    $metadata = $entity->lingotek_metadata->entity;
    $source_language = $metadata->translation_source->value;
    if ($source_language == LanguageInterface::LANGCODE_NOT_SPECIFIED || $source_language == NULL) {
      $source_language = $entity->getUntranslated()->language()->getId();
    }
    return $this->setTargetStatus($entity, $source_language, $status);
  }

  /**
   * Clear the target statuses.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   */
  protected function clearTargetStatuses(ContentEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    // Clear the target statuses. As we save the source status with the target,
    // we need to keep that one.
    $source_status = $this->getSourceStatus($entity);

    $metadata = &$entity->lingotek_metadata->entity;
    if ($metadata->hasField('translation_status') && count($metadata->translation_status) > 0) {
      $metadata->translation_status = NULL;
    }
    $this->setTargetStatus($entity, $entity->getUntranslated()->language()->getId(), $source_status);
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatuses(ContentEntityInterface &$entity) {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    $document_id = $this->getDocumentId($entity);
    $translation_statuses = $this->lingotek->getDocumentTranslationStatuses($document_id);
    $source_status = $this->getSourceStatus($entity);

    $statuses = [];
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $language) {
      $statuses[$language->getId()] = $this->getTargetStatus($entity, $language->getId());
    }

    // Let's reset all statuses, but keep the source one.
    $this->clearTargetStatuses($entity);

    foreach ($translation_statuses as $lingotek_locale => $progress) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($lingotek_locale);
      if ($drupal_language == NULL) {
        // Language existing in TMS, but not configured on Drupal.
        continue;
      }
      $langcode = $drupal_language->id();
      $current_target_status = $statuses[$langcode];
      if (in_array($current_target_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_DISABLED, Lingotek::STATUS_EDITED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_NONE, Lingotek::STATUS_READY, Lingotek::STATUS_PENDING, Lingotek::STATUS_CANCELLED, NULL])) {
        if ($progress === Lingotek::STATUS_CANCELLED) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CANCELLED);
        }
        elseif ($progress === Lingotek::PROGRESS_COMPLETE) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_READY);
        }
        else {
          if (!$profile->hasDisabledTarget($langcode)) {
            $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_PENDING);
          }
          else {
            $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_DISABLED);
          }
        }
      }
      if ($source_status !== Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_EDITED && $langcode !== $entity->getUntranslated()->language()->getId()) {
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
      }
      if ($source_status === Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_CURRENT && $langcode !== $entity->getUntranslated()->language()->getId()) {
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
      }
      if ($profile->hasDisabledTarget($langcode)) {
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_DISABLED);
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatus(ContentEntityInterface &$entity, $langcode) {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED
        || $profile->hasDisabledTarget($langcode)) {
      return FALSE;
    }
    $current_status = $this->getTargetStatus($entity, $langcode);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $source_status = $this->getSourceStatus($entity);
    $document_id = $this->getDocumentId($entity);
    if ($langcode !== $entity->getUntranslated()->language()->getId()) {
      if (($current_status == Lingotek::STATUS_PENDING ||
      $current_status == Lingotek::STATUS_EDITED) &&
      $source_status !== Lingotek::STATUS_EDITED) {
        $translation_status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        if ($translation_status === Lingotek::STATUS_CANCELLED) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CANCELLED);
        }
        elseif ($translation_status === TRUE) {
          $current_status = Lingotek::STATUS_READY;
          $this->setTargetStatus($entity, $langcode, $current_status);
        }
        // We may not be ready, but some phases must be complete. Let's try to
        // download data, and if there is anything, we can assume a phase is
        // completed.
        // ToDo: Instead of downloading would be nice if we could check phases.
        elseif ($this->lingotek->downloadDocument($document_id, $locale)) {
          // TODO: Set Status to STATUS_READY_INTERIM when that status is
          // available. See ticket: https://www.drupal.org/node/2850548
        }
      }
      elseif ($current_status == Lingotek::STATUS_REQUEST || $current_status == Lingotek::STATUS_UNTRACKED) {
        $translation_status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        if ($translation_status === TRUE) {
          $current_status = Lingotek::STATUS_READY;
          $this->setTargetStatus($entity, $langcode, $current_status);
        }
        elseif ($translation_status !== FALSE) {
          $current_status = Lingotek::STATUS_PENDING;
          $this->setTargetStatus($entity, $langcode, $current_status);
        } //elseif ($this->lingotek->downloadDocument($document_id, $locale)) {
        //   // TODO: Set Status to STATUS_READY_INTERIM when that status is
        //   // available. See ticket: https://www.drupal.org/node/2850548
        // }
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return $current_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatus(ContentEntityInterface &$entity, $langcode) {
    $status = Lingotek::STATUS_UNTRACKED;
    $statuses = $this->getTargetStatuses($entity);
    if (isset($statuses[$langcode])) {
      $status = $statuses[$langcode];
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatuses(ContentEntityInterface &$entity) {
    $statuses = [];
    $metadata = $entity->lingotek_metadata ? $entity->lingotek_metadata->entity : NULL;
    if ($metadata !== NULL && count($metadata->translation_status) > 0) {
      foreach ($metadata->translation_status->getIterator() as $delta => $value) {
        $statuses[$value->language] = $value->value;
      }
    }
    return $statuses;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatus(ContentEntityInterface &$entity, $langcode, $status, $save = TRUE) {
    $set = FALSE;
    if (!$entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::loadByTargetId($entity->getEntityTypeId(), $entity->id());
    }
    $metadata = &$entity->lingotek_metadata->entity;
    if ($metadata->hasField('translation_status') && count($metadata->translation_status) > 0) {
      foreach ($metadata->translation_status->getIterator() as $delta => $value) {
        if ($value->language == $langcode) {
          $value->value = $status;
          $set = TRUE;
        }
      }
    }
    if (!$set && $metadata->hasField('translation_status')) {
      $metadata->translation_status->appendItem(['language' => $langcode, 'value' => $status]);
      $set = TRUE;
    }
    if ($set) {
      $entity->lingotek_processed = TRUE;
      $metadata->save();
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatuses(ContentEntityInterface &$entity, $status) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->getUntranslated()->language()->getId();

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getTargetStatus($entity, $langcode)) {
        if ($current_status === Lingotek::STATUS_PENDING && $status === Lingotek::STATUS_REQUEST) {
          // Don't allow to pass from pending to request. We have been already
          // requested this one.
          continue;
        }
        if (in_array($current_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_DISABLED, NULL]) && $status === Lingotek::STATUS_PENDING) {
          continue;
        }
        if ($current_status == $status) {
          continue;
        }
        if ($current_status != Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_CURRENT) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
        elseif ($current_status == Lingotek::STATUS_EDITED && in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_PENDING])) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
        if ($status === Lingotek::STATUS_CANCELLED) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
        if ($status === Lingotek::STATUS_DISABLED) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function markTranslationsAsDirty(ContentEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->getUntranslated()->language()->getId();

    // Only mark as out of date the current ones.
    $to_change = [
      Lingotek::STATUS_CURRENT,
      // Lingotek::STATUS_PENDING,
      // Lingotek::STATUS_INTERMEDIATE,
      // Lingotek::STATUS_READY,
    ];

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getTargetStatus($entity, $langcode)) {
        if (in_array($current_status, $to_change)) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_PENDING);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentId(ContentEntityInterface &$entity) {
    $doc_id = NULL;
    $metadata = $entity->hasField('lingotek_metadata') ? $entity->lingotek_metadata->entity : NULL;
    if ($metadata !== NULL && $metadata->document_id) {
      $doc_id = $metadata->document_id->value;
    }
    return $doc_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setDocumentId(ContentEntityInterface &$entity, $doc_id) {
    if ($entity->lingotek_metadata->entity === NULL) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::loadByTargetId($entity->getEntityTypeId(), $entity->id());
    }
    $entity->lingotek_processed = TRUE;
    $entity->lingotek_metadata->entity->setDocumentId($doc_id);
    $entity->lingotek_metadata->entity->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLocale(ContentEntityInterface &$entity) {
    $source_language = $entity->getUntranslated()->language()->getId();
    return $this->languageLocaleMapper->getLocaleForLangcode($source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceData(ContentEntityInterface &$entity, &$visited = []) {
    // Logic adapted from Content Translation core module and TMGMT contrib
    // module for pulling translatable field info from content entities.
    $isParentEntity = count($visited) === 0;
    $visited[$entity->bundle()][] = $entity->id();
    $entity_type = $entity->getEntityType();
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $storage_definitions = $entity_type instanceof ContentEntityTypeInterface ? $this->entityFieldManager->getFieldStorageDefinitions($entity_type->id()) : [];
    $translatable_fields = [];
    // We need to include computed fields, as we may have a URL alias.
    foreach ($entity->getFields(TRUE) as $field_name => $definition) {
      if ($this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $field_name)
        && $field_name != $entity_type->getKey('langcode')
        && $field_name != $entity_type->getKey('default_langcode')) {
        $translatable_fields[$field_name] = $definition;
      }
    }
    $default_display = $this->entityTypeManager->getStorage('entity_view_display')
      ->load($entity_type->id() . '.' . $entity->bundle() . '.' . 'default');
    if ($default_display !== NULL) {
      uksort($translatable_fields, function ($a, $b) use ($default_display) {
        return SortArray::sortByKeyString($default_display->getComponent($a), $default_display->getComponent($b), 'weight');
      });
    }

    $data = [];
    $source_entity = $entity->getUntranslated();
    foreach ($translatable_fields as $k => $definition) {
      // If there is only one relevant attribute, upload it.
      // Get the column translatability configuration.
      module_load_include('inc', 'content_translation', 'content_translation.admin');
      $column_element = content_translation_field_sync_widget($field_definitions[$k]);
      $field = $source_entity->get($k);
      $field_type = $field_definitions[$k]->getType();

      if ($field->isEmpty()) {
        $data[$k] = [];
      }
      foreach ($field as $fkey => $fval) {
        // If we have only one relevant column, upload that. If not, check our
        // settings.
        if (!$column_element) {
          $properties = $fval->getProperties();
          foreach ($properties as $property_name => $property_value) {
            if (isset($storage_definitions[$k])) {
              $property_definition = $storage_definitions[$k]->getPropertyDefinition($property_name);
              $data_type = $property_definition->getDataType();
              if (($data_type === 'string' || $data_type === 'uri') && !$property_definition->isComputed()) {
                if (isset($fval->$property_name) && !empty($fval->$property_name)) {
                  $data[$k][$fkey][$property_name] = $fval->get($property_name)
                    ->getValue();
                }
                // If there is a path item, we need to handle that the pid is a
                // string but we don't want to upload it. See
                // https://www.drupal.org/node/2689253.
                if ($field_type === 'path') {
                  unset($data[$k][$fkey]['pid']);
                }
              }
            }
          }
        }
        else {
          $configured_properties = $this->lingotekConfiguration->getFieldPropertiesLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $k);
          $properties = $fval->getProperties();
          foreach ($properties as $pkey => $pval) {
            if (isset($configured_properties[$pkey]) && $configured_properties[$pkey]) {
              $data[$k][$fkey][$pkey] = $pval->getValue();
            }
          }
        }
      }
      if ($field_type === 'block_field') {
        foreach ($entity->{$k} as $field_item) {
          $pluginId = $field_item->get('plugin_id')->getValue();
          $block_instance = $field_item->getBlock();
          $lingotekConfigTranslation = \Drupal::service('lingotek.config_translation');
          /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager */
          $typedConfigManager = \Drupal::service('config.typed');
          $pluginIDName = $block_instance->getPluginDefinition()['id'];
          $blockConfig = $block_instance->getConfiguration();
          $definition = $typedConfigManager->getDefinition('block.settings.' . $pluginIDName);
          if ($definition['type'] == 'undefined') {
            $definition = $typedConfigManager->getDefinition('block_settings');
          }
          $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $blockConfig);
          $schema = $typedConfigManager->create($dataDefinition, $blockConfig);
          $properties = $lingotekConfigTranslation->getTranslatableProperties($schema, NULL);
          $embedded_data = [];
          foreach ($properties as $property) {
            $propertyParts = explode('.', $property);
            $embedded_data[$property] = NestedArray::getValue($blockConfig, $propertyParts);
          }
          if (strpos($pluginId, 'block_content') === 0) {
            $uuid = $block_instance->getDerivativeId();
            if ($block = \Drupal::service('entity.repository')->loadEntityByUuid('block_content', $uuid)) {
              $embedded_data['entity'] = $this->getSourceData($block, $visited);
            }
          }
          $data[$k][$field_item->getName()] = $embedded_data;
        }
      }
      if ($field_type === 'layout_section') {
        // This means that we are using layout:builder_at. We verify it anyway.
        $layoutBuilderAT = \Drupal::moduleHandler()->moduleExists('layout_builder_at');
        if ($layoutBuilderAT) {
          $block_manager = \Drupal::service('plugin.manager.block');
          $lingotekConfigTranslation = \Drupal::service('lingotek.config_translation');
          /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager */
          $typedConfigManager = \Drupal::service('config.typed');
          $data[$k] = ['components' => []];
          foreach ($entity->{$k} as $field_item) {
            $sectionObject = $field_item->getValue()['section'];
            $components = $sectionObject->getComponents();
            /** @var \Drupal\layout_builder\SectionComponent $component */
            foreach ($components as $componentUuid => $component) {
              /** @var \Drupal\Core\Block\BlockPluginInterface $block_instance */
              // TODO: Change this to getConfiguration() when is safe to do so.
              // See https://www.drupal.org/project/drupal/issues/3180555.
              $block_instance = $block_manager->createInstance($component->getPluginId(), $component->get('configuration'));
              $pluginIDName = $block_instance->getPluginDefinition()['id'];
              $blockConfig = $block_instance->getConfiguration();
              $definition = $typedConfigManager->getDefinition('block.settings.' . $pluginIDName);
              if ($definition['type'] == 'undefined') {
                $definition = $typedConfigManager->getDefinition('block_settings');
              }
              $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $blockConfig);
              $schema = $typedConfigManager->create($dataDefinition, $blockConfig);
              $properties = $lingotekConfigTranslation->getTranslatableProperties($schema, NULL);

              $embedded_data = [];
              foreach ($properties as $property) {
                // The data definition will return nested keys as dot separated.
                $propertyParts = explode('.', $property);
                $embedded_data[$property] = NestedArray::getValue($blockConfig, $propertyParts);
              }
              $data[$k]['components'][$componentUuid] = $embedded_data;

              if (strpos($pluginIDName, 'inline_block') === 0) {
                $blockRevisionId = $blockConfig['block_revision_id'];
                if ($block = $this->entityTypeManager->getStorage('block_content')->loadRevision($blockRevisionId)) {
                  $data[$k]['entities']['block_content'][$blockRevisionId] = $this->getSourceData($block, $visited);
                }
              }
            }
          }
        }
      }
      if ($field_type === 'layout_translation') {
        // We need to get the original data from the layout.
        $layoutBuilderST = \Drupal::moduleHandler()->moduleExists('layout_builder_st');
        if ($layoutBuilderST) {
          $data[$k] = ['components' => []];
          $layoutField = $entity->{OverridesSectionStorage::FIELD_NAME};
          $block_manager = \Drupal::service('plugin.manager.block');
          $lingotekConfigTranslation = \Drupal::service('lingotek.config_translation');
          /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager */
          $typedConfigManager = \Drupal::service('config.typed');
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
              $block_instance = $block_manager->createInstance($component->getPluginId(), $component->get('configuration'));
              $pluginIDName = $block_instance->getPluginDefinition()['id'];
              $blockConfig = $block_instance->getConfiguration();
              $definition = $typedConfigManager->getDefinition('block.settings.' . $pluginIDName);
              if ($definition['type'] == 'undefined') {
                $definition = $typedConfigManager->getDefinition('block_settings');
              }
              $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $blockConfig);
              $schema = $typedConfigManager->create($dataDefinition, $blockConfig);
              $properties = $lingotekConfigTranslation->getTranslatableProperties($schema, NULL);

              $embedded_data = [];
              foreach ($properties as $property) {
                // The data definition will return nested keys as dot separated.
                $propertyParts = explode('.', $property);
                $embedded_data[$property] = NestedArray::getValue($blockConfig, $propertyParts);
              }
              $data[$k]['components'][$componentUuid] = $embedded_data;

              if (strpos($pluginIDName, 'inline_block') === 0) {
                $blockRevisionId = $blockConfig['block_revision_id'];
                if ($block = $this->entityTypeManager->getStorage('block_content')->loadRevision($blockRevisionId)) {
                  $data[$k]['entities']['block_content'][$blockRevisionId] = $this->getSourceData($block, $visited);
                }
              }
            }
          }
        }
      }
      // If we have an entity reference, we may want to embed it.
      if ($field_type === 'entity_reference' || $field_type === 'er_viewmode' || $field_type === 'bricks') {
        $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()->getSetting('target_type');
        foreach ($entity->{$k} as $field_item) {
          $embedded_entity_id = $field_item->get('target_id')->getValue();
          $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)->load($embedded_entity_id);
          // We may have orphan references, so ensure that they exist before
          // continuing.
          if ($embedded_entity !== NULL) {
            if ($embedded_entity instanceof ContentEntityInterface) {
              // We need to avoid cycles if we have several entity references
              // referencing each other.
              if (!isset($visited[$embedded_entity->bundle()]) || !in_array($embedded_entity->id(), $visited[$embedded_entity->bundle()])) {
                $embedded_data = $this->getSourceData($embedded_entity, $visited);
                $data[$k][$field_item->getName()] = $embedded_data;
              }
              else {
                // We don't want to embed the data, but still will need the
                // references, so let's include the metadata.
                $metadata = [];
                $this->includeMetadata($embedded_entity, $metadata, FALSE);
                $data[$k][$field_item->getName()] = $metadata;
              }
            }
            elseif ($embedded_entity instanceof ConfigEntityInterface) {
              $embedded_data = $this->lingotekConfigTranslation->getSourceData($embedded_entity);
              $data[$k][$field_item->getName()] = $embedded_data;
            }
          }
          else {
            // If the referenced entity doesn't exist, remove the target_id
            // that may be already set.
            unset($data[$k]);
          }
        }
      }
      // Paragraphs use the entity_reference_revisions field type.
      // Cohesion uses a similar type and we can reuse this.
      elseif ($field_type === 'entity_reference_revisions' || $field_type === 'cohesion_entity_reference_revisions') {
        $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()->getSetting('target_type');
        foreach ($entity->{$k} as $field_item) {
          $embedded_entity_id = $field_item->get('target_id')->getValue();
          $embedded_entity_revision_id = $field_item->get('target_revision_id')->getValue();
          $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)->loadRevision($embedded_entity_revision_id);
          // Handle the unlikely case where a paragraph has lost its parent.
          if (!empty($embedded_entity)) {
            $embedded_data = $this->getSourceData($embedded_entity, $visited);
            $data[$k][$field_item->getName()] = $embedded_data;
          }
          else {
            // If the referenced entity doesn't exist, remove the target_id
            // that may be already set.
            unset($data[$k]);
          }
        }
      }
      elseif ($field_type === 'tablefield') {
        foreach ($entity->{$k} as $index => $field_item) {
          $tableValue = $field_item->value;
          $embedded_data = [];
          foreach ($tableValue as $row_index => $row) {
            if ($row_index === 'caption') {
              $embedded_data[$index]['caption'] = $row;
            }
            else {
              foreach ($row as $col_index => $cell) {
                $embedded_data[$index]['row:' . $row_index]['col:' . $col_index] = $cell;
              }
            }
          }
          $data[$k] = $embedded_data;
        }
      }
      // Special treatment for Acquia Site Builder (Cohesion) values.
      elseif ($field_type === 'string_long' && $field->getName() === 'json_values' && $field->getEntity()->getEntityTypeId() === 'cohesion_layout') {
        $value = $entity->{$k}->value;
        $layout_canvas = new LayoutCanvas($value);
        foreach ($layout_canvas->iterateCanvas() as $element) {
          $data_layout = [];
          if ($element->isComponent() && $component = Component::load($element->getComponentID())) {
            // Get the models of each form field of the component as an array keyed by their uuid
            $component_model = $component->getLayoutCanvasInstance()
              ->iterateModels('component_form');
            if ($element->getModel()) {
              $data_layout = array_merge(
                $data_layout,
                $this->extractCohesionComponentValues($component_model, $element->getModel()->getValues())
              );
            }
          }

          if (!empty($data_layout)) {
            $data[$k][$element->getModelUUID()] = $data_layout;
          }
        }
        unset($data[$k][0]);
      }
      elseif ($field_type === 'metatag') {
        foreach ($entity->{$k} as $field_item) {
          $metatag_serialized = $field_item->get('value')->getValue();
          $metatags = unserialize($metatag_serialized);
          if ($metatags) {
            $data[$k][$field_item->getName()] = $metatags;
          }
        }
      }
      // We could have a path as computed field.
      elseif ($field_type === 'path') {
        if ($entity->id()) {
          $source = '/' . $entity->toUrl()->getInternalPath();
          /** @var \Drupal\Core\Entity\EntityStorageInterface $aliasStorage */
          $alias_storage = $this->entityTypeManager->getStorage('path_alias');
          /** @var \Drupal\path_alias\PathAliasInterface[] $paths */
          $paths = $alias_storage->loadByProperties(['path' => $source, 'langcode' => $entity->language()->getId()]);
          if (count($paths) > 0) {
            $path = reset($paths);
            $alias = $path->getAlias();
            if ($alias !== NULL) {
              $data[$k][0]['alias'] = $alias;
            }
          }
        }
      }
    }
    // Embed entity metadata. We need to exclude intelligence metadata if it is
    // a child entity.
    $this->includeMetadata($entity, $data, $isParentEntity);

    return $data;
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

      $form_field = \Drupal::keyValue('cohesion.assets.form_elements')
        ->get($component->getElement()->getProperty('uid'));
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
              \Drupal::logger('lingotek')
                ->warning('Unhandled component type of \'%type\' (schema type: %schema) encountered when extracting cohesion component values.', [
                  '%type' => $component_type,
                  '%schema' => $schema_type,
                ]);
              break;
          }

          break;

        default:
          \Drupal::logger('lingotek')->warning(
            'Unhandled schema type of \'%type\' encountered when extracting cohesion component values.',
            ['%type' => $schema_type]
          );
          break;
      }
    }

    return $field_values;
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
              \Drupal::logger('lingotek')
                ->warning('Unhandled component type of \'%type\' (schema type: %schema) encountered when setting cohesion component values.', [
                  '%type' => $component_type,
                  '%schema' => $schema_type,
                ]);
              break;
          }

          break;

        default:
          \Drupal::logger('lingotek')->warning(
            'Unhandled schema type of \'%type\' encountered when setting cohesion component values.',
            ['%type' => $schema_type]
          );
          break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateEntityHash(ContentEntityInterface $entity) {
    $source_data = json_encode($this->getSourceData($entity));
    if ($entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity->lingotek_hash = md5($source_data);
      $entity->lingotek_metadata->entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasEntityChanged(ContentEntityInterface &$entity) {
    if (isset($entity->original)) {
      if ($entity->getRevisionId() !== $entity->original->getRevisionId()) {
        return TRUE;
      }
      $source_data = $this->getSourceData($entity);
      if (isset($source_data['_lingotek_metadata'])) {
        unset($source_data['_lingotek_metadata']['_entity_revision']);
      }
      $source_data = json_encode($source_data);
      $hash = md5($source_data);
      $old_source_data = $this->getSourceData($entity->original);
      if (isset($old_source_data['_lingotek_metadata'])) {
        unset($old_source_data['_lingotek_metadata']['_entity_revision']);
      }
      $old_source_data = json_encode($old_source_data);
      $old_hash = md5($old_source_data);
      return (bool) strcmp($hash, $old_hash);
    }
    else {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addTarget(ContentEntityInterface &$entity, $locale) {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED ||
            $profile->hasDisabledTarget($drupal_language->getId())) {
      return FALSE;
    }
    $source_langcode = $entity->getUntranslated()->language()->getId();
    $source_locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);

    if ($locale == $source_locale) {
      // We don't want to translate from one language to itself.
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $source_status = $this->getSourceStatus($entity);
      $current_status = $this->getTargetStatus($entity, $drupal_language->id());

      // When a translation is in one of these states, we know that it hasn't yet been sent up to the Lingotek API,
      // which means that we'll have to call addTarget() on it.
      //
      // TODO: should we consider STATUS_NONE as a "pristine" status?
      $pristine_statuses = [
        Lingotek::STATUS_REQUEST,
        Lingotek::STATUS_UNTRACKED,
        Lingotek::STATUS_EDITED,
      ];

      if (in_array($current_status, $pristine_statuses)) {
        try {
          $result = $this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getEntityProfile($entity));
        }
        catch (LingotekDocumentLockedException $exception) {
          $this->setDocumentId($entity, $exception->getNewDocumentId());
          throw $exception;
        }
        catch (LingotekDocumentArchivedException $exception) {
          $this->setDocumentId($entity, NULL);
          $this->deleteMetadata($entity);
          throw $exception;
        }
        catch (LingotekPaymentRequiredException $exception) {
          throw $exception;
        }
        catch (LingotekApiException $exception) {
          throw $exception;
        }
        if ($result) {
          $this->setTargetStatus($entity, $drupal_language->id(), Lingotek::STATUS_PENDING);
          // If the status was "Importing", and the target was added
          // successfully, we can ensure that the content is current now.
          if ($source_status == Lingotek::STATUS_IMPORTING) {
            $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          }
          return TRUE;
        }
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslations(ContentEntityInterface &$entity) {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    $languages = [];
    if ($document_id = $this->getDocumentId($entity)) {
      $target_languages = $this->languageManager->getLanguages();
      $target_languages = array_filter($target_languages, function (LanguageInterface $language) {
        $configLanguage = ConfigurableLanguage::load($language->getId());
        return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
      });

      $entity_langcode = $entity->getUntranslated()->language()->getId();

      foreach ($target_languages as $langcode => $language) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
        if ($langcode !== $entity_langcode) {
          if (!$profile->hasDisabledTarget($langcode)) {
            $source_status = $this->getSourceStatus($entity);
            $current_status = $this->getTargetStatus($entity, $langcode);
            if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT && $current_status !== Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_READY) {
              try {
                $result = $this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getEntityProfile($entity));
              }
              catch (LingotekDocumentLockedException $exception) {
                $this->setDocumentId($entity, $exception->getNewDocumentId());
                throw $exception;
              }
              catch (LingotekDocumentArchivedException $exception) {
                $this->setDocumentId($entity, NULL);
                $this->deleteMetadata($entity);
                throw $exception;
              }
              catch (LingotekPaymentRequiredException $exception) {
                throw $exception;
              }
              catch (LingotekApiException $exception) {
                throw $exception;
              }
              if ($result) {
                $languages[] = $langcode;
                $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_PENDING);
                // If the status was "Importing", and the target was added
                // successfully, we can ensure that the content is current now.
                if ($source_status == Lingotek::STATUS_IMPORTING) {
                  $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
                }
              }
            }
          }
        }
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument(ContentEntityInterface $entity, $job_id = NULL) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    // We can reupload if the document is cancelled.
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      return FALSE;
    }
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($entity) ?: NULL;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      return $this->updateDocument($entity, $job_id);
    }
    $source_data = $this->getSourceData($entity);
    $extended_name = $entity->bundle() . ' (' . $entity->getEntityTypeId() . '): ' . $entity->label();

    $profile_preference = $profile->getAppendContentTypeToTitle();
    $global_preference = $this->lingotekConfiguration->getPreference('append_type_to_title');
    switch ($profile_preference) {
      case 'yes':
        $document_name = $extended_name;
        break;

      case 'no':
        $document_name = $entity->label();
        break;

      case 'global_setting':
        $document_name = $global_preference ? $extended_name : $entity->label();
        break;

      default:
        $document_name = $extended_name;
    }

    $url = $entity->hasLinkTemplate('canonical') ? $entity->toUrl()->setAbsolute(TRUE)->toString() : NULL;

    // Allow other modules to alter the data before is uploaded.
    \Drupal::moduleHandler()->invokeAll('lingotek_content_entity_document_upload', [&$source_data, &$entity, &$url]);

    try {
      $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getSourceLocale($entity), $url, $profile, $job_id);
    }
    catch (LingotekPaymentRequiredException $exception) {
      $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      throw $exception;
    }
    catch (LingotekApiException $exception) {
      $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      throw $exception;
    }
    if ($document_id) {
      $this->lingotekConfiguration->setProfile($entity, $profile->id());
      $this->setDocumentId($entity, $document_id);
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_REQUEST);
      $this->setJobId($entity, $job_id);
      $this->setLastUploaded($entity, \Drupal::time()->getRequestTime());
      return $document_id;
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocument(ContentEntityInterface &$entity, $locale) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      \Drupal::logger('lingotek')->warning('Avoided download for (%entity_id,%revision_id): Source status is %source_status.', ['%entity_id' => $entity->id(), '%revision_id' => $entity->getRevisionId(), '%source_status' => $this->getSourceStatus($entity)]);
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $source_status = $this->getSourceStatus($entity);
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
      $langcode = $drupal_language->id();
      $data = [];
      try {
        if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) !== FALSE) {
          $data = $this->lingotek->downloadDocument($document_id, $locale);
        }
        else {
          \Drupal::logger('lingotek')->warning('Avoided download for (%entity_id,%revision_id): Source status is %source_status.', ['%entity_id' => $entity->id(), '%revision_id' => $entity->getRevisionId(), '%source_status' => $this->getSourceStatus($entity)]);
          return NULL;
        }
      }
      catch (LingotekApiException $exception) {
        \Drupal::logger('lingotek')->error('Error happened downloading %document_id %locale: %message', ['%document_id' => $document_id, '%locale' => $locale, '%message' => $exception->getMessage()]);
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
        throw $exception;
      }

      if ($data) {
        // Check the real status, because it may still need review or anything.
        $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        $transaction = $this->connection->startTransaction();
        try {
          $saved = $this->saveTargetData($entity, $langcode, $data);
          if ($saved) {
            // If the status was "Importing", and the target was added
            // successfully, we can ensure that the content is current now.
            if ($source_status == Lingotek::STATUS_IMPORTING) {
              $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
            }
            if ($source_status == Lingotek::STATUS_EDITED) {
              $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
            }
            elseif ($status === TRUE) {
              $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
            }
            else {
              $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_INTERMEDIATE);
            }
          }
        }
        catch (LingotekContentEntityStorageException $storageException) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
          \Drupal::logger('lingotek')->error('Error happened (storage) saving %document_id %locale: %message', ['%document_id' => $document_id, '%locale' => $locale, '%message' => $storageException->getMessage()]);
          throw $storageException;
        }
        catch (\Exception $exception) {
          $transaction->rollBack();
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
          \Drupal::logger('lingotek')->error('Error happened (unknown) saving %document_id %locale: %message', ['%document_id' => $document_id, '%locale' => $locale, '%message' => $exception->getMessage()]);
          return FALSE;
        }
        return TRUE;
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    \Drupal::logger('lingotek')->warning('Error happened trying to download (%entity_id,%revision_id): no document id found.', ['%entity_id' => $entity->id(), '%revision_id' => $entity->getRevisionId()]);
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument(ContentEntityInterface &$entity, $job_id = NULL) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($entity) ?: NULL;
    }
    $source_data = $this->getSourceData($entity);
    $document_id = $this->getDocumentId($entity);
    $source_langcode = $entity->getUntranslated()->language()->getId();
    $source_locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);
    $url = $entity->hasLinkTemplate('canonical') ? $entity->toUrl()->setAbsolute(TRUE)->toString() : NULL;
    $extended_name = $entity->bundle() . ' (' . $entity->getEntityTypeId() . '): ' . $entity->label();
    $profile_preference = $profile->getAppendContentTypeToTitle();
    $global_preference = $this->lingotekConfiguration->getPreference('append_type_to_title');
    switch ($profile_preference) {
      case 'yes':
        $document_name = $extended_name;
        break;

      case 'no':
        $document_name = $entity->label();
        break;

      case 'global_setting':
        $document_name = $global_preference ? $extended_name : $entity->label();
        break;

      default:
        $document_name = $extended_name;
    }

    // Allow other modules to alter the data before is uploaded.
    \Drupal::moduleHandler()->invokeAll('lingotek_content_entity_document_upload', [&$source_data, &$entity, &$url]);

    try {
      $newDocumentID = $this->lingotek->updateDocument($document_id, $source_data, $url, $document_name, $profile, $job_id, $source_locale);
    }
    catch (LingotekDocumentLockedException $exception) {
      $this->setDocumentId($entity, $exception->getNewDocumentId());
      throw $exception;
    }
    catch (LingotekDocumentArchivedException $exception) {
      $this->setDocumentId($entity, NULL);
      $this->deleteMetadata($entity);
      throw $exception;
    }
    catch (LingotekPaymentRequiredException $exception) {
      $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      throw $exception;
    }
    catch (LingotekApiException $exception) {
      $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      throw $exception;
    }

    if ($newDocumentID) {
      if (is_string($newDocumentID)) {
        $document_id = $newDocumentID;
        $this->setDocumentId($entity, $newDocumentID);
      }
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_PENDING);
      $this->setJobId($entity, $job_id);
      $this->setLastUpdated($entity, \Drupal::time()->getRequestTime());
      return $newDocumentID;
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  public function downloadDocuments(ContentEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $source_status = $this->getSourceStatus($entity);
      $target_languages = $this->languageManager->getLanguages();
      $target_languages = array_filter($target_languages, function (LanguageInterface $language) {
        $configLanguage = ConfigurableLanguage::load($language->getId());
        return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
      });

      $entity_langcode = $entity->getUntranslated()->language()->getId();

      foreach ($target_languages as $langcode => $language) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
        if ($langcode !== $entity_langcode) {
          try {
            if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) !== FALSE) {
              $data = $this->lingotek->downloadDocument($document_id, $locale);
              if ($data) {
                // Check the real status, because it may still need review or anything.
                $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
                $transaction = $this->connection->startTransaction();
                try {
                  $saved = $this->saveTargetData($entity, $langcode, $data);
                  if ($saved) {
                    // If the status was "Importing", and the target was added
                    // successfully, we can ensure that the content is current now.
                    if ($source_status == Lingotek::STATUS_IMPORTING) {
                      $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
                    }
                    if ($source_status == Lingotek::STATUS_EDITED) {
                      $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
                    }
                    elseif ($status === TRUE) {
                      $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
                    }
                    else {
                      $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_INTERMEDIATE);
                    }
                  }
                }
                catch (LingotekApiException $exception) {
                  // TODO: log issue
                  $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
                  throw $exception;
                }
                catch (LingotekContentEntityStorageException $storageException) {
                  $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
                  throw $storageException;
                }
                catch (\Exception $exception) {
                  $transaction->rollBack();
                  $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
                }
              }
              else {
                return NULL;
              }
            }
          }
          catch (LingotekApiException $exception) {
            // TODO: log issue
            $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
            throw $exception;
          }
        }
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocument(ContentEntityInterface &$entity) {
    $result = FALSE;
    $doc_id = $this->getDocumentId($entity);
    if ($doc_id) {
      $result = $this->lingotek->cancelDocument($doc_id);
      $this->lingotekConfiguration->setProfile($entity, NULL);
      $this->setDocumentId($entity, NULL);
    }
    $this->setSourceStatus($entity, Lingotek::STATUS_CANCELLED);
    $this->setTargetStatuses($entity, Lingotek::STATUS_CANCELLED);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocumentTarget(ContentEntityInterface &$entity, $locale) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED || $this->getSourceStatus($entity) === Lingotek::STATUS_CANCELLED) {
      return FALSE;
    }
    $source_langcode = $entity->getUntranslated()->language()->getId();
    $source_locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);

    if ($locale == $source_locale) {
      // This is not a target, but the source language itself.
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);

      if ($this->lingotek->cancelDocumentTarget($document_id, $locale)) {
        $this->setTargetStatus($entity, $drupal_language->id(), Lingotek::STATUS_CANCELLED);
        return TRUE;
      }
    }

    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMetadata(ContentEntityInterface &$entity) {
    $doc_id = $this->getDocumentId($entity);
    if ($doc_id) {
      $this->cancelDocument($entity);
    }
    $metadata = $entity->lingotek_metadata->entity;
    if ($metadata !== NULL) {
      $metadata->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDocumentId($document_id) {
    $entity = NULL;
    $metadata = LingotekContentMetadata::loadByDocumentID($document_id);
    if ($metadata && $metadata->getContentEntityTypeId() && $metadata->getContentEntityId()) {
      $entity = $this->entityTypeManager->getStorage($metadata->getContentEntityTypeId())->load($metadata->getContentEntityId());
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllLocalDocumentIds() {
    return LingotekContentMetadata::getAllLocalDocumentIds();
  }

  /**
   * Loads the correct revision is loaded from the database, bypassing caches.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity we want to load a revision from.
   * @param int|null $revision
   *   The revision id. NULL if we don't know it.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The wanted revision of the entity.
   */
  protected function loadUploadedRevision(ContentEntityInterface $entity, $revision = NULL) {
    $the_revision = NULL;

    $entity_type = $entity->getEntityType();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

    if ($entity_type->isRevisionable()) {
      // If the entity type is revisionable, we need to check the proper revision.
      // This may come from the uploaded data, but in case we didn't have it, we
      // have to infer using the timestamp.
      if ($revision !== NULL) {
        $the_revision = $entity_storage->loadRevision($revision);
      }
      elseif ($revision === NULL && $entity->hasField('revision_timestamp')) {
        // Let's find the better revision based on the timestamp.
        $timestamp = $this->lingotek->getUploadedTimestamp($this->getDocumentId($entity));
        $revision = $this->getClosestRevisionToTimestamp($entity, $timestamp);
        if ($revision !== NULL) {
          $the_revision = $entity_storage->loadRevision($revision);
        }
      }
      if ($the_revision === NULL) {
        // We didn't find a better option, but let's reload this one so it's not
        // cached.
        $the_revision = $entity_storage->loadRevision($entity->getRevisionId());
      }
    }
    else {
      $entity_storage->resetCache([$entity->id()]);
      $the_revision = $entity_storage->load($entity->id());
    }
    return $the_revision;
  }

  protected function getClosestRevisionToTimestamp(ContentEntityInterface &$entity, $timestamp) {
    $entity_id = $entity->id();

    $query = \Drupal::database()->select($entity->getEntityType()->getRevisionDataTable(), 'nfr');
    $query->fields('nfr', [$entity->getEntityType()->getKey('revision')]);
    $query->addJoin('INNER', $entity->getEntityType()->getRevisionTable(), 'nr',
        'nfr.vid = nr.vid and nfr.nid = nr.nid and nfr.langcode = nr.langcode'
      );
    $query->condition('nfr.' . $entity->getEntityType()->getKey('id'), $entity_id);
    $query->condition('nfr.' . $entity->getEntityType()->getKey('langcode'), $entity->language()->getId());
    $query->condition('nr.revision_timestamp', $timestamp, '<');
    $query->orderBy('nfr.changed', 'DESC');
    $query->range(0, 1);

    $value = $query->execute();
    $vids = $value->fetchAssoc();
    return ($vids !== FALSE && count($vids) === 1) ? $vids['vid'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function saveTargetData(ContentEntityInterface &$entity, $langcode, $data) {
    // Without a defined langcode, we can't proceed
    if (!$langcode) {
      // TODO: log warning that downloaded translation's langcode is not enabled.
      return FALSE;
    }
    $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity->getEntityTypeId());

    try {
      // We need to load the revision that was uploaded for consistency. For that,
      // we check if we have a valid revision in the response, and if not, we
      // check the date of the uploaded document.

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $revision = (isset($data['_lingotek_metadata']) && isset($data['_lingotek_metadata']['_entity_revision'])) ? $data['_lingotek_metadata']['_entity_revision'] : NULL;
      $revision = $this->loadUploadedRevision($entity, $revision);

      // We should reload the last revision of the entity at all times.
      // This check here is only because of the case when we have asymmetric
      // paragraphs for translations, as in that case we get a duplicate that
      // still has not a valid entity id.
      // Also take into account that we may have just removed paragraph
      // translations form previous translation approaches, and in that case we
      // are forced to remove those, but there will be a mark of translation
      // changes.
      if ($entity->id() && !$entity->hasTranslationChanges()) {
        $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
      }

      // Initialize the translation on the Drupal side, if necessary.
      /** @var \Drupal\Core\Entity\ContentEntityInterface $translation */
      if (!$entity->hasTranslation($langcode)) {
        $entity->addTranslation($langcode, $revision->toArray());
      }
      $translation = $entity->getTranslation($langcode);

      foreach ($data as $name => $field_data) {
        if (strpos($name, '_') === 0) {
          // Skip special fields underscored.
          break;
        }
        $field_definition = $entity->getFieldDefinition($name);
        if ($field_definition && ($field_definition->isTranslatable() || $field_definition->getType() === 'cohesion_entity_reference_revisions' || $field_definition->getType() === 'entity_reference_revisions')
          && $this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $name)) {
          // First we check if this is a entity reference, and save the translated entity.
          $field_type = $field_definition->getType();
          if ($field_type === 'entity_reference' || $field_type === 'er_viewmode' || $field_type === 'bricks') {
            $target_entity_type_id = $field_definition->getFieldStorageDefinition()
              ->getSetting('target_type');
            $translation->{$name} = NULL;
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
                $embedded_entity_id = $revision->{$name}->get($index)
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
                    $this->saveTargetData($embedded_entity, $langcode, $field_item);
                  }
                  else {
                    \Drupal::logger('lingotek')->warning('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $name]);
                  }
                }
                elseif ($embedded_entity instanceof ConfigEntityInterface) {
                  $this->lingotekConfigTranslation->saveTargetData($embedded_entity, $langcode, $field_item);
                }
                // Now the embedded entity is saved, but we need to ensure
                // the reference will be saved too.
                $translation->{$name}->set($delta, $embedded_entity_id);
                $delta++;
              }
            }
          }
          // Paragraphs module use 'entity_reference_revisions'.
          elseif ($field_type === 'entity_reference_revisions') {
            $paragraphTranslatable = $field_definition->isTranslatable();
            $target_entity_type_id = $field_definition->getFieldStorageDefinition()
              ->getSetting('target_type');
            if ($paragraphTranslatable) {
              $translation->{$name} = NULL;
            }
            $delta = 0;
            $fieldValues = [];
            foreach ($field_data as $index => $field_item) {
              $embedded_entity_id = $revision->{$name}->get($index)
                ->get('target_id')
                ->getValue();
              /** @var \Drupal\Core\Entity\RevisionableInterface $embedded_entity */
              $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)
                ->load($embedded_entity_id);
              if ($embedded_entity !== NULL) {
                // If there is asymmetrical paragraphs enabled, we need a new one duplicated and stored.
                if ($paragraphTranslatable && \Drupal::moduleHandler()->moduleExists('paragraphs_asymmetric_translation_widgets')) {
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
                $this->saveTargetData($embedded_entity, $langcode, $field_item);
                // Now the embedded entity is saved, but we need to ensure
                // the reference will be saved too. Ensure it's the same revision.
                $fieldValues[$delta] = ['target_id' => $embedded_entity->id(), 'target_revision_id' => $embedded_entity->getRevisionId()];
                $delta++;
              }
            }
            // If the paragraph was not translatable, we avoid at all costs to modify the field,
            // as this will override the source and may have unintended consequences.
            if ($paragraphTranslatable) {
              $translation->{$name} = $fieldValues;
            }
          }
          elseif ($field_type === 'tablefield') {
            foreach ($field_data as $delta => $field_item_data) {
              $embedded_data = [];
              $caption = '';
              $table = [];
              foreach ($field_item_data as $row_index => $row) {
                if ($row_index === 'caption') {
                  $caption = $row;
                }
                else {
                  foreach ($row as $col_index => $cell) {
                    $table[intval(str_replace('row:', '', $row_index))][intval(str_replace('col:', '', $col_index))] = $cell;
                  }
                }
              }
              $translation->{$name}->set($delta, ['caption' => $caption, 'value' => $table]);
            }
          }
          // Cohesion layouts use 'cohesion_entity_reference_revisions'.
          elseif ($field_type === 'cohesion_entity_reference_revisions') {
            $cohesionLayoutTranslatable = $field_definition->isTranslatable();
            $target_entity_type_id = $field_definition->getFieldStorageDefinition()
              ->getSetting('target_type');
            if ($cohesionLayoutTranslatable) {
              $translation->{$name} = NULL;
            }
            $delta = 0;
            $fieldValues = [];
            foreach ($field_data as $index => $field_item) {
              $embedded_entity_id = $revision->{$name}->get($index)
                ->get('target_id')
                ->getValue();
              /** @var \Drupal\Core\Entity\RevisionableInterface $embedded_entity */
              $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)
                ->load($embedded_entity_id);
              if ($embedded_entity !== NULL) {
                $this->saveTargetData($embedded_entity, $langcode, $field_item);
                // Now the embedded entity is saved, but we need to ensure
                // the reference will be saved too. Ensure it's the same revision.
                $fieldValues[$delta] = ['target_id' => $embedded_entity->id(), 'target_revision_id' => $embedded_entity->getRevisionId()];
                $delta++;
              }
            }
            // If the cohesion layout was not translatable, we avoid at all costs to modify the field,
            // as this will override the source and may have unintended consequences.
            if ($cohesionLayoutTranslatable) {
              $translation->{$name} = $fieldValues;
            }
          }
          // If there is a path item, we need to handle it separately. See
          // https://www.drupal.org/node/2681241
          elseif ($field_type === 'path') {
            $pid = NULL;
            $source = '/' . $entity->toUrl()->getInternalPath();
            /** @var \Drupal\Core\Entity\EntityStorageInterface $aliasStorage */
            $alias_storage = $this->entityTypeManager->getStorage('path_alias');
            /** @var \Drupal\path_alias\PathAliasInterface[] $original_paths */
            $original_paths = $alias_storage->loadByProperties(['path' => $source, 'langcode' => $entity->getUntranslated()->language()->getId()]);
            $original_path = NULL;
            $alias = $field_data[0]['alias'];
            // Validate the alias before saving.
            if (!UrlHelper::isValid($alias)) {
              \Drupal::logger('lingotek')->warning('Alias for %type %label in language %langcode not saved, invalid uri "%uri"',
                ['%type' => $entity->getEntityTypeId(), '%label' => $entity->label(), '%langcode' => $langcode, '%uri' => $alias]);
              // Default to the original path.
              if (count($original_paths) > 0) {
                $original_path = reset($original_paths);
                $alias = $original_path->getAlias();
              }
              else {
                $alias = $source;
              }
              if (\Drupal::moduleHandler()->moduleExists('pathauto')) {
                $alias = '';
                $translation->get($name)->offsetGet(0)->set('alias', $alias);
                $translation->get($name)->offsetGet(0)->set('pathauto', TRUE);
              }
            }
            if ($alias !== NULL) {
              $translation->get($name)->offsetGet(0)->set('alias', $alias);
              if (\Drupal::moduleHandler()->moduleExists('pathauto') && !empty($alias) && $alias !== $original_path) {
                $translation->get($name)->offsetGet(0)->set('pathauto', FALSE);
              }
            }
          }
          elseif ($field_type === 'metatag') {
            $index = 0;
            foreach ($field_data as $field_item) {
              $metatag_value = serialize($field_item);
              $translation->{$name}->set($index, $metatag_value);
              ++$index;
            }
          }
          elseif ($field_type === 'string_long' && $field_definition->getName() === 'json_values' && $field_definition->getTargetEntityTypeId() === 'cohesion_layout') {
            $existingData = $revision->get($name)->offsetGet(0)->value;
            $layout_canvas = new LayoutCanvas($existingData);
            foreach ($layout_canvas->iterateCanvas() as $element) {
              if (!$element->isComponent() || !$component = Component::load($element->getComponentID())) {
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
            $translation->get($name)->offsetGet(0)->set('value', json_encode($layout_canvas));
          }
          elseif ($field_type === 'layout_section') {
            // TODO: Ensure we use LB_AT.
            $sourceSections = $revision->{$name};
            $translation->{$name} = NULL;
            // If we are embedding content blocks, we need to translate those too.
            // And we need to do that before saving the sections, as we need to
            // reference the latest revision.
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
                      $this->saveTargetData($embedded_entity, $langcode, $blockContentData);
                    }
                    else {
                      \Drupal::logger('lingotek')->warning('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $name]);
                    }
                  }
                }
              }
            }
            foreach ($sourceSections as $delta => &$field_item) {
              /** @var \Drupal\layout_builder\SectionComponent $sectionObject */
              $sectionObject = clone $field_item->section;
              $components = $sectionObject->getComponents();
              /** @var \Drupal\layout_builder\SectionComponent $component */
              foreach ($components as $componentUuid => &$component) {
                $config = $component->get('configuration');
                if (isset($field_data['components'][$componentUuid])) {
                  $componentData = $field_data['components'][$componentUuid];
                  foreach ($componentData as $componentDataKey => $componentDataValue) {
                    $componentDataKeyParts = explode('.', $componentDataKey);
                    NestedArray::setValue($config, $componentDataKeyParts, $componentDataValue);
                  }
                }
                // We need to reference the latest revision.
                if (isset($config['block_revision_id']) && strpos($config['id'], 'inline_block') === 0) {
                  $old_revision_id = $config['block_revision_id'];
                  $storage = $this->entityTypeManager->getStorage('block_content');
                  $bc = $storage->loadRevision($old_revision_id);
                  $latest = $storage->load($bc->id());
                  $rev = $latest->getRevisionId();
                  $config['block_revision_id'] = $rev;
                }
                $component->setConfiguration($config);
              }
              $translation->{$name}->set($delta, ['section' => $sectionObject]);
            }
          }
          elseif ($field_type === 'layout_translation') {
            $components = [];

            // We need the original layout, as the translation must store the
            // non-translatable properties too. So we need to copy them to the
            // translated field.
            $block_manager = \Drupal::service('plugin.manager.block');
            $layoutField = $entity->{OverridesSectionStorage::FIELD_NAME};
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
              $block_instance = $block_manager->createInstance($originalComponent->getPluginId(), $originalComponent->get('configuration'));
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
                      $this->saveTargetData($embedded_entity, $langcode, $blockContentData);
                    }
                    else {
                      \Drupal::logger('lingotek')->warning('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $name]);
                    }
                  }
                }
              }
            }
            $translation->{$name}->value = ['components' => $components];
          }
          elseif ($field_type === 'block_field') {
            $translation->{$name} = NULL;
            foreach ($field_data as $index => $field_item) {
              /** @var \Drupal\Core\Block\BlockPluginInterface $block */
              $block = $revision->get($name)->get($index)->getBlock();
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
                $translation->{$name}->set($index, [
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
                          $this->saveTargetData($embedded_entity, $langcode, $entityData);
                        }
                        else {
                          \Drupal::logger('lingotek')->warning('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $name]);
                        }
                      }
                    }
                  }
                }
              }
            }
          }
          else {
            // Initialize delta in case there are no items in $field_data.
            $delta = -1;
            // Save regular fields.
            foreach ($field_data as $delta => $delta_data) {
              foreach ($delta_data as $property => $property_data) {
                $property_definition = $storage_definitions[$name]->getPropertyDefinition($property);
                $data_type = $property_definition->getDataType();
                if ($data_type === 'uri') {
                  // Validate an uri.
                  if (!\Drupal::pathValidator()->isValid($property_data)) {
                    \Drupal::logger('lingotek')->warning('Field %field for %type %label in language %langcode not saved, invalid uri "%uri"',
                      ['%field' => $name, '%type' => $entity->getEntityTypeId(), '%label' => $entity->label(), '%langcode' => $langcode, '%uri' => $property_data]);
                    // Let's default to the original value given that there was a problem.
                    $property_data = $revision->get($name)->offsetGet($delta)->{$property};
                  }
                }
                if ($translation->get($name)->offsetExists($delta) && method_exists($translation->get($name)->offsetGet($delta), "set")) {
                  $translation->get($name)->offsetGet($delta)->set($property, html_entity_decode($property_data));
                }
                elseif ($translation->get($name)) {
                  $translation->get($name)->appendItem()->set($property, html_entity_decode($property_data));
                }
              }
            }

            // Remove the rest of deltas that were no longer found in the document downloaded from lingotek.
            $continue = TRUE;
            while ($continue) {
              if ($translation->get($name)->offsetExists($delta + 1)) {
                $translation->get($name)->removeItem($delta + 1);
              }
              else {
                $continue = FALSE;
              }
            }
          }
        }
      }

      // We need to set the content_translation source so the files are synced
      // properly. See https://www.drupal.org/node/2544696 for more information.
      $translation->set('content_translation_source', $entity->getUntranslated()->language()->getId());

      $entity->lingotek_processed = TRUE;
      // Allow other modules to alter the translation before is saved.
      \Drupal::moduleHandler()->invokeAll('lingotek_content_entity_translation_presave', [&$translation, $langcode, $data]);

      $status_field = $entity->getEntityType()->getKey('status');
      $status_field_definition = $entity->getFieldDefinition($status_field);
      if ($status_field_definition !== NULL && $status_field_definition->isTranslatable()) {
        $status_setting = $this->lingotekConfiguration->getPreference('target_download_status');
        if ($status_setting !== "same-as-source") {
          $status_value = ($status_setting === 'published') ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED;
          $translation->set($status_field, $status_value);
        }
      }

      // If there is any content moderation module is enabled, we may need to
      // perform a transition in their workflow.
      /** @var \Drupal\lingotek\Moderation\LingotekModerationFactoryInterface $moderation_factory */
      $moderation_factory = \Drupal::service('lingotek.moderation_factory');
      $moderation_handler = $moderation_factory->getModerationHandler();
      $moderation_handler->performModerationTransitionIfNeeded($translation);

      if ($moderation_handler->isModerationEnabled($translation) &&
          $translation->getEntityType()->isRevisionable()) {
        if ($bundle_entity_type = $entity->getEntityType()->getBundleEntityType()) {
          $bundle_entity = $this->entityTypeManager->getStorage($bundle_entity_type)->load($entity->bundle());
          if ($bundle_entity instanceof RevisionableEntityBundleInterface) {
            $translation->setNewRevision($bundle_entity->shouldCreateNewRevision());
          }
        }
        if ($translation instanceof RevisionLogInterface && $translation->isNewRevision()) {
          $requestTime = \Drupal::time()->getRequestTime();
          $translation->setRevisionUserId(\Drupal::currentUser()->id());
          $translation->setRevisionCreationTime($requestTime);
          $translation->setRevisionLogMessage((string) new FormattableMarkup('Document translated into @langcode by Lingotek.', ['@langcode' => strtoupper($langcode)]));
        }
      }
      $translation->save();

      return $entity;
    }
    catch (EntityStorageException $storage_exception) {
      $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
      throw new LingotekContentEntityStorageException($entity, $storage_exception, $storage_exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getJobId(ContentEntityInterface $entity) {
    $job_id = NULL;
    if (!empty($entity->get('lingotek_metadata')->target_id)) {
      /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
      $metadata = $entity->lingotek_metadata->entity;
      $job_id = $metadata->getJobId();
    }
    return $job_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setJobId(ContentEntityInterface $entity, $job_id, $update_tms = FALSE) {
    if (!$entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::loadByTargetId($entity->getEntityTypeId(), $entity->id());
    }
    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    $metadata = &$entity->lingotek_metadata->entity;
    $source_langcode = $entity->getUntranslated()->language()->getId();
    $source_locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);
    $newDocumentID = FALSE;
    if ($update_tms && $document_id = $this->getDocumentId($entity)) {
      try {
        $newDocumentID = $this->lingotek->updateDocument($document_id, NULL, NULL, NULL, NULL, $job_id, $source_locale);
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->setDocumentId($entity, $exception->getNewDocumentId());
        throw $exception;
      }
      catch (LingotekDocumentArchivedException $exception) {
        $old_job_id = $this->getJobId($entity);
        $this->setDocumentId($entity, NULL);
        $this->deleteMetadata($entity);
        $metadata = LingotekContentMetadata::create(['content_entity_type_id' => $entity->getEntityTypeId(), 'content_entity_id' => $entity->id()]);
        $metadata->setJobId($old_job_id);
        $metadata->save();
        throw $exception;
      }
      catch (LingotekPaymentRequiredException $exception) {
        throw $exception;
      }
      catch (LingotekApiException $exception) {
        throw $exception;
      }
    }
    if (is_string($newDocumentID)) {
      $metadata->setDocumentId($newDocumentID);
    }
    $metadata->setJobId($job_id);
    $metadata->save();
    return $entity;
  }

  /**
   * Embeds the metadata for being uploaded.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param $data
   *   The array of data.
   */
  protected function includeMetadata(ContentEntityInterface &$entity, &$data, $includeIntelligenceMetadata = TRUE) {
    $data['_lingotek_metadata']['_entity_type_id'] = $entity->getEntityTypeId();
    $data['_lingotek_metadata']['_entity_id'] = $entity->id();
    $data['_lingotek_metadata']['_entity_revision'] = $entity->getRevisionId();

    if ($includeIntelligenceMetadata) {
      /** @var \Drupal\lingotek\LingotekIntelligenceMetadataInterface $intelligenceService */
      $intelligenceService = \Drupal::service('lingotek.intelligence');

      if ($entity->id()) {
        if ($entity->lingotek_metadata && $entity->lingotek_metadata->entity) {
          $profile = $this->lingotekConfiguration->getEntityProfile($entity);
        }
        else {
          $profile = NULL;
        }

        $domain = \Drupal::request()->getSchemeAndHttpHost();

        $author_name = '';
        $author_email = '';
        if (method_exists($entity, 'getOwner')) {
          /** @var \Drupal\user\UserInterface $user */
          $user = $entity->getOwner();
          if ($user !== NULL && $user instanceof UserInterface) {
            $author_name = $user->getDisplayName();
            $author_email = $user->getEmail();
          }
        }

        $intelligenceService->setProfile($profile);

        $data['_lingotek_metadata']['_intelligence']['external_document_id'] = $entity->id();
        $data['_lingotek_metadata']['_intelligence']['content_type'] = $entity->getEntityTypeId() . ' - ' . $entity->bundle();

        // Check if we have permission to send these
        if ($intelligenceService->getBaseDomainPermission()) {
          $data['_lingotek_metadata']['_intelligence']['domain'] = $domain;
        }
        if ($intelligenceService->getReferenceUrlPermission()) {
          $data['_lingotek_metadata']['_intelligence']['reference_url'] = $entity->hasLinkTemplate('canonical') ? $entity->toUrl()
            ->setAbsolute(TRUE)
            ->toString() : NULL;
        }
        if ($intelligenceService->getAuthorPermission()) {
          $data['_lingotek_metadata']['_intelligence']['author_name'] = $author_name;
        }
        if ($intelligenceService->getAuthorPermission() && $intelligenceService->getAuthorEmailPermission() && $intelligenceService->getContactEmailForAuthorPermission() && $intelligenceService->getContactEmailPermission()) {
          $data['_lingotek_metadata']['_intelligence']['author_email'] = $intelligenceService->getContactEmail();
        }
        if ($intelligenceService->getAuthorPermission() && $intelligenceService->getAuthorEmailPermission() && (!$intelligenceService->getContactEmailForAuthorPermission() || !$intelligenceService->getContactEmailPermission())) {
          $data['_lingotek_metadata']['_intelligence']['author_email'] = $author_email;
        }
        if ($intelligenceService->getBusinessUnitPermission()) {
          $data['_lingotek_metadata']['_intelligence']['business_unit'] = $intelligenceService->getBusinessUnit();
        }
        if ($intelligenceService->getBusinessDivisionPermission()) {
          $data['_lingotek_metadata']['_intelligence']['business_division'] = $intelligenceService->getBusinessDivision();
        }
        if ($intelligenceService->getCampaignIdPermission()) {
          $data['_lingotek_metadata']['_intelligence']['campaign_id'] = $intelligenceService->getCampaignId();
        }
        if ($intelligenceService->getCampaignRatingPermission()) {
          $data['_lingotek_metadata']['_intelligence']['campaign_rating'] = $intelligenceService->getCampaignRating();
        }
        if ($intelligenceService->getChannelPermission()) {
          $data['_lingotek_metadata']['_intelligence']['channel'] = $intelligenceService->getChannel();
        }
        if ($intelligenceService->getContactNamePermission()) {
          $data['_lingotek_metadata']['_intelligence']['contact_name'] = $intelligenceService->getContactName();
        }
        if ($intelligenceService->getContactEmailPermission()) {
          $data['_lingotek_metadata']['_intelligence']['contact_email'] = $intelligenceService->getContactEmail();
        }
        if ($intelligenceService->getContentDescriptionPermission()) {
          $data['_lingotek_metadata']['_intelligence']['content_description'] = $intelligenceService->getContentDescription();
        }
        if ($intelligenceService->getExternalStyleIdPermission()) {
          $data['_lingotek_metadata']['_intelligence']['external_style_id'] = $intelligenceService->getExternalStyleId();
        }
        if ($intelligenceService->getPurchaseOrderPermission()) {
          $data['_lingotek_metadata']['_intelligence']['purchase_order'] = $intelligenceService->getPurchaseOrder();
        }
        if ($intelligenceService->getRegionPermission()) {
          $data['_lingotek_metadata']['_intelligence']['region'] = $intelligenceService->getRegion();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setLastUploaded(ContentEntityInterface $entity, int $timestamp) {
    if (!$entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::loadByTargetId($entity->getEntityTypeId(), $entity->id());
    }
    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    $metadata = &$entity->lingotek_metadata->entity;
    $metadata->setLastUploaded($timestamp)->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastUpdated(ContentEntityInterface $entity, int $timestamp) {
    if (!$entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::loadByTargetId($entity->getEntityTypeId(), $entity->id());
    }
    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    $metadata = &$entity->lingotek_metadata->entity;
    $metadata->setLastUpdated($timestamp)->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastUploaded(ContentEntityInterface $entity) {
    if (!$entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::loadByTargetId($entity->getEntityTypeId(), $entity->id());
    }
    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    $metadata = $entity->lingotek_metadata->entity;
    return $metadata->getLastUploaded();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastUpdated(ContentEntityInterface $entity) {
    if (!$entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::loadByTargetId($entity->getEntityTypeId(), $entity->id());
    }

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    $metadata = $entity->lingotek_metadata->entity;
    return $metadata->getLastUpdated();
  }

}
