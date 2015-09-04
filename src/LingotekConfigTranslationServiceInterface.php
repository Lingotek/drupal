<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigTranslationServiceInterface.
 */

namespace Drupal\lingotek;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Service for managing Lingotek configuration translations.
 */
interface LingotekConfigTranslationServiceInterface {

  /**
   * Gets the config entities that are available for Lingotek config translation.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of config entities that are enabled for Lingotek config translation.
   */
  public function getEnabledConfigTypes();

  /**
   * Checks if the given plugin is enabled for Lingotek config translation.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   *
   * @return bool
   *   TRUE if it enabled, FALSE if not.
   */
  public function isEnabled($plugin_id);

  /**
   * Sets if the given config entity is enabled for Lingotek config translation.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   *
   * @param bool $enabled
   *   Flag for enabling or disabling this config entity. Defaults to TRUE.
   */
  public function setEnabled($plugin_id, $enabled = TRUE);

  /**
   * Gets the default profile for a config entity.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   *
   * @return LingotekProfile
   *   The Lingotek profile.
   */
  public function getDefaultProfile($plugin_id);

  /**
   * Sets the default profile for a config entity.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   * @param string $profile_id
   *   The profile id.
   */
  public function setDefaultProfile($plugin_id, $profile_id);

}
