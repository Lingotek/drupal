<?php

namespace Drupal\lingotek\FormComponent;

/**
 * Base class for Lingotek form-field plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
abstract class LingotekFormComponentFieldBase extends LingotekFormComponentBase implements LingotekFormComponentFieldInterface {

  /**
   * {@inheritdoc}
   */
  public function getHeader($entity_type_id = NULL) {
    return $this->getPluginDefinition()['title'];
  }

  /**
   * Provides sort parameters for the header.
   *
   * @param string $entity_type_id
   *   The entity-type ID.
   *
   * @return array
   *   An array of sort parameters:
   *   - field: the field by which the query will be sorted,
   *     e.g. 'entity_data.nid'.
   *   - sort (optional): the sort direction. Defaults to 'asc'.
   */
  protected function sort($entity_type_id) {
    return [];
  }

}
