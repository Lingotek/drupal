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
  protected $title = NULL;

  /**
   * Constructs a LingotekTranslatableEntity object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(ContentEntityInterface $entity, LingotekInterface $lingotek) {
    $this->entity = $entity;
    $this->L = $lingotek;
  }

  public static function load(ContainerInterface $container, $entity) {
    $lingotek = $container->get('lingotek');
    return new static($entity, $lingotek);
  }

  public static function loadByDocId($doc_id) {
    $entity = FALSE;

    $query = db_select('lingotek_entity_metadata', 'l')->fields('l');
    $query->condition('entity_key', 'doc_id');
    $query->condition('value', $doc_id);
    $result = $query->execute();

    if ($record = $result->fetchAssoc()) {
      $id = $record['entity_id'];
      $entity_type = $record['entity_type'];
    }
    $entity = self::loadById($id, $entity_type);
    return $entity;
  }

  public function getSourceData() {
    // Logic adapted from TMGMT contrib module for pulling
    // translatable field info from content entities.
    $translatable_fields = array_filter($this->entity->getFieldDefinitions(), function ($definition) {
      return $definition->isTranslatable();
    });

    $data = array();
    $translation = $this->entity->getTranslation($this->entity->language()->langcode);
    foreach ($translatable_fields as $k => $definition) {
      $field = $translation->get($k);
      //$data[$k]['#label'] = $definition->getLabel();
      foreach ($field as $fkey => $fval) {
        //$data[$k][$fkey]['#label'] = t('Delta #@delta', array('@delta' => $fkey));
        /* @var FieldItemInterface $field_item */
        foreach ($fval->getProperties() as $pkey => $pval) {
          // Ignore computed values.
          $property_def = $pval->getDataDefinition();
          if (($property_def->isComputed())) {
            continue;
          }
          // Ignore non-string properties and those with limited allowed values
          if ($pval instanceof AllowedValuesInterface || !($pval instanceof StringInterface)) {
            $data[$k][$fkey][$pkey] = $pval->getValue();
          }
        }
      }
    }
    return $data;
  }

  public function saveTargetData($data, $locale) {
    // Logic adapted from TMGMT contrib module for saving
    // translated fields to their entity.

    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $langcode = $this->L->convertLingotek2Drupal($locale);
    if (!$langcode) {
      // TODO: log warning that downloaded translation's langcode is not enabled.
      return FALSE;
    }
    // initialize the translation on the Drupal side, if necessary
    if (!$this->entity->hasTranslation($langcode)) {
      $this->entity->addTranslation($langcode, $this->entity->toArray());
    }
    $translation = $this->entity->getTranslation($langcode);
    foreach ($data as $name => $field_data) {
      foreach (element_children($field_data) as $delta) {
        $field_item = $field_data[$delta];
        foreach (element_children($field_item) as $property) {
          $property_data = $field_item[$property];
          if (isset($property_data['#translation']['#text'])) {
            $translation->get($name)->offsetGet($delta)->set($property, $property_data['#translation']['#text']);
          }
        }
      }
    }
    $translation->save();
    return $this;
  }

  public function getProfile() {
    throw new Exception('getProfile not implemented.');
  }

  public function setProfile() {
    throw new Exception('setProfile not implemented.');
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
        ->condition('entity_type', $this->entity->bundle());
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
    $metadata = $this->metadata();
    if (!isset($metadata[$key])) {
      db_insert('lingotek_entity_metadata')
          ->fields(array(
            'entity_id' => $this->entity->id(),
            'entity_type' => $this->entity->bundle(),
            'entity_key' => $key,
            'value' => $value,
          ))
          ->execute();
    }
    else {
      db_update('lingotek_entity_metadata')
          ->fields(array(
            'value' => $value
          ))
          ->condition('entity_id', $this->entity->id())
          ->condition('entity_type', $this->entity->bundle())
          ->condition('entity_key', $key)
          ->execute();
    }
    return $this;
  }

}
