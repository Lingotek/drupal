<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * Defines a Lingotek form-filter plugin for the job ID.
 *
 * @LingotekFormComponentFilter(
 *   id = "job_id",
 *   title = @Translation("Job ID"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 300,
 * )
 */
class JobId extends LingotekFormComponentFilterBase {

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'textfield',
      '#title' => $this->getTitle(),
      '#description' => $this->t('You can indicate multiple comma-separated values.<br />The prefix "not:" will return entities with any job ID except the listed ones.<br />Entering <code>&lt;none&gt;</code> will return entities without a job ID.<br />Entering <code>&lt;any&gt;</code> will return entities with any job ID.'),
      '#size' => 35,
      '#default_value' => $default_value ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    if ($value = trim($value)) {
      parent::filter($entity_type_id, $entities, $value, $query);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');

      switch ($value) {
        case '<any>':
          $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
            'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
          $query->condition('metadata.job_id', NULL, 'IS NOT NULL');
          if ($unions = $query->getUnion()) {
            foreach ($unions as $union) {
              $union['query']->innerJoin($metadata_type->getBaseTable(), 'metadata',
                'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
              $union['query']->condition('metadata.job_id', NULL, 'IS NOT NULL');
            }
          }
          break;

        case '<none>':
          $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
            'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
          $query->condition('metadata.job_id', NULL, 'IS NULL');
          if ($unions = $query->getUnion()) {
            foreach ($unions as $union) {
              $union['query']->innerJoin($metadata_type->getBaseTable(), 'metadata',
                'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
              $union['query']->condition('metadata.job_id', NULL, 'IS NULL');
            }
          }
          break;

        default:
          $operator = 'IN';
          $prefix = 'not:';

          if (substr($value, 0, 4) == $prefix) {
            $value = trim(str_replace($prefix, '', $value));
            $operator = 'NOT IN';
          }

          $value = explode(',', $value);
          array_walk($value, function (&$item) {
            $item = trim($item);
          });
          if (count($value) > 1) {
            $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
              'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
            $query->condition('metadata.job_id', $value, $operator);
            if ($unions = $query->getUnion()) {
              foreach ($unions as $union) {
                $union['query']->innerJoin($metadata_type->getBaseTable(), 'metadata',
                  'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
                $union['query']->condition('metadata.job_id', $value, $operator);
              }
            }
          }
          elseif (!empty($value)) {
            $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
              'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
            $query->condition('metadata.job_id', '%' . $value[0] . '%', 'LIKE');
            if ($unions = $query->getUnion()) {
              foreach ($unions as $union) {
                $union['query']->innerJoin($metadata_type->getBaseTable(), 'metadata',
                  'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
                $union['query']->condition('metadata.job_id', '%' . $value[0] . '%', 'LIKE');
              }
            }
          }
      }
    }
  }

}
