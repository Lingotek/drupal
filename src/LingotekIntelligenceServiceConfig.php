<?php

namespace Drupal\lingotek;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for managing Lingotek Intelligence related configuration.
 *
 * @package Drupal\lingotek
 */
class LingotekIntelligenceServiceConfig implements LingotekIntelligenceMetadataInterface, ContainerInjectionInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a LingotekIntelligenceService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBusinessUnit() {
    return $this->getValue('business_unit');
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
    return $this->getValue('business_division');
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
    return $this->getValue('campaign_id');
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
    return $this->getValue('campaign_rating');
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
    return $this->getValue('channel');
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
    return $this->getValue('contact_name');
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
    return $this->getValue('contact_email');
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
    return $this->getValue('content_description');
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
    return $this->getValue('purchase_order');
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
    return $this->getValue('external_style_id');
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
    return $this->getValue('region');
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
    $value = NULL;
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_author');
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
    $value = NULL;
    $config = $this->configFactory->get('lingotek.settings');
    if ($config->get('intelligence.use_author_email')) {
      $value = $config->get('intelligence.default_author_email');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_author_email');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_contact_email_for_author');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_business_unit');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_business_division');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_campaign_id');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_campaign_rating');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_channel');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_contact_name');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_contact_email');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_content_description');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_external_style_id');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_purchase_order');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_region');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_base_domain');
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
    $config = $this->configFactory->get('lingotek.settings');
    return $config->get('intelligence.use_reference_url');
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
    $config = $this->configFactory->get('lingotek.settings');
    if ($config->get('intelligence.use_' . $key)) {
      $value = $config->get('intelligence.' . $key);
    }
    return $value;
  }

  /**
   * Helper for setting a value to config.
   *
   * @param string $key
   *   The key.
   * @param $value
   *   The value.
   *
   * @return $this
   */
  protected function setValue($key, $value) {
    $config = $this->configFactory->getEditable('lingotek.settings');
    $config->set('intelligence.' . $key, $value);
    $config->save();
    return $this;
  }

}
