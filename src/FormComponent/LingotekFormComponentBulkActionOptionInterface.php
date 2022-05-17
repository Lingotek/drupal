<?php

namespace Drupal\lingotek\FormComponent;

/**
 * Interface for Lingotek form-bulk-action options plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
interface LingotekFormComponentBulkActionOptionInterface extends LingotekFormComponentInterface {

  public function registerBulkActions(array $action_ids);

  public function buildFormElement();

}
