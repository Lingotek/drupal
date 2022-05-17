<?php

namespace Drupal\lingotek\FormComponent;

/**
 * Base class for Lingotek form bulk action plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
abstract class LingotekFormComponentBulkActionOptionBase extends LingotekFormComponentBase implements LingotekFormComponentBulkActionOptionInterface {

  protected $action_ids = [];

  public function registerBulkActions(array $action_ids) {
    $this->action_ids = array_merge($this->action_ids, $action_ids);
  }

  /**
   * @return array
   */
  protected function getStates(): array {
    $count = count($this->action_ids);
    $states = [];

    foreach ($this->action_ids as $delta => $action_id) {
      $states[] = ['value' => $action_id];
      if ($delta < ($count - 1)) {
        $states[] = 'or';
      }
    }
    return $states;
  }

}
