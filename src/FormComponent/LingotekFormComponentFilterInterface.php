<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Database\Query\SelectInterface;

/**
 * Interface for Lingotek form-filter plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
interface LingotekFormComponentFilterInterface extends LingotekFormComponentInterface {

  /**
   * Builds the filter element.
   *
   * @param mixed|null $default_value
   *   The filter's default value.
   *
   * @return array
   *   The element's renderable array.
   */
  public function buildElement($default_value = NULL);

  /**
   * Builds the filter group's element.
   *
   * @return array
   *   The element's renderable array.
   */
  public function buildGroupElement();

  /**
   * Returns the filter-key array.
   *
   * @return array
   *   The filter key.
   */
  public function getFilterKey();

  /**
   * Performs the filter operation.
   *
   * @param string $entity_type_id
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   * @param mixed $value
   * @param \Drupal\Core\Database\Query\SelectInterface|null $query
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL);

  /**
   * Gets a submitted filter value from a structured array.
   *
   * @param array $submitted
   *   A structured array from a form state or a tempstore item.
   *
   * @return mixed
   *   The filter value.
   */
  public function getSubmittedValue(array $submitted);

}
