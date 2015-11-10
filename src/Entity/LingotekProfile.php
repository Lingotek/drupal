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
 *     "vault",
 *     "project",
 *     "workflow",
 *     "language_overrides",
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
   * Entities using this profile will use this vault.
   *
   * @var string
   */
  protected $vault = 'default';

  /**
   * Entities using this profile will use this project.
   *
   * @var string
   */
  protected $project = 'default';

  /**
   * Entities using this profile will use this workflow.
   *
   * @var string
   */
  protected $workflow = 'default';

  /**
   * Specific target language settings override.
   *
   * @var string
   */
  protected $language_overrides = [];

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
  public function setAutomaticUpload($auto_upload) {
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
  public function setAutomaticDownload($auto_download) {
    $this->auto_download = $auto_download;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVault() {
    return $this->vault;
  }

  /**
   * {@inheritdoc}
   */
  public function setVault($vault) {
    $this->vault = $vault;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProject() {
    return $this->project;
  }

  /**
   * {@inheritdoc}
   */
  public function setProject($project) {
    $this->project = $project;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow() {
    return $this->workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }
  // ToDo: Avoid deletion if this profile is being used.

  /**
   * {@inheritdoc}
   */
  public function getWorkflowForTarget($langcode) {
    $workflow = $this->getWorkflow();
    if (isset($this->language_overrides[$langcode]) &&
      $this->language_overrides[$langcode]['overrides'] === 'custom') {
      $workflow = $this->language_overrides[$langcode]['custom']['workflow'];
    }
    return $workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutomaticDownloadForTarget($langcode) {
    $auto_download = $this->hasAutomaticDownload();
    if (isset($this->language_overrides[$langcode]) &&
      $this->language_overrides[$langcode]['overrides'] === 'custom') {
      $auto_download = $this->language_overrides[$langcode]['custom']['auto_download'];
    }
    return $auto_download;
  }

}
