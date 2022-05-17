<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Field;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase;

/**
 * Defines a Lingotek form-field plugin for the job ID.
 *
 * @LingotekFormComponentField(
 *   id = "job_id",
 *   title = @Translation("Job ID"),
 *   weight = 600,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   }
 * )
 */
class JobId extends LingotekFormComponentFieldBase {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function getData(EntityInterface $entity) {
    return $this->translationService->getJobId($entity) ?? '';
  }

}
