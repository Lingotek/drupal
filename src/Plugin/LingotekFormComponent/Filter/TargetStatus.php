<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * Defines a Lingotek form-filter plugin for the target status.
 *
 * @LingotekFormComponentFilter(
 *   id = "target_status",
 *   title = @Translation("Target status"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 800,
 *   group = @Translation("Advanced options"),
 * )
 */
class TargetStatus extends LingotekFormComponentFilterBase {

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'select',
      '#title' => $this->getTitle(),
      '#default_value' => $default_value ?? '',
      '#options' => [
        '' => $this->t('All'),
        Lingotek::STATUS_CURRENT => $this->t('Current'),
        Lingotek::STATUS_EDITED => $this->t('Edited'),
        Lingotek::STATUS_PENDING => $this->t('In Progress'),
        Lingotek::STATUS_READY => $this->t('Ready'),
        Lingotek::STATUS_INTERMEDIATE => $this->t('Interim'),
        Lingotek::STATUS_REQUEST => $this->t('Not Requested'),
        Lingotek::STATUS_CANCELLED => $this->t('Cancelled'),
        Lingotek::STATUS_ERROR => $this->t('Error'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    parent::filter($entity_type_id, $entities, $value, $query);

    $entity_type = $this->getEntityType($entity_type_id);
    $id_key = $entity_type->getKey('id');
    $langcode_key = $entity_type->getKey('langcode');
    $base_table = $entity_type->getBaseTable();
    $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
    $metadata_type_base_table = $metadata_type->getBaseTable();
    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $subquery */
    $subquery = $this->connection->select($base_table, 'entity_table')->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
    $subquery->fields('entity_table', [$id_key]);
    $subquery->innerJoin($metadata_type_base_table, 'metadata_target', 'entity_table.' . $id_key . '= metadata_target.content_entity_id AND metadata_target.content_entity_type_id = \'' . $entity_type_id . '\'');
    $subquery->innerJoin('lingotek_content_metadata__translation_status', 'translation_target_status', 'metadata_target.id = translation_target_status.entity_id AND translation_target_status.translation_status_language <> entity_table.' . $langcode_key);
    $subquery->condition('translation_target_status.translation_status_value', $value);

    $query->condition('entity_table.' . $id_key, $subquery, 'IN');

    if ($unions = $query->getUnion()) {
      foreach ($unions as $union) {
        $union['query']->condition('entity_table.' . $id_key, $subquery, 'IN');
      }
    }
  }

}
