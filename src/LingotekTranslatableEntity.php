<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekTranslatableEntity.
 */

namespace Drupal\lingotek;

use Drupal\lingotek\Exception\LingotekApiException;
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

  public static function load(LingotekInterface $lingotek, $entity) {
    return new static($entity, $lingotek);
  }

  public static function loadById($id, $entity_type) {
    $L = \Drupal::service('lingotek');
    $entity = entity_load($entity_type, $id);
    return self::load($L, $entity);
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
          'created' => method_exists($this->entity, 'getCreatedTime') ? $this->entity->getCreatedTime() : -1,
          'modified' => $this->entity->getChangedTime(),
        ))
        ->execute();
    } else {
      db_update('lingotek_entity_metadata')
        ->fields(array(
          'value' => $value,
          'created' => method_exists($this->entity, 'getCreatedTime') ? $this->entity->getCreatedTime() : -1,
          'modified' => $this->entity->getChangedTime(),
        ))
        ->condition('entity_id', $this->entity->id())
        ->condition('entity_type', $this->entity->getEntityTypeId())
        ->condition('entity_key', $key)
        ->execute();
    
    }
    return $this;
  }

}
