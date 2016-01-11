<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekContentTranslationService.
 */

namespace Drupal\lingotek;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Service for managing Lingotek content translations.
 */
class LingotekContentTranslationService implements LingotekContentTranslationServiceInterface {

  /**
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
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  protected $languageManager;

  /**
   * Constructs a new LingotekContentTranslationService object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   An lingotek object.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *  The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   An entity manager object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager) {
    $this->lingotek = $lingotek;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function checkSourceStatus(ContentEntityInterface &$entity) {
    $document_id = $this->getDocumentId($entity);
    if ($document_id && $this->lingotek->documentImported($document_id)) {
      $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceStatus(ContentEntityInterface &$entity) {
    $source_language = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    if ($entity->lingotek_translation_source && $entity->lingotek_translation_source->value !== NULL) {
      $source_language = $entity->lingotek_translation_source->value;
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
    $source_language = $entity->lingotek_translation_source->value;
    if ($source_language == LanguageInterface::LANGCODE_NOT_SPECIFIED || $source_language == NULL) {
      $source_language = $entity->getUntranslated()->language()->getId();
    }
    return $this->setTargetStatus($entity, $source_language, $status);
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatuses(ContentEntityInterface &$entity) {
    foreach ($entity->lingotek_translation_status->getIterator() as $delta => $value) {
      $language = $value->language;
      $current_status = $value->value;
      if (($current_status == Lingotek::STATUS_PENDING) && $this->lingotek->getDocumentStatus($this->getDocumentId($entity))) {
        $current_status = Lingotek::STATUS_READY;
        $this->setTargetStatus($entity, $language, $current_status);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatus(ContentEntityInterface &$entity, $langcode) {
    $current_status = $this->getTargetStatus($entity, $langcode);
    if (($current_status == Lingotek::STATUS_PENDING || $current_status == Lingotek::STATUS_EDITED) && $this->lingotek->getDocumentStatus($this->getDocumentId($entity))) {
      $current_status = Lingotek::STATUS_READY;
      $this->setTargetStatus($entity, $langcode, $current_status);
    }
    return $current_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatus(ContentEntityInterface &$entity, $langcode) {
    $status = Lingotek::STATUS_UNTRACKED;

    if (count($entity->lingotek_translation_status) > 0) {
      foreach ($entity->lingotek_translation_status->getIterator() as $delta => $value) {
        if ($value->language == $langcode) {
          $status = $value->value;
        }
      }
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatus(ContentEntityInterface &$entity, $langcode, $status, $save = TRUE) {
    $set = FALSE;
    if ($entity->hasField('lingotek_translation_status') && count($entity->lingotek_translation_status) > 0) {
      foreach ($entity->lingotek_translation_status->getIterator() as $delta => $value) {
        if ($value->language == $langcode) {
          $value->value = $status;
          $set = true;
        }
      }
    }
    if (!$set && $entity->hasField('lingotek_translation_status')) {
      $entity->lingotek_translation_status->appendItem(['language' => $langcode, 'value' => $status]);
      $set = TRUE;
    }
    if ($set && $save) {
      $entity->save();
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatuses(ContentEntityInterface &$entity, $status) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->getUntranslated()->language()->getId();

    foreach($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getTargetStatus($entity, $langcode)) {
        if ($current_status != Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_CURRENT) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
        elseif ($current_status == Lingotek::STATUS_EDITED && $status == Lingotek::STATUS_CURRENT) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function markTranslationsAsDirty(ContentEntityInterface &$entity) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->getUntranslated()->language()->getId();

    foreach($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getTargetStatus($entity, $langcode)) {
        if ($current_status == Lingotek::STATUS_CURRENT) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentId(ContentEntityInterface &$entity) {
    $doc_id = NULL;
    if ($entity->lingotek_document_id) {
      $doc_id = $entity->lingotek_document_id->value;
    }
    return $doc_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setDocumentId(ContentEntityInterface &$entity, $doc_id) {
    $entity->lingotek_document_id = $doc_id;
    $entity->save();

    \Drupal::database()->insert('lingotek_content_metadata')
      ->fields(['document_id', 'entity_type', 'entity_id'])
      ->values([
        'document_id' => $doc_id,
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
      ])->execute();

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
  public function getSourceData(ContentEntityInterface &$entity) {
    // Logic adapted from Content Translation core module and TMGMT contrib
    // module for pulling translatable field info from content entities.
    $entity_type = $entity->getEntityType();
    $storage_definitions = $entity_type instanceof ContentEntityTypeInterface ? $this->entityManager->getFieldStorageDefinitions($entity_type->id()) : array();
    $translatable_fields = array();
    foreach ($entity->getFields(FALSE) as $field_name => $definition) {
      if (!empty($storage_definitions[$field_name]) && $storage_definitions[$field_name]->isTranslatable() && $field_name != $entity_type->getKey('langcode') && $field_name != $entity_type->getKey('default_langcode')) {
        $translatable_fields[$field_name] = $definition;
      }
    }
    $field_definitions = $this->entityManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $data = array();
    $source_entity = $entity->getUntranslated();
    foreach ($translatable_fields as $k => $definition) {
      // Check if the field is marked for upload.
      if ($this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $k)) {
        // If there is only one relevant attribute, upload it.
        // Get the column translatability configuration.
        module_load_include('inc', 'content_translation', 'content_translation.admin');
        $column_element = content_translation_field_sync_widget($field_definitions[$k]);

        $field = $source_entity->get($k);
        foreach ($field as $fkey => $fval) {
          // If we have only one relevant column, upload that. If not, check
          // our settings.
          if (!$column_element) {
            $property_name = $storage_definitions[$k]->getMainPropertyName();
            $data[$k][$fkey][$property_name] = $fval->get($property_name)->getValue();
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
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function hasEntityChanged(ContentEntityInterface &$entity) {
    $source_data = json_encode($this->getSourceData($entity));
    $hash = md5($source_data);
    $old_hash = $entity->lingotek_hash->value;
    if (!$old_hash || strcmp($hash, $old_hash)){
      $entity->lingotek_hash->value = $hash;
      $entity->save();
      return true;
    }
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function addTarget(ContentEntityInterface &$entity, $locale) {
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
      if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT  && $current_status !== Lingotek::STATUS_READY) {
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
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslations(ContentEntityInterface &$entity) {
    $languages = [];
    if ($document_id = $this->getDocumentId($entity)) {
      $target_languages = $this->languageManager->getLanguages();
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
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument(ContentEntityInterface $entity) {
    if (!empty($entity->lingotek_document_id->value)) {
      return $this->updateDocument($entity);
    }
    $source_data = json_encode($this->getSourceData($entity));
    $document_name = $entity->bundle() . ' (' . $entity->getEntityTypeId() . '): ' . $entity->label();
    $url = $entity->hasLinkTemplate('canonical') ? $entity->toUrl()->setAbsolute(TRUE)->toString() : NULL;
    $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getSourceLocale($entity), $url, $this->lingotekConfiguration->getEntityProfile($entity));
    if ($document_id) {
      $this->setDocumentId($entity, $document_id);
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_REQUEST);
      return $document_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocument(ContentEntityInterface &$entity, $locale) {
    if ($document_id = $this->getDocumentId($entity)) {
      try {
        $data = $this->lingotek->downloadDocument($document_id, $locale);
      }
      catch (LingotekApiException $exception) {
        // TODO: log issue
        return FALSE;
      }

      if ($data) {
        $transaction = db_transaction();
        try {
          $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
          $langcode = $drupal_language->id();
          $this->saveTargetData($entity, $langcode, $data);
          // If the status was "Importing", and the target was added
          // successfully, we can ensure that the content is current now.
          $source_status = $this->getSourceStatus($entity);
          if ($source_status == Lingotek::STATUS_IMPORTING || $source_status == Lingotek::STATUS_EDITED) {
            $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          }
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
        }
        catch (Exception $e) {
          $transaction->rollback();
        }
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument(ContentEntityInterface &$entity) {
    $source_data = json_encode($this->getSourceData($entity));
    $document_id = $this->getDocumentId($entity);
    $url = $entity->toUrl()->setAbsolute()->toString();

    if ($this->lingotek->updateDocument($document_id, $source_data, $url)){
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_REQUEST);
      return $document_id;
    }
    return FALSE;
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
    $entity->lingotek_translation_status = NULL;
    $entity->lingotek_document_id = NULL;

    \Drupal::database()->delete('lingotek_content_metadata')
      ->condition('document_id', $doc_id)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();

    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDocumentId($document_id) {
    $entity = NULL;
    $metadata = \Drupal::database()->select('lingotek_content_metadata','lcm')
      ->fields('lcm', ['document_id', 'entity_type', 'entity_id'])
      ->condition('document_id', $document_id)
      ->execute()
      ->fetchAssoc();
    if ($metadata) {
      $entity = $this->entityManager->getStorage($metadata['entity_type'])->load($metadata['entity_id']);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllLocalDocumentIds() {
    $metadata = \Drupal::database()->select('lingotek_content_metadata','lcm')
      ->fields('lcm', ['document_id'])
      ->execute()
      ->fetchAssoc();
    return array_values($metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function saveTargetData(ContentEntityInterface &$entity, $langcode, $data) {
    // Logic adapted from TMGMT contrib module for saving
    // translated fields to their entity.
    $lock = \Drupal::lock();
    if ($lock->acquire(__FUNCTION__)) {
      $entity = entity_load($entity->getEntityTypeId(), $entity->id(), TRUE);
      if (!$langcode) {
        // TODO: log warning that downloaded translation's langcode is not enabled.
        $lock->release(__FUNCTION__);
        return FALSE;
      }

      // initialize the translation on the Drupal side, if necessary
      if (!$entity->hasTranslation($langcode)) {
        $entity->addTranslation($langcode, $entity->toArray());
      }
      $translation = $entity->getTranslation($langcode);
      foreach ($data as $name => $field_data) {
        foreach ($field_data as $delta => $delta_data) {
          foreach ($delta_data as $property => $property_data) {
            if (method_exists($translation->get($name)->offsetGet($delta), "set")) {
              $translation->get($name)->offsetGet($delta)->set($property, $property_data);
            }
          }
        }
      }
      // We need to set the content_translation source so the files are synced
      // properly. See https://www.drupal.org/node/2544696 for more information.
      $translation->set('content_translation_source', $entity->getUntranslated()->language()->getId());
      $translation->save();
      $lock->release(__FUNCTION__);
    }
    else {
      $lock->wait(__FUNCTION__);
    }
    return $entity;
  }

}
