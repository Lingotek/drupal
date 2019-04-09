<?php

namespace Drupal\lingotek;

use Drupal\Component\Utility\SortArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
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
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

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
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   An entity manager object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekConfigTranslationServiceInterface $lingotek_config_translation, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager) {
    $this->lingotek = $lingotek;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekConfigTranslation = $lingotek_config_translation;
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function checkSourceStatus(ContentEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $document_id = $this->getDocumentId($entity);
    // We set a max time of 1 hour for the import (in seconds)
    $maxImportTime = 3600;
    $source_status = $this->getSourceStatus($entity);
    if ($document_id) {
      // Document has successfully imported.
      if ($this->lingotek->getDocumentStatus($document_id)) {
        $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        return TRUE;
      }
      else {
        // TODO: change to actual last_uploaded timestamp rather than surrogate.
        if ($entity->getEntityType()->isSubclassOf(EntityChangedInterface::class)) {
          $last_uploaded_time = $entity->getChangedTime();
          // If document has not successfully imported after MAX_IMPORT_TIME
          // then move to ERROR state.
          if (REQUEST_TIME - $last_uploaded_time > $maxImportTime) {
            $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
          }
          else {
            // Document still may be importing and the MAX import time didn't
            // complete yet, so we do nothing.
          }
        }
        return FALSE;
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
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
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
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
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
      if (in_array($current_target_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_DISABLED, Lingotek::STATUS_EDITED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_NONE, Lingotek::STATUS_READY, Lingotek::STATUS_PENDING, NULL])) {
        if ($progress === Lingotek::PROGRESS_COMPLETE) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_READY);
        }
        else {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_PENDING);
        }
      }
      if ($source_status !== Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_EDITED && $langcode !== $entity->getUntranslated()->language()->getId()) {
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
      }
      if ($source_status === Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_CURRENT && $langcode !== $entity->getUntranslated()->language()->getId()) {
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
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
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
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
        if ($translation_status === TRUE) {
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
    if ($entity->lingotek_metadata === NULL) {
      $entity->lingotek_metadata = LingotekContentMetadata::create();
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
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
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
      $entity->lingotek_metadata->entity = LingotekContentMetadata::create();
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
    $field_definitions = $this->entityManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $storage_definitions = $entity_type instanceof ContentEntityTypeInterface ? $this->entityManager->getFieldStorageDefinitions($entity_type->id()) : [];
    $translatable_fields = [];
    // We need to include computed fields, as we may have a URL alias.
    foreach ($entity->getFields(TRUE) as $field_name => $definition) {
      if ($this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $field_name)
        && $field_name != $entity_type->getKey('langcode')
        && $field_name != $entity_type->getKey('default_langcode')) {
        $translatable_fields[$field_name] = $definition;
      }
    }
    $default_display = entity_get_display($entity->getEntityTypeId(), $entity->bundle(), 'default');
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
      // If we have an entity reference, we may want to embed it.
      if ($field_type === 'entity_reference' || $field_type === 'er_viewmode' || $field_type === 'bricks') {
        $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()->getSetting('target_type');
        foreach ($entity->{$k} as $field_item) {
          $embedded_entity_id = $field_item->get('target_id')->getValue();
          $embedded_entity = $this->entityManager->getStorage($target_entity_type_id)->load($embedded_entity_id);
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
      elseif ($field_type === 'entity_reference_revisions') {
        $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()->getSetting('target_type');
        foreach ($entity->{$k} as $field_item) {
          $embedded_entity_id = $field_item->get('target_id')->getValue();
          $embedded_entity_revision_id = $field_item->get('target_revision_id')->getValue();
          $embedded_entity = $this->entityManager->getStorage($target_entity_type_id)->loadRevision($embedded_entity_revision_id);
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
          $path = \Drupal::service('path.alias_storage')->load(['source' => $source, 'langcode' => $entity->language()->getId()]);
          $alias = $path['alias'];
          if ($alias !== NULL) {
            $data[$k][0]['alias'] = $alias;
          }
        }
      }
    }
    // Embed entity metadata. We need to exclude intelligence metadata if it is
    // a child entity.
    $this->includeMetadata($entity, $data, $isParentEntity);

    return $data;
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
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $source_langcode = $entity->getUntranslated()->language()->getId();
    $source_locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);

    if ($locale == $source_locale) {
      // We don't want to translate from one language to itself.
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
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
        if ($this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getEntityProfile($entity))) {
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
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
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
          $source_status = $this->getSourceStatus($entity);
          $current_status = $this->getTargetStatus($entity, $langcode);
          if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT && $current_status !== Lingotek::STATUS_EDITED  && $current_status !== Lingotek::STATUS_READY) {
            if ($this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getEntityProfile($entity))) {
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
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($entity);
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
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
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
          return NULL;
        }
      }
      catch (LingotekApiException $exception) {
        // TODO: log issue
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
        throw $exception;
        return FALSE;
      }

      if ($data) {
        // Check the real status, because it may still need review or anything.
        $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        $transaction = db_transaction();
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
          throw $storageException;
        }
        catch (\Exception $exception) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
          $transaction->rollback();
          return FALSE;
        }
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
  public function updateDocument(ContentEntityInterface &$entity, $job_id = NULL) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($entity);
    }
    $source_data = $this->getSourceData($entity);
    $document_id = $this->getDocumentId($entity);
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

    $result = FALSE;
    try {
      $result = $this->lingotek->updateDocument($document_id, $source_data, $url, $document_name, $profile, $job_id);
    }
    catch (LingotekApiException $exception) {
      $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      throw $exception;
    }

    if ($result) {
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_PENDING);
      $this->setJobId($entity, $job_id);
      return $document_id;
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  public function downloadDocuments(ContentEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
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
                $transaction = db_transaction();
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
                  $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
                  $transaction->rollback();
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

  public function downloadDocumentContent($document_id) {
    try {
      $data = $this->lingotek->downloadDocumentContent($document_id);
    }
    catch (LingotekApiException $exception) {
      // TODO: log issue
      return FALSE;
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDocument(ContentEntityInterface &$entity) {
    return $this->lingotek->deleteDocument($this->getDocumentId($entity));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMetadata(ContentEntityInterface &$entity) {
    if ($this->lingotekConfiguration->mustDeleteRemoteAfterDisassociation()) {
      $this->deleteDocument($entity);
    }

    $doc_id = $this->getDocumentId($entity);
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
      $entity = $this->entityManager->getStorage($metadata->getContentEntityTypeId())->load($metadata->getContentEntityId());
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
    $type = $entity->getEntityTypeId();

    if ($entity_type->isRevisionable()) {
      // If the entity type is revisionable, we need to check the proper revision.
      // This may come from the uploaded data, but in case we didn't have it, we
      // have to infer using the timestamp.
      if ($revision !== NULL) {
        $the_revision = entity_revision_load($type, $revision);
      }
      elseif ($revision === NULL && $entity->hasField('revision_timestamp')) {
        // Let's find the better revision based on the timestamp.
        $timestamp = $this->lingotek->getUploadedTimestamp($this->getDocumentId($entity));
        $revision = $this->getClosestRevisionToTimestamp($entity, $timestamp);
        if ($revision !== NULL) {
          $the_revision = entity_revision_load($type, $revision);
        }
      }
      if ($the_revision === NULL) {
        // We didn't find a better option, but let's reload this one so it's not
        // cached.
        $the_revision = entity_revision_load($type, $entity->getRevisionId());
      }
    }
    else {
      $the_revision = entity_load($type, $entity->id(), TRUE);
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
    $storage_definitions = $this->entityManager->getFieldStorageDefinitions($entity->getEntityTypeId());

    try {
      // We need to load the revision that was uploaded for consistency. For that,
      // we check if we have a valid revision in the response, and if not, we
      // check the date of the uploaded document.

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $revision = (isset($data['_lingotek_metadata']) && isset($data['_lingotek_metadata']['_entity_revision'])) ? $data['_lingotek_metadata']['_entity_revision'] : NULL;
      $revision = $this->loadUploadedRevision($entity, $revision);

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
        if ($field_definition && ($field_definition->isTranslatable() || $field_definition->getType() === 'entity_reference_revisions')
          && $this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $name)) {
          // First we check if this is a entity reference, and save the translated entity.
          $field_type = $field_definition->getType();
          if ($field_type === 'entity_reference' || $field_type === 'er_viewmode' || $field_type === 'bricks') {
            $target_entity_type_id = $field_definition->getFieldStorageDefinition()
              ->getSetting('target_type');
            $index = -1;
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
              $embedded_entity = $this->entityManager->getStorage($target_entity_type_id)
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
                    \Drupal::logger('lingotek')->warning($this->t('Field %field not saved as its referenced entity is not translatable by Lingotek', ['%field' => $name]));
                  }
                }
                elseif ($embedded_entity instanceof ConfigEntityInterface) {
                  $this->lingotekConfigTranslation->saveTargetData($embedded_entity, $langcode, $field_item);
                }
                // Now the embedded entity is saved, but we need to ensure
                // the reference will be saved too.
                $translation->{$name}->set($index, $embedded_entity_id);
              }
            }
            if ($index === -1) {
              // Remove the rest of deltas that were no longer found in the document downloaded from lingotek.
              ++$index;
              $continue = TRUE;
              while ($continue) {
                if ($translation->get($name)->offsetExists($index)) {
                  $translation->get($name)->removeItem($index);
                }
                else {
                  $continue = FALSE;
                }
              }
            }
          }
          // Paragraphs module use 'entity_reference_revisions'.
          elseif ($field_type === 'entity_reference_revisions') {
            $target_entity_type_id = $field_definition->getFieldStorageDefinition()
              ->getSetting('target_type');
            $index = -1;
            foreach ($field_data as $index => $field_item) {
              $embedded_entity_id = $revision->{$name}->get($index)
                ->get('target_id')
                ->getValue();
              /** @var \Drupal\Core\Entity\RevisionableInterface $embedded_entity */
              $embedded_entity = $this->entityManager->getStorage($target_entity_type_id)
                ->load($embedded_entity_id);
              if ($embedded_entity !== NULL) {
                $this->saveTargetData($embedded_entity, $langcode, $field_item);
                // Now the embedded entity is saved, but we need to ensure
                // the reference will be saved too. Ensure it's the same revision.
                $translation->{$name}->set($index, ['target_id' => $embedded_entity_id, 'target_revision_id' => $embedded_entity->getRevisionId()]);
              }
            }
            if ($index === -1) {
              // Remove the rest of deltas that were no longer found in the document downloaded from lingotek.
              ++$index;
              $continue = TRUE;
              while ($continue) {
                if ($translation->get($name)->offsetExists($index)) {
                  $translation->get($name)->removeItem($index);
                }
                else {
                  $continue = FALSE;
                }
              }
            }
          }
          // If there is a path item, we need to handle it separately. See
          // https://www.drupal.org/node/2681241
          elseif ($field_type === 'path') {
            $pid = NULL;
            $source = '/' . $entity->toUrl()->getInternalPath();
            /** @var \Drupal\Core\Path\AliasStorageInterface $aliasStorage */
            $alias_storage = \Drupal::service('path.alias_storage');
            $path = $alias_storage->load(['source' => $source, 'langcode' => $langcode]);
            $original_path = $alias_storage->load(['source' => $source, 'langcode' => $entity->getUntranslated()->language()->getId()]);
            if ($path) {
              $pid = $path['pid'];
            }
            $alias = $field_data[0]['alias'];
            // Validate the alias before saving.
            if (!UrlHelper::isValid($alias)) {
              \Drupal::logger('lingotek')->warning($this->t('Alias for %type %label in language %langcode not saved, invalid uri "%uri"',
                ['%type' => $entity->getEntityTypeId(), '%label' => $entity->label(), '%langcode' => $langcode, '%uri' => $alias]));
              // Default to the original path.
              $alias = $original_path ? $original_path['alias'] : $source;
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
                    \Drupal::logger('lingotek')->warning($this->t('Field %field for %type %label in language %langcode not saved, invalid uri "%uri"',
                      ['%field' => $name, '%type' => $entity->getEntityTypeId(), '%label' => $entity->label(), '%langcode' => $langcode, '%uri' => $property_data]));
                    // Let's default to the original value given that there was a problem.
                    $property_data = $revision->get($name)->offsetGet($delta)->{$property};
                  }
                }
                if (method_exists($translation->get($name)->offsetGet($delta), "set")) {
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
          $status_value = ($status_setting === 'published') ? NODE_PUBLISHED : NODE_NOT_PUBLISHED;
          $translation->set($status_field, $status_value);
        }
      }

      // If there is any content moderation module is enabled, we may need to
      // perform a transition in their workflow.
      /** @var \Drupal\lingotek\Moderation\LingotekModerationFactoryInterface $moderation_factory */
      $moderation_factory = \Drupal::service('lingotek.moderation_factory');
      $moderation_handler = $moderation_factory->getModerationHandler();
      $moderation_handler->performModerationTransitionIfNeeded($translation);

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
    if ($entity->lingotek_metadata === NULL) {
      $entity->lingotek_metadata = LingotekContentMetadata::create();
    }
    $metadata = &$entity->lingotek_metadata->entity;

    if ($update_tms && $document_id = $this->getDocumentId($entity)) {
      $this->lingotek->updateDocument($document_id, NULL, NULL, NULL, NULL, $job_id);
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

}
