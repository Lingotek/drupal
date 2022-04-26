<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentBundleTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase;

/**
 * Defines a Lingotek form-field plugin for an entity's content type.
 *
 * @LingotekFormComponentField(
 *   id = "content_type",
 *   title = @Translation("Content type"),
 *   weight = 50,
 *   form_ids = {
 *     "lingotek_job_content_entities_management"
 *   }
 * )
 */
class ContentType extends LingotekFormComponentFieldBase {

  use LingotekFormComponentBundleTrait;

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader($entity_type_id = NULL) {
    return $this->t('Content Type');
  }

  /**
   * {@inheritdoc}
   */
  public function getData(EntityInterface $entity) {
    return $entity->getEntityType()->getLabel();
  }

}
