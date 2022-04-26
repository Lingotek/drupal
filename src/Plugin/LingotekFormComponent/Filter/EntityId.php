<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * Defines a Lingotek form-filter plugin for the entity ID.
 *
 * @LingotekFormComponentFilter(
 *   id = "entity_id",
 *   title = @Translation("Entity ID"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 500,
 *   group = @Translation("Advanced options"),
 * )
 */
class EntityId extends LingotekFormComponentFilterBase {

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'textfield',
      '#title' => $this->getTitle(),
      '#description' => $this->t('You can indicate multiple comma-separated values.'),
      '#size' => 35,
      '#default_value' => $default_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    if ($value = trim($value)) {
      $value = explode(',', $value);
      array_walk($value, function (&$value) {
        $value = trim($value);
      });

      if ($value = array_filter($value)) {
        parent::filter($entity_type_id, $entities, $value, $query);
        $entity_type = $this->getEntityType($entity_type_id);
        $id_key = $entity_type->getKey('id');
        $query->innerJoin($entity_type->getDataTable(), 'entity_data', 'entity_table.' . $id_key . '= entity_data.' . $id_key);
        $query->condition('entity_table.' . $id_key, $value, 'IN');

        if ($unions = $query->getUnion()) {
          foreach ($unions as $union) {
            $union['query']->innerJoin($entity_type->getDataTable(), 'entity_data', 'entity_table.' . $id_key . '= entity_data.' . $id_key);
            $union['query']->condition('entity_table.' . $id_key, $value, 'IN');
          }
        }
      }
    }
  }

}
