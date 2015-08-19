<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekProfileInterface.
 */

namespace Drupal\lingotek;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Lingotek profile entity.
 */
interface LingotekProfileInterface extends ConfigEntityInterface {

  /**
   * Returns whether this profile is locked.
   *
   * @return bool
   *   Whether the profile is locked or not.
   */
  public function isLocked();

  /**
   * Gets the weight of the profile.
   *
   * @return int
   *   The weight, used to order profiles with larger positive weights sinking
   *   items toward the bottom of lists.
   */
  public function getWeight();

  /**
   * Sets the weight of the profile.
   *
   * @param int $weight
   *   The weight, used to order profiles with larger positive weights sinking
   *   items toward the bottom of lists.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Returns whether this profile indicates automatic upload of sources.
   *
   * @return bool
   *   Whether the profile indicates automatic upload or not.
   */
  public function hasAutomaticUpload();

  /**
   * Sets whether this profile indicates automatic upload of sources.
   *
   * @param bool $auto_upload
   *   Whether the profile indicates automatic uploads or not.
   */
  public function setAutoUpload($auto_upload);

  /**
   * Returns whether this profile indicates automatic download of translations.
   *
   * @return bool
   *   Whether the profile indicates automatic download or not.
   */
  public function isAutoDownload();

  /**
   * Sets whether this profile indicates automatic download of translations.
   *
   * @param bool $auto_download
   *   Whether the profile indicates automatic download or not.
   */
  public function setAutoDownload($auto_download);

}