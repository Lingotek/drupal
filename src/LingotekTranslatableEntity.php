<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekTranslatableEntity.
 */

namespace Drupal\lingotek;

use Drupal\lingotek\LingotekInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for wrapping entities with translation meta data and functions.
 */
class LingotekTranslatableEntity {

  /**
   * An entity instance.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  public $entity;

  /**
   * The title of the document
   */
  protected $title;

  /*
   * The source locale code
   */
  protected $locale;

  /**
   * Constructs a LingotekTranslatableEntity object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(ContentEntityInterface $entity, LingotekInterface $lingotek) {
    $this->entity = $entity;
    $this->L = $lingotek;
    $this->translatable_field_types = array('text_with_summary', 'text');
    $this->entity_manager = \Drupal::entityManager();
  }

  public static function load(ContainerInterface $container, $entity) {
    $lingotek = $container->get('lingotek');
    return new static($entity, $lingotek);
  }

  public static function loadByDocId($doc_id) {
    $entity = FALSE;

    $query = db_select('lingotek_entity_metadata', 'l')->fields('l', array('entity_id', 'entity_type'));
    $query->condition('entity_key', 'document_id');
    $query->condition('value', $doc_id);
    $result = $query->execute();

    if ($record = $result->fetchAssoc()) {
      $id = $record['entity_id'];
      $entity_type = $record['entity_type'];
    }
    if ($id && $entity_type) {
      $entity = self::loadById($id, $entity_type);
    }
    return $entity;
  }

  public static function loadById($id, $entity_type) {
    $container = \Drupal::getContainer();
    $entity = entity_load($entity_type, $id);
    return self::load($container, $entity);
  }

  public function getSourceLocale() {
    $this->locale = LingotekLocale::convertDrupal2Lingotek($this->entity->language()->getId());
    return $this->locale;
  }

  public function getSourceData() {
    // Logic adapted from Content Translation core module and TMGMT contrib
    // module for pulling translatable field info from content entities.
    $entity_type = $this->entity->getEntityType();
    $storage_definitions = $entity_type instanceof ContentEntityTypeInterface ? $this->entity_manager->getFieldStorageDefinitions($entity_type->id()) : array();
    $translatable_fields = array();
    foreach ($this->entity->getFields(FALSE) as $field_name => $definition) {
      if (!empty($storage_definitions[$field_name]) && $storage_definitions[$field_name]->isTranslatable() && $field_name != $entity_type->getKey('langcode') && $field_name != $entity_type->getKey('default_langcode')) {
        $translatable_fields[$field_name] = $definition;
      }
    }

    $data = array();
    $translation = $this->entity->getTranslation($this->entity->language()->getId());
    foreach ($translatable_fields as $k => $definition) {
      $field = $translation->get($k);
      foreach ($field as $fkey => $fval) {
        foreach ($fval->getProperties() as $pkey => $pval) {
          // TODO: add a check for the fields that should be sent up for translation.
          $data[$k][$fkey][$pkey] = $pval->getValue();
        }
      }
    }
    return $data;
  }

  public function saveTargetData($data, $locale) {
    // Logic adapted from TMGMT contrib module for saving
    // translated fields to their entity.
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $lock = \Drupal::lock();
    if ($lock->acquire(__FUNCTION__)) {
      $this->entity = entity_load($this->entity->getEntityTypeId(), $this->entity->id(), TRUE);
      $langcode = LingotekLocale::convertLingotek2Drupal($locale);
      if (!$langcode) {
        // TODO: log warning that downloaded translation's langcode is not enabled.
        $lock->release(__FUNCTION__);
        return FALSE;
      }

      // initialize the translation on the Drupal side, if necessary
      if (!$this->entity->hasTranslation($langcode)) {
        $this->entity->addTranslation($langcode, $this->entity->toArray());
      }
      $translation = $this->entity->getTranslation($langcode);
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
    return $this;
  }

  public function hasEntityChanged() {
    $source_data = json_encode($this->getSourceData());
    $hash = md5($source_data);
    $old_hash = $this->getHash();

    if (!$old_hash || strcmp($hash, $old_hash)){
      $this->setHash($hash);
      
      return true;
    }

    return false;
  }

  public function setProfileForNewlyIdentifiedEntities() {
    $current_profile = $this->L->get('translate.entity.' . $this->entity->getEntityTypeId() . '.' . $this->entity->bundle() . '.profile');
    
    if ($current_profile == NULL) {
      $this->setProfile(Lingotek::PROFILE_AUTOMATIC);
    }
    else {
      $this->setProfile($current_profile);
    }
  }

  public function hasAutomaticUpload() {
    $profiles = $this->L->get('profile');
    $lte_profile_id = $this->getProfile();
      
    foreach ($profiles as $profile_id => $profile) {
      if ($profile['id'] == $lte_profile_id) {
        $lte_auto_upload = $profile['auto_upload'];
        break;
      }
    }
    return $lte_auto_upload;
  }

  public function hasAutomaticDownload() {
    $profiles = $this->L->get('profile');
    $lte_profile_id = $this->getProfile();
      
    foreach ($profiles as $profile_id => $profile) {
      if ($profile['id'] == $lte_profile_id) {
        $lte_auto_download = $profile['auto_download'];
        break;
      }
    }
    return $lte_auto_download;
  }

  public function getProfile() {
    return $this->getMetadata('profile');
  }

  public function setProfile($profile) {
    return $this->setMetadata('profile', $profile);
  }

  public function getSourceStatus() {
    return $this->getMetadata('source_status');
  }

  public function setSourceStatus($status) {
    return $this->setMetadata('source_status', $status);
  }

  public function getTargetStatus($locale) {
    return $this->getMetadata('target_status_' . $locale);
  }

  public function setTargetStatus($locale, $status) {
    return $this->setMetadata('target_status_' . $locale, $status);
  }

  public function getDocId() {
    return $this->getMetadata('document_id');
  }

  public function setDocId($id) {
    return $this->setMetadata('document_id', $id);
  }
  
  public function getHash() {
    return $this->getMetadata('hash');
  }

  public function setHash($hash) {
    return $this->setMetadata('hash', $hash);
  }

  public function setTargetStatuses($status) {
    $target_languages = \Drupal::languageManager()->getLanguages();
    $entity_langcode = $this->entity->language()->getId();

    foreach($target_languages as $langcode => $language) {
      $locale = LingotekLocale::convertDrupal2Lingotek($langcode);
      if ($langcode != $entity_langcode && $this->getTargetStatus($locale)) {
        $this->setTargetStatus($locale, $status);
      }
    }
  }

  /**
   * Gets a Lingotek metadata value for the given key.
   *
   * @param string $key
   *   The key whose value should be returned. (Returns all
   *   metadata values if not set.)
   *
   * @return string
   *   The value for the specified key, if it exists, or
   *   an array of values if no key is passed.
   */
  public function getMetadata($key = NULL) {
    $metadata = array();

    $query = db_select('lingotek_entity_metadata', 'meta')
      ->fields('meta')
      ->condition('entity_id', $this->entity->id())
      ->condition('entity_type', $this->entity->getEntityTypeId());
    if ($key) {
      $query->condition('entity_key', $key);
    }
    $results = $query->execute();

    foreach ($results as $result) {
      $metadata[$result->entity_key] = $result->value;
    }
    if (empty($metadata)) {
      return NULL;
    }
    if ($key && !empty($metadata[$result->entity_key])) {
      return $metadata[$result->entity_key];
    }
    return $metadata;
  }

