<?php

namespace Drupal\lingotek\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\lingotek\Entity\LingotekContentMetadata;

/**
 * A computed field that provides a content entity's Lingotek metadata.
 */
class LingotekContentMetadataFieldItemList extends EntityReferenceFieldItemList {

  /**
   * Gets the Lingotek metadata entity linked to a content entity revision.
   *
   * @return \Drupal\lingotek\LingotekContentMetadataInterface|null
   *   The content entity's Lingotek metadata.
   */
  protected function getContentMetadata() {
    $entity = $this->getEntity();

    /** @var LingotekConfigurationServiceInterface $config_service */
    $config_service = \Drupal::service('lingotek.configuration');
    if (!$config_service->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      return NULL;
    }

    if ($entity->id()) {
      $metadata_result = \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->getQuery()
        ->condition('content_entity_type_id', $entity->getEntityTypeId())
        ->condition('content_entity_id', $entity->id())
        ->execute();

      if ($metadata_id = key($metadata_result)) {
        /** @var \Drupal\lingotek\LingotekContentMetadataInterface $metadata */
        $metadata = \Drupal::entityTypeManager()
          ->getStorage('lingotek_content_metadata')
          ->load($metadata_id);

        return $metadata;
      }
    }
    $metadata = LingotekContentMetadata::create();
    if ($entity->id()) {
      $metadata->setEntity($entity);
      $metadata->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    if ($index !== 0) {
      throw new \InvalidArgumentException('An entity can not have multiple moderation states at the same time.');
    }
    if (isset($this->list[$index]) && !$this->list[$index]->isEmpty()) {
      return $this->list[$index];
    }
    $this->computeLingotekMetadataFieldItemList();
    return isset($this->list[$index]) ? $this->list[$index] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->computeLingotekMetadataFieldItemList();
    return parent::getIterator();
  }

  /**
   * Recalculate the Lingotek metadata field item list.
   */
  protected function computeLingotekMetadataFieldItemList() {
    // Compute the value of the moderation state.
    $index = 0;
    if (!isset($this->list[$index]) || $this->list[$index]->isEmpty()) {
      $metadata = $this->getContentMetadata();
      // Do not store NULL values in the static cache.
      if ($metadata) {
        $this->list[$index] = $this->createItem($index, ['entity' => $metadata]);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function preSave() {
    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    $metadata = $this->getContentMetadata();
    if ($metadata && !$metadata->getContentEntityId()) {
      $metadata->setEntity($this->getEntity());
    }
    parent::preSave();
  }

}
