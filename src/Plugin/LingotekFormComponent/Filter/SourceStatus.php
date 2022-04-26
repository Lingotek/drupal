<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;
use Drupal\lingotek\Lingotek;

/**
 * Defines a Lingotek form-filter plugin for the source status.
 *
 * @LingotekFormComponentFilter(
 *   id = "source_status",
 *   title = @Translation("Source status"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = -500,
 *   group = @Translation("Advanced options"),
 * )
 */
class SourceStatus extends LingotekFormComponentFilterBase {

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'select',
      '#title' => $this->getTitle(),
      '#default_value' => $default_value,
      '#options' => [
        '' => $this->t('All'),
        'UPLOAD_NEEDED' => $this->t('Upload Needed'),
        Lingotek::STATUS_CURRENT => $this->t('Current'),
        Lingotek::STATUS_IMPORTING => $this->t('Importing'),
        Lingotek::STATUS_EDITED => $this->t('Edited'),
        Lingotek::STATUS_CANCELLED => $this->t('Cancelled'),
        Lingotek::STATUS_ERROR => $this->t('Error'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    if (!$value) {
      return;
    }

    parent::filter($entity_type_id, $entities, $value, $query);

    $entity_type = $this->getEntityType($entity_type_id);
    $id_key = $entity_type->getKey('id');
    $langcode_key = $entity_type->getKey('langcode');
    $base_table = $entity_type->getBaseTable();
    $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
    $metadata_type_id_key = $metadata_type->getKey('id');
    $metadata_type_base_table = $metadata_type->getBaseTable();

    if ($value === 'UPLOAD_NEEDED') {
      // We consider that "Upload Needed" includes those never uploaded or
      // disassociated, edited, or with error on last upload.
      $needingUploadStatuses = [
        Lingotek::STATUS_EDITED,
        Lingotek::STATUS_REQUEST,
        Lingotek::STATUS_CANCELLED,
        Lingotek::STATUS_ERROR,
      ];

      // Filter metadata by content_entity_type_id if exists
      $query->innerJoin($metadata_type_base_table, 'metadata_source',
        'entity_table.' . $id_key . '= metadata_source.content_entity_id AND metadata_source.content_entity_type_id = \'' . $entity_type_id . '\'');
      $query->innerJoin('lingotek_content_metadata__translation_status', 'translation_status',
        'metadata_source.id = translation_status.entity_id AND translation_status.translation_status_language = entity_table.' . $langcode_key);
      $query->condition('translation_status.translation_status_value', $needingUploadStatuses, 'IN');

      // No metadata.
      $no_metadata_query = $this->connection->select($metadata_type_base_table, 'mt');
      $no_metadata_query->fields('mt', [$metadata_type_id_key]);
      $no_metadata_query->where('entity_table.' . $id_key . ' = mt.content_entity_id');
      $no_metadata_query->condition('mt.content_entity_type_id', $entity_type_id);
      $union1 = $this->connection->select($base_table, 'entity_table');
      $union1->fields('entity_table', [$id_key]);
      $union1->condition('entity_table.' . $langcode_key, LanguageInterface::LANGCODE_NOT_SPECIFIED, '!=');
      $union1->notExists($no_metadata_query);

      // No target statuses.
      $no_statuses_query = $this->connection->select('lingotek_content_metadata__translation_status', 'tst');
      $no_statuses_query->fields('tst', ['entity_id']);
      $no_statuses_query->where('mt2.' . $metadata_type_id_key . ' = tst.entity_id');
      $union2 = $this->connection->select($base_table, 'entity_table');
      $union2->fields('entity_table', [$id_key]);
      $union2->innerJoin($metadata_type_base_table, 'mt2',
        'entity_table.' . $id_key . '= mt2.content_entity_id AND mt2.content_entity_type_id = \'' . $entity_type_id . '\'');
      $union2->condition('entity_table.' . $langcode_key, LanguageInterface::LANGCODE_NOT_SPECIFIED, '!=');
      $union2->notExists($no_statuses_query);

      $query->union($union1);
      $query->union($union2);
    }
    else {
      $query->innerJoin($metadata_type_base_table, 'metadata_source',
        'entity_table.' . $id_key . '= metadata_source.content_entity_id AND metadata_source.content_entity_type_id = \'' . $entity_type_id . '\'');
      $query->innerJoin('lingotek_content_metadata__translation_status', 'translation_status',
        'metadata_source.id = translation_status.entity_id AND translation_status.translation_status_language = entity_table.' . $langcode_key);
      $query->condition('translation_status.translation_status_value', $value);
    }
  }

}
