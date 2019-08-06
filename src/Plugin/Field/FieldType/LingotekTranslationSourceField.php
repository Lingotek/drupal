<?php

namespace Drupal\lingotek\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

class LingotekTranslationSourceField extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * Compute the values.
   */
  protected function computeValue() {
    $metadata = $this->getEntity();
    $entity_type_id = $metadata->getContentEntityTypeId();
    $entity_id = $metadata->getContentEntityId();
    if ($entity_type_id && $entity_id) {
      $target_entity = \Drupal::entityTypeManager()
        ->getStorage($entity_type_id)
        ->load($entity_id);
      if ($target_entity) {
        $this->list[0] = $this->createItem(0,
          $target_entity->language()->getId());
      }
    }
  }

}
