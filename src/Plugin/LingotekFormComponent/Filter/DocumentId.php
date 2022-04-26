<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * Defines a Lingotek form-filter plugin for the document ID.
 *
 * @LingotekFormComponentFilter(
 *   id = "document_id",
 *   title = @Translation("Document ID"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 400,
 *   group = @Translation("Advanced options"),
 * )
 */
class DocumentId extends LingotekFormComponentFilterBase {

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'textfield',
      '#size' => 35,
      '#title' => $this->getTitle(),
      '#description' => $this->t('You can indicate multiple comma-separated values.'),
      '#default_value' => $default_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    if ($value = trim($value)) {
      parent::filter($entity_type_id, $entities, $value, $query);
      $value = explode(',', $value);
      array_walk($value, function (&$value) {
        $value = trim($value);
      });
      $documentIdOperator = (count($value) > 1) ? 'IN' : 'LIKE';
      $documentIdValue = (count($value) > 1) ? $value : '%' . $value[0] . '%';
      $entity_type = $this->getEntityType($entity_type_id);

      $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
      $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
        'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
      $query->condition('metadata.document_id', $documentIdValue, $documentIdOperator);

      if ($unions = $query->getUnion()) {
        foreach ($unions as $union) {
          $union['query']->innerJoin($metadata_type->getBaseTable(), 'metadata',
            'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
          $union['query']->condition('metadata.document_id', $documentIdValue, $documentIdOperator);
        }
      }
    }
  }

}
