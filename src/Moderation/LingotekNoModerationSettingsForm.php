<?php

namespace Drupal\lingotek\Moderation;

/**
 * Moderation settings form when no other integration applies.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekNoModerationSettingsForm implements LingotekModerationSettingsFormInterface {

  use LingotekNoModerationCheckTrait;

  /**
   * {@inheritdoc}
   */
  public function getColumnHeader() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function needsColumn($entity_type_id) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationUploadStatuses($entity_type_id, $bundle) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModerationUploadStatus($entity_type_id, $bundle) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationDownloadTransitions($entity_type_id, $bundle) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModerationDownloadTransition($entity_type_id, $bundle) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function form($entity_type_id, $bundle) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitHandler($entity_type_id, $bundle, array $form_values) {
    // Do nothing.
  }

}