  /**
   * Sets a Lingotek metadata value for this item.
   *
   * @param string $key
   *   The key for a name/value pair.
   * @param string $value
   *   The value for a name/value pair.
   */
  public function setMetadata($key, $value) {
    $metadata = $this->getMetadata();
    if (!isset($metadata[$key])) {
      db_insert('lingotek_entity_metadata')
        ->fields(array(
          'entity_id' => $this->entity->id(),
          'entity_type' => $this->entity->getEntityTypeId(),
          'entity_key' => $key,
          'value' => $value,
          'created' => $this->entity->getCreatedTime(),
          'modified' => $this->entity->getChangedTime(),
        ))
        ->execute();
    } else {
      db_update('lingotek_entity_metadata')
        ->fields(array(
          'value' => $value,
          'created' => $this->entity->getCreatedTime(),
          'modified' => $this->entity->getChangedTime(),
        ))
        ->condition('entity_id', $this->entity->id())
        ->condition('entity_type', $this->entity->getEntityTypeId())
        ->condition('entity_key', $key)
        ->execute();
    
    }
    return $this;
  }

  public function deleteMetadata() {
    $metadata = $this->getMetadata();
    
    foreach($metadata as $key => $value) {
      db_delete('lingotek_entity_metadata')
        ->condition('entity_id', $this->entity->id())
        ->condition('entity_type', $this->entity->getEntityTypeId())
        ->condition('entity_key', $key, 'LIKE')
        ->execute();
    }
  }

