<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for Lingotek form-field plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
interface LingotekFormComponentFieldInterface extends LingotekFormComponentInterface {

  /**
   * Returns the table header for a field.
   *
   * @param string $entity_type_id
   *
   * @return mixed
   */
  public function getHeader($entity_type_id = NULL);

  /**
   * Returns the data for a field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array
   */
  public function getData(EntityInterface $entity);

}
