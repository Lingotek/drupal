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
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   An entity manager object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LingotekInterface $lingotek, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager) {
    $this->lingotek = $lingotek;
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function checkSourceStatus(ContentEntityInterface &$entity) {
    if ($this->lingotek->documentImported($this->getDocumentId($entity))) {
      $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceStatus(ContentEntityInterface &$entity) {
    $source_language = $entity->lingotek_translation_source->value;
    if ($source_language == LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $source_language = $entity->language()->getId();
    }
    return $this->getTargetStatus($entity, $source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceStatus(ContentEntityInterface &$entity, $status) {
    $source_language = $entity->lingotek_translation_source->value;
    if ($source_language == LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $source_language = $entity->language()->getId();
    }
    return $this->setTargetStatus($entity, $source_language, $status);
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatus(ContentEntityInterface &$entity, $locale) {
    $current_status = $this->getTargetStatus($entity, $locale);
    if (($current_status == Lingotek::STATUS_PENDING) && $this->lingotek->getDocumentStatus($this->getDocumentId($entity))) {
      $current_status = Lingotek::STATUS_READY;
      $this->setTargetStatus($entity, $locale, $current_status);
    }
    return $current_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatus(ContentEntityInterface &$entity, $locale) {
    $status = Lingotek::STATUS_UNTRACKED;

    if (count($entity->lingotek_translation_status) > 0) {
      foreach ($entity->lingotek_translation_status->getIterator() as $delta => $value) {
        if ($value->key == $locale) {
          $status = $value->value;
        }
      }
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatus(ContentEntityInterface &$entity, $locale, $status) {
    $set = FALSE;
    if (count($entity->lingotek_translation_status) > 0) {
      foreach ($entity->lingotek_translation_status->getIterator() as $delta => $value) {
        if ($value->key == $locale) {
          $value->value = $status;
          $set = true;
        }
      }
    }
    if (!$set) {
      $entity->lingotek_translation_status->appendItem(['key' => $locale, 'value' => $status]);
      $set = TRUE;
    }
    if ($set) {
      $entity->save();
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatuses(ContentEntityInterface &$entity, $status) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->language()->getId();

    foreach($target_languages as $langcode => $language) {
      $locale = LingotekLocale::convertDrupal2Lingotek($langcode);
      if ($langcode != $entity_langcode && $this->getTargetStatus($entity, $locale)) {
        $this->setTargetStatus($entity, $locale, $status);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentId(ContentEntityInterface &$entity) {
    return $entity->lingotek_document_id->value;
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
    $source_language = $entity->language()->getId();
    return LingotekLocale::convertDrupal2Lingotek($source_language);
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
    $field_definitions = $this->entityManager->getFieldDefinitions($entity_type->id(), $entity->bundle());

    $config = \Drupal::config('lingotek.settings');
    $data = array();
    $translation = $entity->getTranslation($entity->language()->getId());
    foreach ($translatable_fields as $k => $definition) {
      // Check if the field is marked for upload.
      if ($config->get('translate.entity.' . $entity_type->id() . '.' . $entity->bundle() . '.field.' . $k )) {
        // If there is only one relevant attribute, upload it.
        // Get the column translatability configuration.
        module_load_include('inc', 'content_translation', 'content_translation.admin');
        $column_element = content_translation_field_sync_widget($field_definitions[$k]);

        $field = $translation->get($k);
        foreach ($field as $fkey => $fval) {
          // If we have only one relevant column, upload that. If not, check
          // our settings.
          if (!$column_element) {
            $property_name = $storage_definitions[$k]->getMainPropertyName();
            $data[$k][$fkey][$property_name] = $fval->get($property_name)->getValue();
          }
          else {
            $configured_properties = $config->get('translate.entity.' . $entity_type->id() . '.' . $entity->bundle() . '.field.' . $k . ':properties');
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
    if ($this->lingotek->addTarget($this->getDocumentId($entity), $locale)) {
      $this->setTargetStatus($entity, $locale, Lingotek::STATUS_PENDING);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslations(ContentEntityInterface &$entity) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->language()->getId();

    foreach($target_languages as $langcode => $language) {
      $locale = LingotekLocale::convertDrupal2Lingotek($langcode);
      if ($langcode != $entity_langcode) {
        $this->setTargetStatus($entity, $locale, Lingotek::STATUS_PENDING);
        $response = $this->addTarget($entity, $locale);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument(ContentEntityInterface &$entity) {
    $source_data = json_encode($this->getSourceData($entity));
    $document_name = $entity->bundle() . ' (' . $entity->getEntityTypeId() . '): ' . $entity->label();
    $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getSourceLocale($entity));
    if ($document_id) {
      $this->setDocumentId($entity, $document_id);
      $this->setSourceStatus($entity, Lingotek::STATUS_PENDING);
      return $document_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocument(ContentEntityInterface &$entity, $locale) {
    try {
      $data = $this->lingotek->downloadDocument($this->getDocumentId($entity), $locale);
    } catch (LingotekApiException $exception) {
      // TODO: log issue
      return FALSE;
    }

    if ($data) {
      $transaction = db_transaction();
      try {
        $this->saveTargetData($entity, $locale, $data);
        $this->setTargetStatus($entity, $locale, Lingotek::STATUS_CURRENT);
      }
      catch(Exception $e) {
        $transaction->rollback();
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument(ContentEntityInterface &$entity) {
    $source_data = json_encode($this->getSourceData($entity));
    if ($this->lingotek->updateDocument($this->getDocumentId($entity), $source_data)){
      $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
      return TRUE;
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
    $this->setTargetStatuses($entity, NULL);
    $this->setDocumentId($entity, NULL);
    $this->setSourceStatus($entity, NULL);
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
  public function saveTargetData(ContentEntityInterface &$entity, $locale, $data) {
    // Logic adapted from TMGMT contrib module for saving
    // translated fields to their entity.
    $lock = \Drupal::lock();
    if ($lock->acquire(__FUNCTION__)) {
      $entity = entity_load($entity->getEntityTypeId(), $entity->id(), TRUE);
      $langcode = LingotekLocale::convertLingotek2Drupal($locale);
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
      $translation->save();
      $lock->release(__FUNCTION__);
    }
    else {
      $lock->wait(__FUNCTION__);
    }
    return $entity;
  }

}