<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkActionOption;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionOptionBase;

/**
 * Defines a Lingotek bulk action plugin for the upload operation.
 *
 * @LingotekFormComponentBulkActionOption(
 *   id = "job_id",
 *   title = @Translation("Job ID"),
 *   weight = 10,
 * )
 */
class JobId extends LingotekFormComponentBulkActionOptionBase {

  use DependencySerializationTrait;

  public function buildFormElement() {
    $states = $this->getStates();
    $element = [
      '#type' => 'lingotek_job_id',
      '#title' => $this->pluginDefinition['title'],
      '#description' => $this->t('Assign a job id that you can filter on later on the TMS or in this page.'),
      '#description_display' => 'after',
      '#states' => [
        'visible' => [
          ':input[name="operation"]' => $states,
        ],
      ],
    ];
    return $element;
  }

}
