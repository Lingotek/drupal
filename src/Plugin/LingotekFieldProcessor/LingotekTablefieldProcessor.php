<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "tablefield",
 *   weight = 5,
 * )
 */
class LingotekTablefieldProcessor extends PluginBase implements LingotekFieldProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return 'tablefield' === $field_definition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = []) {
    foreach ($entity->get($field_name) as $index => $field_item) {
      $tableValue = $field_item->value;
      $embedded_data = [];
      foreach ($tableValue as $row_index => $row) {
        if ($row_index === 'caption') {
          $embedded_data[$index]['caption'] = $row;
        }
        else {
          foreach ($row as $col_index => $cell) {
            $embedded_data[$index]['row:' . $row_index]['col:' . $col_index] = $cell;
          }
        }
      }
      $data[$field_name] = $embedded_data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    foreach ($field_data as $delta => $field_item_data) {
      $caption = '';
      $table = [];
      foreach ($field_item_data as $row_index => $row) {
        if ($row_index === 'caption') {
          $caption = $row;
        }
        else {
          foreach ($row as $col_index => $cell) {
            $table[intval(str_replace('row:', '', $row_index))][intval(str_replace('col:', '', $col_index))] = $cell;
          }
        }
      }
      $translation->get($field_name)->set($delta, ['caption' => $caption, 'value' => $table]);
    }
  }

}
