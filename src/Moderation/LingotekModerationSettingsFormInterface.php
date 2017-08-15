<?php

namespace Drupal\lingotek\Moderation;

/**
 * Workbench moderation settings form helper.
 *
 * @package Drupal\lingotek\Moderation
 */
interface LingotekModerationSettingsFormInterface extends LingotekModerationServiceInterface {

  /**
   * Gets the column header title.
   *
   * @return string
   *   The column header title.
   */
  public function getColumnHeader();

  /**
   * Checks if there is a need for a moderation column.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return bool
   *   If the entity is enabled for moderation, return TRUE. FALSE otherwise.
   */
  public function needsColumn($entity_type_id);

  /**
   * Gets the moderation statuses.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   *
   * @return array
   *   Array with statuses ids as keys and label as value.
   */
  public function getModerationUploadStatuses($entity_type_id, $bundle);

  /**
   * Gets the default upload status.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   *
   * @return string
   *   The default status ID. If there is a setting, that will be returned.
   */
  public function getDefaultModerationUploadStatus($entity_type_id, $bundle);

  /**
   * Gets the default download transition.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   *
   * @return string
   *   The default transition ID. If there is a setting, that will be returned.
   */
  public function getModerationDownloadTransitions($entity_type_id, $bundle);

  /**
   * Gets the default transition.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   *
   * @return string
   *   The default transition ID. If there is a setting, that will be returned.
   */
  public function getDefaultModerationDownloadTransition($entity_type_id, $bundle);

  /**
   * Gets the subform for configuring the settings for a given bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   *
   * @return array
   *   The array defining the form.
   */
  public function form($entity_type_id, $bundle);

  /**
   * Submit handler for saving the settings for a given bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   * @param array $form_values
   *   The submitted form values.
   */
  public function submitHandler($entity_type_id, $bundle, array $form_values);

}
