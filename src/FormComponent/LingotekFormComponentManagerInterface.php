<?php

namespace Drupal\lingotek\FormComponent;

/**
 * Interface for all form-component-plugin managers.
 *
 * @package Drupal\lingotek\FormComponent
 */
interface LingotekFormComponentManagerInterface {

  /**
   * Gets applicable plugins for a certain form ID and entity type.
   *
   * @param array $arguments
   *   An associative array of variables, keyed by variable name. Normally, this
   *   would include:
   *   - form_id: The ID of the form that's calling the plugin manager.
   *   - entity_type_id: The ID of the entity type the form is managing.
   *   More specific plugin managers may require different variables.
   *
   * @return \Drupal\lingotek\FormComponent\LingotekFormComponentInterface[]
   *   The form plugins.
   */
  public function getApplicable(array $arguments = []);

}
