<?php

namespace Drupal\lingotek;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Exception\LingotekDocumentAlreadyCompletedException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;
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
      try {
        $status = $this->lingotek->getDocumentStatus($document_id);
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->setDocumentId($entity, $exception->getNewDocumentId());
        throw $exception;
      }
      catch (LingotekDocumentNotFoundException $exception) {
        if ($this->checkUploadProcessId($entity)) {
          // If the document was not found and the process completed means that
          // the document might have been deleted afterwards.
          // We check for timeout to set an error if needed.
          if (!$this->checkForTimeout($entity)) {
            $this->setDocumentId($entity, NULL);
            $this->deleteMetadata($entity);
            throw $exception;
          }
        }
        else {
          // The document is not ready yet, but the check operation is still in
          // progress.
          $this->checkForTimeout($entity);
          return FALSE;
        }
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

      if ($status) {
        $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        return TRUE;
      }
      else {
        if (!$this->checkUploadProcessId($entity)) {
          // I'm not sure if this is relevant anymore, as the timeout will
          // probably trigger a 404. We leave it just in case.
          $this->checkForTimeout($entity);
          return FALSE;
        }
        else {
          // The document is not ready yet, but the check operation succeeded.
          return FALSE;
        }
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
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   */
  protected function checkForTimeout(ContentEntityInterface &$entity) {
    // We set a max time of 1 hour for the import (in seconds)
    $maxImportTime = 3600;
    $timedOut = FALSE;
    if ($last_uploaded_time = $this->getLastUpdated($entity) ?: $this->getLastUploaded($entity)) {
      // If document has not successfully imported after MAX_IMPORT_TIME
      // then move to ERROR state.
      if (\Drupal::time()->getRequestTime() - $last_uploaded_time > $maxImportTime) {
        $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
        $timedOut = TRUE;
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
        $timedOut = TRUE;
        $this->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      }
    }
    return $timedOut;
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
    if (in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_ERROR])) {
      $this->clearUploadProcessId($entity);
    }
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
    try {
      $translation_statuses = $this->lingotek->getDocumentTranslationStatuses($document_id);
    }
    catch (LingotekDocumentNotFoundException $exception) {
      $this->setDocumentId($entity, NULL);
      $this->deleteMetadata($entity);
      throw $exception;
    }
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
        try {
          $translation_status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        }
        catch (LingotekDocumentNotFoundException $exception) {
          $this->setDocumentId($entity, NULL);
          $this->deleteMetadata($entity);
          throw $exception;
        }
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
        try {
          $translation_status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        }
        catch (LingotekDocumentNotFoundException $exception) {
          $this->setDocumentId($entity, NULL);
          $this->deleteMetadata($entity);
          throw $exception;
        }
        if ($translation_status === TRUE) {
          $current_status = Lingotek::STATUS_READY;
          $this->setTargetStatus($entity, $langcode, $current_status);
        }
        elseif ($translation_status !== FALSE) {
          $current_status = Lingotek::STATUS_PENDING;
          $this->setTargetStatus($entity, $langcode, $current_status);
        }
        // elseif ($this->lingotek->downloadDocument($document_id, $locale)) {
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
        if (in_array($status, [Lingotek::STATUS_ARCHIVED, Lingotek::STATUS_DELETED, Lingotek::STATUS_CANCELLED, Lingotek::STATUS_DISABLED])) {
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
    $source_entity = NULL;
    if ($entity instanceof RevisionableInterface) {
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      if ($revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $entity->getUntranslated()->language()->getId())) {
        $source_entity = $storage->loadRevision($revision_id);
      }
      else {
        $source_entity = $entity->getUntranslated();
      }
    }
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
    foreach ($translatable_fields as $field_name => $definition) {
      /** @var \Drupal\lingotek\FieldProcessor\LingotekFieldProcessorManager $field_processor_manager */
      $field_processor_manager = \Drupal::service('plugin.manager.lingotek_field_processor');
      /** @var \Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface[] $field_processors */
      $field_processors = $field_processor_manager->getProcessorsForField($field_definitions[$field_name], $source_entity);
      foreach ($field_processors as $field_processor) {
        $field_processor->extract($source_entity, $field_name, $field_definitions[$field_name], $data, $visited);
      }
    }

    // Embed entity metadata. We need to exclude intelligence metadata if it is
    // a child entity.
    $this->includeMetadata($source_entity, $data, $isParentEntity);

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
        Lingotek::STATUS_DELETED,
      ];

      if (in_array($current_status, $pristine_statuses)) {
        try {
          $result = $this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getEntityProfile($entity));
        }
        catch (LingotekDocumentLockedException $exception) {
          $this->setDocumentId($entity, $exception->getNewDocumentId());
          throw $exception;
        }
        catch (LingotekDocumentNotFoundException $exception) {
          $this->setDocumentId($entity, NULL);
          $this->deleteMetadata($entity);
          throw $exception;
        }
        catch (LingotekDocumentArchivedException $exception) {
          $this->setDocumentId($entity, NULL);
          $this->setSourceStatus($entity, Lingotek::STATUS_ARCHIVED);
          $this->setTargetStatuses($entity, Lingotek::STATUS_ARCHIVED);
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
              catch (LingotekDocumentNotFoundException $exception) {
                $this->setDocumentId($entity, NULL);
                $this->deleteMetadata($entity);
                throw $exception;
              }
              catch (LingotekDocumentArchivedException $exception) {
                $this->setDocumentId($entity, NULL);
                $this->setSourceStatus($entity, Lingotek::STATUS_ARCHIVED);
                $this->setTargetStatuses($entity, Lingotek::STATUS_ARCHIVED);
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

    $process_id = NULL;
    try {
      $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getSourceLocale($entity), $url, $profile, $job_id, $process_id);
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
      if ($process_id !== NULL) {
        $this->storeUploadProcessId($entity, $process_id);
      }
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
      $target_status = $this->getTargetStatus($entity, $langcode);
      $data = [];
      try {
        if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) !== FALSE || $target_status === Lingotek::STATUS_INTERMEDIATE) {
          $data = $this->lingotek->downloadDocument($document_id, $locale);
        }
        else {
          \Drupal::logger('lingotek')->warning('Avoided download for (%entity_id,%revision_id): Source status is %source_status, target %target_langcode is %target_status.', [
            '%entity_id' => $entity->id(),
            '%revision_id' => $entity->getRevisionId(),
            '%source_status' => $source_status,
            '%target_langcode' => $langcode,
            '%target_status' => $target_status,
          ]);
          return NULL;
        }
      }
      catch (LingotekDocumentNotFoundException $exception) {
        $this->setDocumentId($entity, NULL);
        $this->deleteMetadata($entity);
        throw $exception;
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

    $process_id = NULL;
    try {
      $newDocumentID = $this->lingotek->updateDocument($document_id, $source_data, $url, $document_name, $profile, $job_id, $source_locale, $process_id);
    }
    catch (LingotekDocumentNotFoundException $exception) {
      $this->setDocumentId($entity, NULL);
      $this->deleteMetadata($entity);
      throw $exception;
    }
    catch (LingotekDocumentLockedException $exception) {
      $this->setDocumentId($entity, $exception->getNewDocumentId());
      throw $exception;
    }
    catch (LingotekDocumentArchivedException $exception) {
      $this->setDocumentId($entity, NULL);
      $this->setSourceStatus($entity, Lingotek::STATUS_ARCHIVED);
      $this->setTargetStatuses($entity, Lingotek::STATUS_ARCHIVED);
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
      if ($process_id !== NULL) {
        $this->storeUploadProcessId($entity, $process_id);
      }
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
                catch (LingotekDocumentNotFoundException $exception) {
                  $this->setDocumentId($entity, NULL);
                  $this->deleteMetadata($entity);
                  throw $exception;
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
          catch (LingotekDocumentNotFoundException $exception) {
            $this->setDocumentId($entity, NULL);
            $this->deleteMetadata($entity);
            throw $exception;
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
      try {
        $result = $this->lingotek->cancelDocument($doc_id);
        $this->lingotekConfiguration->setProfile($entity, NULL);
        $this->setDocumentId($entity, NULL);
      }
      catch (LingotekDocumentAlreadyCompletedException $exception) {
        \Drupal::logger('lingotek')
          ->warning('The document %label (%doc_id) was not cancelled on the TMS side as it was already completed.', [
            '%label' => $entity->label(),
            '%doc_id' => $doc_id,
          ]);
        $this->lingotekConfiguration->setProfile($entity, NULL);
        $this->setDocumentId($entity, NULL);
      }
      catch (LingotekDocumentNotFoundException $exception) {
        $this->setDocumentId($entity, NULL);
        $this->deleteMetadata($entity);
        throw $exception;
      }
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

      try {
        if ($this->lingotek->cancelDocumentTarget($document_id, $locale)) {
          $this->setTargetStatus($entity, $drupal_language->id(), Lingotek::STATUS_CANCELLED);
          return TRUE;
        }
      }
      catch (LingotekDocumentNotFoundException $exception) {
        $this->setDocumentId($entity, NULL);
        $this->deleteMetadata($entity);
        throw $exception;
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

      foreach ($data as $field_name => $field_data) {
        if (strpos($field_name, '_') === 0) {
          // Skip special fields underscored.
          break;
        }
        $field_definition = $entity->getFieldDefinition($field_name);
        if ($field_definition && ($field_definition->isTranslatable() || $field_definition->getType() === 'cohesion_entity_reference_revisions' || $field_definition->getType() === 'entity_reference_revisions')
          && $this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $field_name)) {

          /** @var \Drupal\lingotek\FieldProcessor\LingotekFieldProcessorManager $field_processor_manager */
          $field_processor_manager = \Drupal::service('plugin.manager.lingotek_field_processor');
          /** @var \Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface[] $field_processors */
          $field_processors = $field_processor_manager->getProcessorsForField($field_definition, $revision);
          // For persisting, only one processor can apply for avoiding conflicts.
          if (count($field_processors) > 0) {
            $field_processors = array_reverse($field_processors);
            $field_processor = reset($field_processors);
            $field_processor->store($translation, $langcode, $revision, $field_name, $field_definition, $data[$field_name]);
          }
          else {
            \Drupal::logger('lingotek')->error('Error persisting %entity_type_id %entity_id (%label) translation to %langcode because there were no processors for field %field_name', [
              '%entity_type_id' => $revision->getEntityTypeId(),
              '%entity_id' => $revision->id(),
              '%label' => $revision->label(),
              '%langcode' => $langcode,
              '%field_name' => $field_name,
            ]);
          }
        }
      }

      // We need to set the content_translation source so the files are synced
      // properly. See https://www.drupal.org/node/2544696 for more information.
      $translation->set('content_translation_source', $entity->getUntranslated()->language()->getId());

      $entity->lingotek_processed = TRUE;
      // Allow other modules to alter the translation before is saved.
      \Drupal::moduleHandler()->invokeAll('lingotek_content_entity_translation_presave', [&$translation, $langcode, $data]);

      $published_field = $entity->getEntityType()->getKey('published');
      $published_field_definition = $entity->getFieldDefinition($published_field);
      if ($published_field_definition !== NULL && $published_field_definition->isTranslatable()) {
        $published_setting = $this->lingotekConfiguration->getPreference('target_download_status');
        if ($published_setting !== "same-as-source") {
          $published_value = ($published_setting === 'published') ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED;
          $translation->set($published_field, $published_value);
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
      catch (LingotekDocumentNotFoundException $exception) {
        $metadata->setDocumentId(NULL);
        $metadata->translation_status = [];
        $metadata->setJobId($job_id);
        $metadata->save();
        throw $exception;
      }
      catch (LingotekDocumentArchivedException $exception) {
        $old_job_id = $this->getJobId($entity);
        $this->setDocumentId($entity, NULL);
        $this->setSourceStatus($entity, Lingotek::STATUS_ARCHIVED);
        $this->setTargetStatuses($entity, Lingotek::STATUS_ARCHIVED);
        $this->setJobId($entity, $old_job_id);
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

  /**
   * Stores the upload process id.
   *
   * In case of 404, we need to know if there was an error, or it's just still
   * importing.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   * @param string $process_id
   *   The process ID in the TMS.
   */
  protected function storeUploadProcessId(ContentEntityInterface $entity, $process_id) {
    $state = \Drupal::state();
    $stored_process_ids = $state->get('lingotek_import_process_ids', []);
    $parents = [
      $entity->getEntityTypeId(),
      $entity->id(),
    ];
    NestedArray::setValue($stored_process_ids, $parents, $process_id);
    $state->set('lingotek_import_process_ids', $stored_process_ids);
  }

  /**
   * Gets the upload process id.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   */
  protected function getUploadProcessId(ContentEntityInterface $entity) {
    $state = \Drupal::state();
    $stored_process_ids = $state->get('lingotek_import_process_ids', []);
    $parents = [
      $entity->getEntityTypeId(),
      $entity->id(),
    ];
    return NestedArray::getValue($stored_process_ids, $parents);
  }

  /**
   * Checks the upload process id.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   *
   * @return bool
   *   If the process is completed or in progress, returns TRUE. If it failed,
   *   returns FALSE.
   */
  protected function checkUploadProcessId(ContentEntityInterface $entity) {
    $process_id = $this->getUploadProcessId($entity);
    return ($this->lingotek->getProcessStatus($process_id) !== FALSE);
  }

  /**
   * Clears the upload process id.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   */
  protected function clearUploadProcessId(ContentEntityInterface $entity) {
    $state = \Drupal::state();
    $stored_process_ids = $state->get('lingotek_import_process_ids', []);
    $parents = [
      $entity->getEntityTypeId(),
      $entity->id(),
    ];
    NestedArray::unsetValue($stored_process_ids, $parents);
    $state->set('lingotek_import_process_ids', $stored_process_ids);
  }

}
