<?php

namespace Drupal\lingotek;

/**
 * Service contract for checking Lingotek profile usage.
 *
 * @package Drupal\lingotek
 */
interface LingotekProfileUsageInterface {

  /**
   * Bit flag constant for profiles configured to be used by content entities.
   */
  const USED_BY_SETTINGS = 4;

  /**
   * Bit flag constant for unused profiles.
   */
  const UNUSED = 0;

  /**
   * Bit flag constant for profiles configured to be used by config entities.
   */
  const USED_BY_CONFIG = 2;

  /**
   * Bit flag constant for profiles assigned to content.
   */
  const USED_BY_CONTENT = 1;

  /**
   * Check if a profile is used by content entities.
   *
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   The profile being checked.
   *
   * @return int
   *   Bitwise flag. Can include LingotekProfileUsageInterface::USED_BY_CONTENT
   *   or LingotekProfileUsageInterface::UNUSED.
   */
  public function isUsedByContent(LingotekProfileInterface $profile);

  /**
   * Check if a profile is configured to be used by config entities.
   *
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   The profile being checked.
   *
   * @return int
   *   Bitwise flag. Can include LingotekProfileUsageInterface::USED_BY_CONFIG or
   *   LingotekProfileUsageInterface::UNUSED.
   */
  public function isUsedByConfig(LingotekProfileInterface $profile);

  /**
   * Check if a profile is configured to be used by content entities.
   *
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   The profile being checked.
   *
   * @return int
   *   Bitwise flag. Can include LingotekProfileUsageInterface::USED_BY_SETTINGS
   *   or LingotekProfileUsageInterface::UNUSED.
   */
  public function isUsedByContentSettings(LingotekProfileInterface $profile);

}
