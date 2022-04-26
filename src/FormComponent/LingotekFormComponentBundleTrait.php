<?php

namespace Drupal\lingotek\FormComponent;

/**
 * Helper trait to handle entity bundles.
 *
 * @package Drupal\lingotek\FormComponent
 */
trait LingotekFormComponentBundleTrait {

  /**
   * The entity_type.bundle.info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * An array of bundle info keyed by entity-type ID.
   *
   * @var array
   */
  protected $bundleInfo = [];

  /**
   * Gets the entity_type.bundle.info service.
   *
   * @return \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   *   The entity_type.bundle.info service.
   */
  protected function entityTypeBundleInfo() {
    if (!$this->entityTypeBundleInfo) {
      $this->entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
    }

    return $this->entityTypeBundleInfo;
  }

  /**
   * Retrieves bundle information for a given entity type.
   *
   * Returns only translatable bundles.
   *
   * @param string $entity_type_id
   *   The entity-type ID.
   *
   * @return array
   *   An array of bundle definitions.
   */
  protected function getBundleInfo(string $entity_type_id) {
    if (!isset($this->bundleInfo[$entity_type_id])) {
      $bundle_definitions = $this->entityTypeBundleInfo()->getBundleInfo($entity_type_id);

      $this->bundleInfo[$entity_type_id] = array_filter($bundle_definitions, function ($definition) {
        return !empty($definition['translatable']);
      });
    }

    return $this->bundleInfo[$entity_type_id];
  }

  /**
   * Checks whether the entity type has bundles.
   *
   * @param string $entity_type_id
   *   The entity-type ID.
   *
   * @return bool
   *   TRUE if the entity type has bundles.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function hasBundles(string $entity_type_id) {
    $entity_type = $this->getEntityType($entity_type_id);
    $bundle_entity_type = $entity_type->get('bundle_entity_type');
    return $bundle_entity_type && ($bundle_entity_type !== 'bundle');
  }

}
