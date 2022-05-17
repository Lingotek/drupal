<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentBundleTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * Defines a Lingotek form-filter plugin for the entity bundle.
 *
 * @LingotekFormComponentFilter(
 *   id = "bundle",
 *   title = @Translation("Bundle"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 50,
 * )
 */
class Bundle extends LingotekFormComponentFilterBase {

  use DependencySerializationTrait;
  use LingotekFormComponentBundleTrait;

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    $entity_type_id = isset($arguments['entity_type_id']) ? $arguments['entity_type_id'] : NULL;
    return $this->hasBundles($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'select',
      '#title' => $this->getEntityType($this->entityTypeId)->getBundleLabel(),
      '#default_value' => (array) $default_value,
      '#options' => ['' => $this->t('All')] + $this->getAllBundles(),
      '#multiple' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    if ($this->hasBundles($entity_type_id) && !in_array('', $value, TRUE)) {
      parent::filter($entity_type_id, $entities, $value, $query);
      $query->condition('entity_table.' . $this->getEntityType($entity_type_id)->getKey('bundle'), $value, 'IN');
      if ($unions = $query->getUnion()) {
        foreach ($unions as $union) {
          $union['query']->condition('entity_table.' . $this->getEntityType($entity_type_id)->getKey('bundle'), $value, 'IN');
        }
      }
    }
  }

  /**
   * Gets all the bundles as options.
   *
   * @return array
   *   The bundles as a valid options array.
   */
  protected function getAllBundles() {
    $bundles = $this->getBundleInfo($this->entityTypeId);
    $options = [];

    foreach ($bundles as $id => $bundle) {
      $options[$id] = $bundle['label'];
    }

    asort($options);

    return $options;
  }

}
