<?php

namespace Drupal\lingotek\Moderation;

/**
 * Moderation configuration service when no other integration applies.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekNoModerationConfigurationService implements LingotekModerationConfigurationServiceInterface {

  use LingotekNoModerationCheckTrait;

  /**
   * {@inheritdoc}
   */
  public function getUploadStatus($entity_type_id, $bundle) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadTransition($entity_type_id, $bundle) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setUploadStatus($entity_type_id, $bundle, $status) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function setDownloadTransition($entity_type_id, $bundle, $transition) {
    // Do nothing.
  }

}
