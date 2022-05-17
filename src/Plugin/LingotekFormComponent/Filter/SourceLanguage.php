<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * Defines a Lingotek form-filter plugin for the source language.
 *
 * @LingotekFormComponentFilter(
 *   id = "source_language",
 *   title = @Translation("Source language"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 700,
 *   group = @Translation("Advanced options"),
 * )
 */
class SourceLanguage extends LingotekFormComponentFilterBase {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'select',
      '#title' => $this->getTitle(),
      '#options' => ['' => $this->t('All languages')] + $this->getAllLanguages(),
      '#default_value' => $default_value ?? '',
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
    $query->innerJoin($entity_type->getDataTable(), 'entity_data', 'entity_table.' . $id_key . '= entity_data.' . $id_key);
    $query->condition('entity_table.' . $langcode_key, $value);
    $query->condition('entity_data.default_langcode', 1);
    if ($unions = $query->getUnion()) {
      foreach ($unions as $union) {
        $union['query']->innerJoin($entity_type->getDataTable(), 'entity_data', 'entity_table.' . $id_key . '= entity_data.' . $id_key);
        $union['query']->condition('entity_table.' . $langcode_key, $value);
        $union['query']->condition('entity_data.default_langcode', 1);
      }
    }
  }

  /**
   * Gets all the languages as options.
   *
   * @return array
   *   The languages as a valid options array.
   */
  protected function getAllLanguages() {
    $languages = $this->languageManager->getLanguages();
    $options = [];
    foreach ($languages as $id => $language) {
      $options[$id] = $language->getName();
    }
    return $options;
  }

}
