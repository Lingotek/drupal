<?php

namespace Drupal\lingotek\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the Lingotek content metadata entity.
 *
 * Saves the metadata of content entities.
 *
 * @ContentEntityType(
 *   id = "lingotek_content_metadata",
 *   label = @Translation("Lingotek Content Metadata"),
 *   base_table = "lingotek_metadata",
 *   data_table = "lingotek_metadata_field_data",
 *   entity_keys = {
 *     "id" = "id"
 *   }
 * )
 */
class LingotekContentMetadata extends ContentEntityBase {

  /**
   * @{inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['content_entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Content entity type ID'))
      ->setDescription(new TranslatableMarkup('The ID of the content entity type this Lingotek status is for.'))
      ->setRequired(TRUE);

    $fields['content_entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Content entity ID'))
      ->setDescription(new TranslatableMarkup('The ID of the content entity this Lingotek status is for.'))
      ->setRequired(TRUE);

    $fields['document_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Lingotek document id'))
      ->setDescription(new TranslatableMarkup('The Lingotek document id.'));

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Lingotek hash'))
      ->setDescription(new TranslatableMarkup('A hash of the Lingotek saved entity data, required for checking for changes.'));

    $fields['profile'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Lingotek profile'))
      ->setDescription(new TranslatableMarkup('The Lingotek profile defining this translation.'))
      ->setSetting('target_type', 'lingotek_profile');

    $fields['translation_source'] = BaseFieldDefinition::create('language')
      ->setLabel(new TranslatableMarkup('Lingotek translation source'))
      ->setDescription(new TranslatableMarkup('The source language from which this translation was created.'))
      ->setDefaultValue(LanguageInterface::LANGCODE_NOT_SPECIFIED)
      ->setTranslatable(TRUE);

    $fields['translation_status'] = BaseFieldDefinition::create('lingotek_language_key_value')
      ->setLabel(new TranslatableMarkup('Lingotek translation status'))
      ->setDescription(new TranslatableMarkup('The status of the source in case of being the source translation, or the status of the translation.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    return $fields;
  }

  /**
   * Loads a Lingotek content metadata entity based on the entity type and id.
   *
   * @param string $target_entity_type_id
   *   Target entity type id.
   * @param int $target_id
   *   Entity id.
   *
   * @return $this
   *   The Lingotek content metadata if one exists. Otherwise, returns
   *   default values.
   */
  public static function loadByTargetId($target_entity_type_id, $target_id) {
    $metadata = NULL;
    if ($target_entity_type_id == NULL || $target_id == NULL) {
      return NULL;
    }
    $entity_query = \Drupal::entityQuery('lingotek_content_metadata');
    $entity_query->condition('content_entity_type_id', $target_entity_type_id)
      ->condition('content_entity_id', $target_id);
    $result = $entity_query->execute();
    if (!empty($result)) {
      $metadata = self::load(reset($result));
    }

    if ($metadata === NULL) {
      $metadata = new static(
        ['content_entity_type_id' => $target_entity_type_id, 'content_entity_id' => $target_id], 'lingotek_content_metadata');
    }
    return $metadata;
  }

  /**
   * Loads a Lingotek content metadata entity based on the entity type and id.
   *
   * @param string $document_id
   *   Lingotek Document ID
   *
   * @return $this|NULL
   *   The Lingotek content metadata if it exists. Otherwise, returns NULL.
   */
  public static function loadByDocumentId($document_id) {
    $metadata = NULL;
    if ($document_id !== NULL) {
      $entity_query = \Drupal::entityQuery('lingotek_content_metadata');
      $entity_query->condition('document_id', $document_id);
      $result = $entity_query->execute();
      if (!empty($result)) {
        $metadata = self::load(reset($result));
      }
    }
    return $metadata;
  }

  /**
   * Loads all Lingotek document IDs stored in the system.
   *
   * @return string[]
   *   Indexed array of all the document ids.
   */
  public static function getAllLocalDocumentIds() {
    return \Drupal::database()->select('lingotek_metadata','lcm')
      ->fields('lcm', ['document_id'])
      ->execute()
      ->fetchCol(0);
  }

  /**
   * Sets the Lingotek document id.
   *
   * @param string $document_id
   *   Lingotek Document ID
   */
  public function setDocumentId($document_id) {
    $this->document_id->value = $document_id;
  }

  /**
   * Gets the Lingotek profile.
   *
   * @return string
   *   The profile name.
   */
  public function getProfile() {
    return $this->profile->target_id;
  }

  /**
   * Sets the Lingotek profile.
   *
   * @param string $profile_id
   *   The profile name.
   */
  public function setProfile($profile_id) {
    $this->profile->target_id = $profile_id;
  }

  /**
   * Sets the content entity this Lingotek metadata relates to.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity.
   * @return $this
   */
  public function setEntity(EntityInterface $entity) {
    $this->content_entity_type_id->value = $entity->getEntityTypeId();
    $this->content_entity_id->value = $entity->id();
    return $this;
  }

  /**
   * Gets the content entity type ID.
   *
   * @return string
   *   The content entity type ID.
   */
  public function getContentEntityTypeId() {
    return $this->content_entity_type_id->value;
  }

  /**
   * Gets the content entity ID.
   *
   * @return string
   *   The content entity ID.
   */
  public function getContentEntityId() {
    return $this->content_entity_id->value;
  }

  /**
   * Sets the content entity type ID.
   *
   * @param string $value
   *   The content entity type ID.
   * @return $this
   */
  public function setContentEntityTypeId($value) {
    $this->content_entity_type_id = $value;
    return $this;
  }

  /**
   * Sets the content entity ID.
   *
   * @param string $value
   *   The content entity ID.
   * @return $this
   */
  public function setContentEntityId($value) {
    $this->content_entity_id = $value;
    return $this;
  }

}