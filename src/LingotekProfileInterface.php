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
   * The profile is not being used.
   */
  const USED_NEVER = 0;

  /**
   * The profile is being used in content.
   */
  const USED_IN_CONTENT = 1;

  /**
   * The profile is being used in config.
   */
  const USED_IN_CONFIG = 2;

  /**
   * The profile is being used in config.
   */
  const USED_BY_SETTINGS = 3;

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
  public function setAutomaticUpload($auto_upload);

  /**
   * Returns whether this profile indicates automatic download of translations.
   *
   * @return bool
   *   Whether the profile indicates automatic download or not.
   */
  public function hasAutomaticDownload();

  /**
   * Sets whether this profile indicates automatic download of translations.
   *
   * @param bool $auto_download
   *   Whether the profile indicates automatic download or not.
   */
  public function setAutomaticDownload($auto_download);

  /**
   * Gets the TM vault of the profile.
   *
   * @return string
   *   The TM vault identifier, used to upload documents. If 'default', the
   *   default site vault should be used.
   */
  public function getVault();

  /**
   * Sets the TM vault of the profile.
   *
   * @param string $vault
   *   The TM vault identifier, used to upload documents. If 'default', the
   *   default site vault should be used.
   *
   * @return $this
   */
  public function setVault($vault);

  /**
   * Gets the TM project of the profile.
   *
   * @return string
   *   The TM project identifier, used to upload documents. If 'default', the
   *   default site project should be used.
   */
  public function getProject();

  /**
   * Sets the TM vault of the profile.
   *
   * @param string $project
   *   The TM project identifier, used to upload documents. If 'default', the
   *   default site project should be used.
   *
   * @return $this
   */
  public function setProject($project);

  /**
   * Gets the workflow of the profile.
   *
   * @return string
   *   The workflow identifier, used to request translations. If 'default', the
   *   default site workflow should be used.
   */
  public function getWorkflow();

  /**
   * Sets the workflow of the profile.
   *
   * @param string $workflow
   *   The workflow identifier, used to request translations. If 'default', the
   *   default site project should be used.
   *
   * @return $this
   */
  public function setWorkflow($workflow);

  /**
   * Returns whether this profile indicates automatic download of translations for
   * an specific target language.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return bool
   *   Whether the profile indicates automatic download or not.
   */
  public function hasAutomaticDownloadForTarget($langcode);

  /**
   * Gets the workflow to be used for a given language.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   The workflow identifier, used to request translations. If 'default', the
   *   default site workflow should be used.
   */
  public function getWorkflowForTarget($langcode);

  /**
   * Checks if the profile has custom settings for a given target language.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return bool
   *   TRUE if there are customizations, FALSE if not.
   */
  public function hasCustomSettingsForTarget($langcode);

}
