<?php

namespace Drupal\lingotek\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\lingotek\LingotekIntelligenceMetadataInterface;
use Drupal\lingotek\LingotekProfileInterface;

/**
 * Defines the LingotekProfile entity.
 *
 * @ConfigEntityType(
 *   id = "lingotek_profile",
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
 *     "intelligence_metadata",
 *     "filter",
 *     "subfilter",
 *     "language_overrides",
 *   },
 *   links = {
 *     "add-form" = "/admin/lingotek/settings/profile/add",
 *     "delete-form" = "/admin/lingotek/settings/profile/{profile}/delete",
 *     "edit-form" = "/admin/lingotek/settings/profile/{profile}/edit",
 *   },
 * )
 */
class LingotekProfile extends ConfigEntityBase implements LingotekProfileInterface, LingotekIntelligenceMetadataInterface {

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
   * Entities using this profile will use this FPRM Filter.
   *
   * @var string
   */
  protected $filter = 'project_default';

  /**
   * Entities using this profile will use this FPRM Subfilter.
   *
   * @var string
   */
  protected $subfilter = 'project_default';

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
   * Metadata for content with this translation profile
   *
   * @var array
   */
  protected $intelligence_metadata = [
    'override' => FALSE,
    'business_unit' => '',
    'business_division' => '',
    'campaign_id' => '',
    'campaign_rating' => 0,
    'channel' => '',
    'contact_name' => '',
    'contact_email' => '',
    'content_description' => '',
    'external_style_id' => '',
    'purchase_order' => '',
    'region' => '',
    'use_author' => TRUE,
    'default_author_email' => '',
    'use_author_email' => TRUE,
    'use_contact_email_for_author' => FALSE,
    'use_business_unit' => TRUE,
    'use_business_division' => TRUE,
    'use_campaign_id' => TRUE,
    'use_campaign_rating' => TRUE,
    'use_channel' => TRUE,
    'use_contact_name' => TRUE,
    'use_contact_email' => TRUE,
    'use_content_description' => TRUE,
    'use_external_style_id' => TRUE,
    'use_purchase_order' => TRUE,
    'use_region' => TRUE,
    'use_base_domain' => TRUE,
    'use_reference_url' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  public function getBusinessUnit() {
    return $this->intelligence_metadata['business_unit'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessUnit($business_unit) {
    $this->intelligence_metadata['business_unit'] = $business_unit;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessDivision() {
    return $this->intelligence_metadata['business_division'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessDivision($business_division) {
    $this->intelligence_metadata['business_division'] = $business_division;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignId() {
    return $this->intelligence_metadata['campaign_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignId($campaign_id) {
    $this->intelligence_metadata['campaign_id'] = $campaign_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignRating() {
    return $this->intelligence_metadata['campaign_rating'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignRating($campaign_rating) {
    $this->intelligence_metadata['campaign_rating'] = $campaign_rating;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChannel() {
    return $this->intelligence_metadata['channel'];
  }

  /**
   * {@inheritdoc}
   */
  public function setChannel($channel) {
    $this->intelligence_metadata['channel'] = $channel;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactName() {
    return $this->intelligence_metadata['contact_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContactName($contact_name) {
    $this->intelligence_metadata['contact_name'] = $contact_name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEmail() {
    return $this->intelligence_metadata['contact_email'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContactEmail($contact_email) {
    $this->intelligence_metadata['contact_email'] = $contact_email;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentDescription() {
    return $this->intelligence_metadata['content_description'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContentDescription($content_description) {
    $this->intelligence_metadata['content_description'] = $content_description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchaseOrder() {
    return $this->intelligence_metadata['purchase_order'];
  }

  /**
   * {@inheritdoc}
   */
  public function setPurchaseOrder($purchase_order) {
    $this->intelligence_metadata['purchase_order'] = $purchase_order;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalStyleId() {
    return $this->intelligence_metadata['external_style_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function setExternalStyleId($external_style_id) {
    $this->intelligence_metadata['external_style_id'] = $external_style_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegion() {
    return $this->intelligence_metadata['region'];

  }

  /**
   * {@inheritdoc}
   */
  public function setRegion($region) {
    $this->intelligence_metadata['region'] = $region;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorPermission() {
    return $this->intelligence_metadata['use_author'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthorPermission($use_author) {
    $this->intelligence_metadata['use_author'] = $use_author;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultAuthorEmail() {
    return $this->intelligence_metadata['default_author_email'];
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultAuthorEmail($default_author_email) {
    $this->intelligence_metadata['default_author_email'] = $default_author_email;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorEmailPermission() {
    return $this->intelligence_metadata['use_author_email'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthorEmailPermission($use_author_email) {
    $this->intelligence_metadata['use_author_email'] = $use_author_email;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEmailForAuthorPermission() {
    return $this->intelligence_metadata['use_contact_email_for_author'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContactEmailForAuthorPermission($use_contact_email_for_author) {
    $this->intelligence_metadata['use_contact_email_for_author'] = $use_contact_email_for_author;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessUnitPermission() {
    return $this->intelligence_metadata['use_business_unit'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessUnitPermission($use_business_unit) {
    $this->intelligence_metadata['use_business_unit'] = $use_business_unit;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessDivisionPermission() {
    return $this->intelligence_metadata['use_business_division'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessDivisionPermission($use_business_division) {
    $this->intelligence_metadata['use_business_division'] = $use_business_division;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignIdPermission() {
    return $this->intelligence_metadata['use_campaign_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignIdPermission($use_campaign_id) {
    $this->intelligence_metadata['use_campaign_id'] = $use_campaign_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignRatingPermission() {
    return $this->intelligence_metadata['use_campaign_rating'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignRatingPermission($use_campaign_rating) {
    $this->intelligence_metadata['use_campaign_rating'] = $use_campaign_rating;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelPermission() {
    return $this->intelligence_metadata['use_channel'];
  }

  /**
   * {@inheritdoc}
   */
  public function setChannelPermission($use_channel) {
    $this->intelligence_metadata['use_channel'] = $use_channel;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactNamePermission() {
    return $this->intelligence_metadata['use_contact_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContactNamePermission($use_contact_name) {
    $this->intelligence_metadata['use_contact_name'] = $use_contact_name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEmailPermission() {
    return $this->intelligence_metadata['use_contact_email'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContactEmailPermission($use_contact_email) {
    $this->intelligence_metadata['use_contact_email'] = $use_contact_email;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentDescriptionPermission() {
    return $this->intelligence_metadata['use_content_description'];
  }

  /**
   * {@inheritdoc}
   */
  public function setContentDescriptionPermission($use_content_description) {
    $this->intelligence_metadata['use_content_description'] = $use_content_description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalStyleIdPermission() {
    return $this->intelligence_metadata['use_external_style_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function setExternalStyleIdPermission($use_external_style_id) {
    $this->intelligence_metadata['use_external_style_id'] = $use_external_style_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchaseOrderPermission() {
    return $this->intelligence_metadata['use_purchase_order'];
  }

  /**
   * {@inheritdoc}
   */
  public function setPurchaseOrderPermission($use_purchase_order) {
    $this->intelligence_metadata['use_purchase_order'] = $use_purchase_order;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionPermission() {
    return $this->intelligence_metadata['use_region'];
  }

  /**
   * {@inheritdoc}
   */
  public function setRegionPermission($use_region) {
    $this->intelligence_metadata['use_region'] = $use_region;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseDomainPermission() {
    return $this->intelligence_metadata['use_base_domain'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBaseDomainPermission($use_base_domain) {
    $this->intelligence_metadata['use_base_domain'] = $use_base_domain;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceUrlPermission() {
    return $this->intelligence_metadata['use_reference_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function setReferenceUrlPermission($use_reference_url) {
    $this->intelligence_metadata['use_reference_url'] = $use_reference_url;
    return $this;
  }

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
  public function getFilter() {
    return $this->filter;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilter($filter) {
    $this->filter = $filter;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubfilter() {
    return $this->subfilter;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubfilter($filter) {
    $this->subfilter = $filter;
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
    if (isset($this->language_overrides[$langcode]) && $this->hasCustomSettingsForTarget($langcode)) {
      $workflow = $this->language_overrides[$langcode]['custom']['workflow'];
    }
    return $workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutomaticDownloadForTarget($langcode) {
    $auto_download = $this->hasAutomaticDownload();
    if (isset($this->language_overrides[$langcode]) && $this->hasCustomSettingsForTarget($langcode)) {
      $auto_download = $this->language_overrides[$langcode]['custom']['auto_download'];
    }
    return $auto_download;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCustomSettingsForTarget($langcode) {
    return isset($this->language_overrides[$langcode]) && $this->language_overrides[$langcode]['overrides'] === 'custom';
  }

  /**
   * {@inheritdoc}
   */
  public function hasIntelligenceMetadataOverrides() {
    return isset($this->intelligence_metadata['override']) && $this->intelligence_metadata['override'] === TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setIntelligenceMetadataOverrides($value) {
    $this->intelligence_metadata['override'] = $value;
    return $this;
  }

}
