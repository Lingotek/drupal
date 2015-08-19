<?php

/**
 * @file
 * Contains \Drupal\lingotek\Entity\LingotekProfile.
 */

namespace Drupal\lingotek\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\lingotek\LingotekProfileInterface;

/**
 * Defines the LingotekProfile entity.
 *
 * @ConfigEntityType(
 *   id = "profile",
 *   label = @Translation("Lingotek Profile"),
 *   handlers = {
 *     "list_builder" = "Drupal\lingotek\LingotekProfileListBuilder",
 *     "form" = {
 *       "add" = "Drupal\lingotek\Form\LingotekProfileAddForm",
 *       "edit" = "Drupal\lingotek\Form\LingotekProfileEditForm",
 *       "delete" = "Drupal\lingotek\Form\LingotekProfileDeleteForm"
 *     },
 *   },
 *   admin_permission = "administer lingotek",
 *   config_prefix = "profile",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "locked",
 *     "auto_upload",
 *     "auto_download",
 *   },
 *   links = {
 *     "add-form" = "/admin/lingotek/settings/profile/add",
 *     "delete-form" = "/admin/lingotek/settings/profile/{profile}/delete",
 *     "edit-form" = "/admin/lingotek/settings/profile/{profile}/edit",
 *   },
 * )
 */
class LingotekProfile extends ConfigEntityBase implements LingotekProfileInterface {

  /**
   * The profile ID (machine name).
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable label for the profile.
   *
   * @var string
   */
  protected $label;

  /**
   * The weight of the profile, used in lists of profiles.
   *
   * @var integer
   */
  protected $weight = 0;

  /**
   * Locked profiles cannot be edited.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * Entities using this profile may automatically upload sources.
   *
   * @var bool
   */
  protected $auto_upload = FALSE;

  /**
   * Entities using this profile may automatically download translations.
   *
   * @var bool
   */
  protected $auto_download = FALSE;

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return (bool) $this->locked;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutomaticUpload() {
    return (bool) $this->auto_upload;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutoUpload($auto_upload) {
    $this->auto_upload = $auto_upload;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutomaticDownload() {
    return (bool) $this->auto_download;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutoDownload($auto_download) {
    $this->auto_download = $auto_download;
    return $this;
  }

  // ToDo: Avoid deletion if this profile is being used.

}
