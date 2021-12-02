<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "metatag",
 *   weight = 5,
 * )
 */
class LingotekMetatagProcessor extends PluginBase implements LingotekFieldProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return 'metatag' === $field_definition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = []) {
    $y = $entity->get($field_name);
    foreach ($y as $delta => $field_item) {
      $metatag_serialized = $field_item->get('value')->getValue();
      $metatags = unserialize($metatag_serialized);
      if ($metatags) {
        $data[$field_name][$delta] = $metatags;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    foreach ($field_data as $delta => $field_item) {
      $metatag_value = serialize($field_item);
      $translation->get($field_name)->set($delta, $metatag_value);
    }
  }

}
