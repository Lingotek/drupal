<?php

namespace Drupal\lingotek;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for managing Lingotek Intelligence related configuration.
 *
 * @package Drupal\lingotek
 */
class LingotekIntelligenceService implements LingotekIntelligenceMetadataInterface, ContainerInjectionInterface {

  /**
   * The Lingotek Intelligence configuration service.
   *
   * @var \Drupal\lingotek\LingotekIntelligenceServiceConfig
   */
  protected $intelligenceConfig;

  /**
   * The Lingotek profile.
   *
   * @var \Drupal\lingotek\LingotekProfileInterface|NULL
   */
  protected $profile;

  /**
   * Constructs a LingotekIntelligenceService object.
   *
   * @param \Drupal\lingotek\LingotekIntelligenceServiceConfig $intelligence_config
   *   The Lingotek Intelligence configuration service.
   */
  public function __construct(LingotekIntelligenceServiceConfig $intelligence_config) {
    $this->intelligenceConfig = $intelligence_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.intelligence_config')
    );
  }

  /**
   * Sets the profile.
   *
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   The profile.
   */
  public function setProfile($profile) {
    $this->profile = $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessUnit() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessUnit($business_unit) {
    return $this->setValue('business_unit', $business_unit);
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessDivision() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessDivision($business_division) {
    return $this->setValue('business_division', $business_division);
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignId() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignId($campaign_id) {
    return $this->setValue('campaign_id', $campaign_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignRating() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignRating($campaign_rating) {
    return $this->setValue('campaign_rating', $campaign_rating);
  }

  /**
   * {@inheritdoc}
   */
  public function getChannel() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setChannel($channel) {
    return $this->setValue('channel', $channel);
  }

  /**
   * {@inheritdoc}
   */
  public function getContactName() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setContactName($contact_name) {
    return $this->setValue('contact_name', $contact_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEmail() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setContactEmail($contact_email) {
    return $this->setValue('contact_email', $contact_email);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentDescription() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setContentDescription($content_description) {
    return $this->setValue('content_description', $content_description);
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchaseOrder() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setPurchaseOrder($purchase_order) {
    return $this->setValue('purchase_order', $purchase_order);
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalStyleId() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setExternalStyleId($external_style_id) {
    return $this->setValue('external_style_id', $external_style_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getRegion() {
    return $this->getValue(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setRegion($region) {
    return $this->setValue('region', $region);
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthorPermission($use_author) {
    return $this->setValue('use_author', $use_author);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultAuthorEmail() {
    /** @var \Drupal\lingotek\LingotekIntelligenceMetadataInterface $metadata */
    $metadata = $this->profile;
    $value = NULL;
    if ($this->profile !== NULL && $this->profile->hasIntelligenceMetadataOverrides()) {
      if ($metadata->getAuthorEmailPermission()) {
        $value = $metadata->getDefaultAuthorEmail();
      }
    }
    else {
      if ($this->intelligenceConfig->getAuthorEmailPermission()) {
        $value = $this->intelligenceConfig->getDefaultAuthorEmail();
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultAuthorEmail($default_author_email) {
    return $this->setValue('default_author_email', $default_author_email);
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorEmailPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthorEmailPermission($use_author_email) {
    return $this->setValue('use_author_email', $use_author_email);
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEmailForAuthorPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setContactEmailForAuthorPermission($use_contact_email_for_author) {
    return $this->setValue('use_contact_email_for_author', $use_contact_email_for_author);
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessUnitPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessUnitPermission($use_business_unit) {
    return $this->setValue('use_business_unit', $use_business_unit);
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessDivisionPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setBusinessDivisionPermission($use_business_division) {
    return $this->setValue('use_business_division', $use_business_division);
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignIdPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignIdPermission($use_campaign_id) {
    return $this->setValue('use_campaign_id', $use_campaign_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaignRatingPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaignRatingPermission($use_campaign_rating) {
    return $this->setValue('use_campaign_rating', $use_campaign_rating);
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setChannelPermission($use_channel) {
    return $this->setValue('use_channel', $use_channel);
  }

  /**
   * {@inheritdoc}
   */
  public function getContactNamePermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setContactNamePermission($use_contact_name) {
    return $this->setValue('use_contact_name', $use_contact_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEmailPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setContactEmailPermission($use_contact_email) {
    return $this->setValue('use_contact_email', $use_contact_email);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentDescriptionPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setContentDescriptionPermission($use_content_description) {
    return $this->setValue('use_content_description', $use_content_description);
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalStyleIdPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setExternalStyleIdPermission($use_external_style_id) {
    return $this->setValue('use_external_style_id', $use_external_style_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchaseOrderPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setPurchaseOrderPermission($use_purchase_order) {
    return $this->setValue('use_purchase_order', $use_purchase_order);
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setRegionPermission($use_region) {
    return $this->setValue('use_region', $use_region);
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseDomainPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setBaseDomainPermission($use_base_domain) {
    return $this->setValue('use_base_domain', $use_base_domain);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceUrlPermission() {
    return $this->getPermission(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function setReferenceUrlPermission($use_reference_url) {
    return $this->setValue('use_reference_url', $use_reference_url);
  }

  /**
   * Helper for getting a value from config, validating that the usage is set.
   *
   * @param string $key
   *   The key.
   *
   * @return array|mixed|null
   */
  protected function getValue($key) {
    $value = NULL;
    $method = $key;
    $permissionMethod = $key . 'Permission';
    if ($this->profile !== NULL && $this->profile->hasIntelligenceMetadataOverrides()) {
      if ($this->profile->{$permissionMethod}()) {
        $value = $this->profile->{$method}();
      }
    }
    else {
      if ($this->intelligenceConfig->{$permissionMethod}()) {
        $value = $this->intelligenceConfig->{$method}();
      }
    }
    return $value;
  }

  /**
   * Checks the permission given the overrides.
   *
   * @param string $permissionMethod
   *   The permission method being called.
   *
   * @return bool
   *   The access check result.
   */
  protected function getPermission($permissionMethod) {
    $value = NULL;
    if ($this->profile !== NULL && $this->profile->hasIntelligenceMetadataOverrides()) {
      $value = $this->profile->{$permissionMethod}();
    }
    else {
      $value = $this->intelligenceConfig->{$permissionMethod}();
    }
    return $value;
  }

  /**
   * We don't allow to store values on this service.
   *
   * @throws \BadMethodCallException
   */
  protected function setValue($key, $value) {
    throw new \BadMethodCallException();
  }

}
