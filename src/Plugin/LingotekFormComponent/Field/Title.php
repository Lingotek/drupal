<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\lingotek\FormComponent\LingotekFormComponentBundleTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase;

/**
 * Defines a Lingotek form-field plugin for an entity's label.
 *
 * @LingotekFormComponentField(
 *   id = "title",
 *   title = @Translation("Title"),
 *   weight = 200,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   }
 * )
 */
class Title extends LingotekFormComponentFieldBase {

  use LingotekFormComponentBundleTrait;

  /**
   * {@inheritdoc}
   */
  public function getHeader($entity_type_id = NULL) {
    if ($entity_type_id === NULL) {
      return $this->t('Label');
    }
    $entity_type = $this->getEntityType($entity_type_id);
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('entity_field.manager');
    $properties = $field_manager->getBaseFieldDefinitions($entity_type_id);
    $header = $this->hasBundles($entity_type_id) && $entity_type->hasKey('label') ? $properties[$entity_type->getKey('label')]->getLabel() : $entity_type->getLabel();

    return array_merge(['data' => $header], $this->sort($entity_type_id));
  }

  /**
   * {@inheritdoc}
   */
  public function getData(EntityInterface $entity) {
    return $entity->hasLinkTemplate('canonical') ? Link::fromTextAndUrl($entity->label(), $entity->toUrl())->toString() : $entity->label();
  }

  /**
   * {@inheritdoc}
   */
  protected function sort($entity_type_id) {
    if ($entity_type = $this->getEntityType($entity_type_id)) {
      return [
        'field' => 'entity_table.' . $entity_type->getKey('label'),
      ];
    }

    return [];
  }

}
