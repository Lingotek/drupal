<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * "Ghost" filter plugin that initializes the default query.
 *
 * @LingotekFormComponentFilter(
 *   id = "default",
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *   },
 *   weight = -10000000000,
 * )
 */
class DefaultQuery extends LingotekFormComponentFilterBase {

  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $this->getEntityType($entity_type_id);
    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
    $query = $this->connection->select($entity_type->getBaseTable(), 'entity_table')->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
    $query->fields('entity_table', [$entity_type->getKey('id')]);
    // We don't want items with language undefined.
    $query->condition('entity_table.' . $entity_type->getKey('langcode'), LanguageInterface::LANGCODE_NOT_SPECIFIED, '!=');
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmittedValue($submitted) {
    // We need this filter to run every time.
    return TRUE;
  }

}