  public function checkSourceStatus() {
    if ($this->L->documentImported($this->getDocId())) {
      $this->setSourceStatus(Lingotek::STATUS_CURRENT);
      return TRUE;
    }
    return FALSE;
  }

  public function checkTargetStatus($locale) {
    $current_status = $this->getTargetStatus($locale);
    if (($current_status == Lingotek::STATUS_PENDING) && $this->L->getDocumentStatus($this->getDocId())) {
      $current_status = Lingotek::STATUS_READY;
      $this->setTargetStatus($locale, $current_status);
    }
    return $current_status;
  }

  //perhaps this function should be protected
  public function addTarget($locale) {
    if ($this->L->addTarget($this->getDocId(), $locale)) {
      $this->setTargetStatus($locale, Lingotek::STATUS_PENDING);
      return TRUE;
    }
    return FALSE;
  }

  public function requestTranslations() {
    $target_languages = \Drupal::languageManager()->getLanguages();
    $entity_langcode = $this->entity->language()->getId();

    foreach($target_languages as $langcode => $language) {
      $locale = LingotekLocale::convertDrupal2Lingotek($langcode);
      if ($langcode != $entity_langcode) {
        $this->setTargetStatus($locale, Lingotek::STATUS_PENDING);
        $response = $this->addTarget($locale);
      }
    }
  }

  public function upload() {
    $source_data = json_encode($this->getSourceData());
    $document_name = $this->entity->bundle() . ' (' . $this->entity->getEntityTypeId() . '): ' . $this->entity->label();
    $doc_id = $this->L->uploadDocument($document_name, $source_data, $this->getSourceLocale());
    if ($doc_id) {
      $this->setDocId($doc_id);
      $this->setSourceStatus(Lingotek::STATUS_PENDING);
      return $doc_id;
    }
    return FALSE;
  }

  public function delete() {
    if($this->L->deleteDocument($this->getDocId())) {
      return TRUE;
    }  
    return FALSE;
  }

  public function update() {
    $source_data = json_encode($this->getSourceData());
    if ($this->L->updateDocument($this->getDocId(), $source_data)){
      $this->setSourceStatus(Lingotek::STATUS_PENDING);
      return TRUE;
    }
    return FALSE;
  }

  public function download($locale) {
    $data = $this->L->downloadDocument($this->getDocId(), $locale);
    if ($data) {
      $transaction = db_transaction();
      try {
        $this->saveTargetData($data, $locale);
        $this->setTargetStatus($locale, Lingotek::STATUS_CURRENT);
      }
      catch(Exception $e) {
        $transaction->rollback();
      }
      return TRUE;
    }
    return FALSE;
  }

}
