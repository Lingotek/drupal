<?php

namespace Drupal\lingotek\Moderation;

/**
 * Service for managing moderation settings in the Lingotek integration.
 *
 * @package Drupal\lingotek\Moderation
 */
interface LingotekModerationConfigurationServiceInterface extends LingotekModerationServiceInterface {

  /**
   * Gets the moderation status ID that triggers an upload.
   *
   * @param string $entity_type_id
   *   An entity type ID.
   * @param string $bundle
   *   A bundle.
   *
   * @return string
   *   The moderation status ID that triggers an upload.
   */
  public function getUploadStatus($entity_type_id, $bundle);

  /**
   * Gets the moderation transition ID that triggers a download.
   *
   * @param string $entity_type_id
   *   An entity type ID.
   * @param string $bundle
   *   A bundle.
   *
   * @return string
   *   The moderation transition ID that should be triggered after a download.
   */
  public function getDownloadTransition($entity_type_id, $bundle);

  /**
   * Sets the moderation status ID that triggers an upload.
   *
   * @param string $entity_type_id
   *   An entity type ID.
   * @param string $bundle
   *   A bundle.
   * @param string $status
   *   The moderation status ID.
   *
   * @return string
   *   The moderation status ID that triggers an upload.
   */
  public function setUploadStatus($entity_type_id, $bundle, $status);

  /**
   * Sets the moderation transition ID that triggers a download.
   *
   * @param string $entity_type_id
   *   An entity type ID.
   * @param string $bundle
   *   A bundle.
   * @param string $transition
   *   The moderation transition ID.
   *
   * @return string
   *   The moderation transition ID that should be triggered after a download.
   */
  public function setDownloadTransition($entity_type_id, $bundle, $transition);

}
