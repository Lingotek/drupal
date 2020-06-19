<?php

namespace Drupal\lingotek;

/**
 * Contract for any Lingotek Intelligence metadata provider.
 *
 * @package Drupal\lingotek
 */
interface LingotekIntelligenceMetadataInterface {

  /**
   * Gets the Business Unit associated with this content.
   *
   * @return string
   *   The Business Unit.
   */
  public function getBusinessUnit();

  /**
   * Sets the Business Unit responsible for the content with this metadata.
   * A business unit is a relatively autonomous division of a large company that
   * operates as an independent enterprise with responsibility for a particular
   * range of products or activities.
   *
   * Although, defined as such the user may define and use this field as best
   * fits the situation.
   *
   * @param string $business_unit
   *   The Business Unit.
   *
   * @return $this
   */
  public function setBusinessUnit($business_unit);

  /**
   * Gets the Business Division associated with this content.
   *
   * @return string
   */
  public function getBusinessDivision();

  /**
   * Set the Business Division responsible for the content with this metadata.
   * A Business Division is defined as a discrete part of a company that may
   * operate under the same name and legal responsibility or as a separate
   * corporate and legal entity under another business name.
   *
   * This may be used as best fits the needs of the user.
   *
   * @param string $business_division
   *   The Business Division.
   *
   * @return $this
   */
  public function setBusinessDivision($business_division);

  /**
   * Get the Campaign ID associated with this content.
   *
   * @return string
   */
  public function getCampaignId();

  /**
   * Set the Campaign ID associated with this content. The Campaign ID could be
   * for a marketing or other campaign. This allows particular content to be
   * associated with the campaign and then be able to see how a campaign is
   * doing by only looking at content from the campaign.
   *
   * @param string $campaign_id
   *   The Campaign ID.
   *
   * @return $this
   */
  public function setCampaignId($campaign_id);

  /**
   * Get the Campaign Rating associated with the content and the Campaign (which
   * is represented by the Campaign ID).
   *
   * @return int
   */
  public function getCampaignRating();

  /**
   * Sets the Campaign Rating. The Campaign rating must be numeric, but can
   * otherwise be used to rate the campaign and its effect on this content.
   *
   * @param int $campaign_rating
   *   The Campaign Rating.
   *
   * @return $this
   */
  public function setCampaignRating($campaign_rating);

  /**
   * Gets the Channel associated with the content.
   *
   * @return string
   */
  public function getChannel();

  /**
   * Sets the Channel associated with the content. A channel is a way or outlet
   * to market and sell products. This can be used to associate the content with
   * a particular marketing channel.
   *
   * @param string $channel
   *   The Channel.
   *
   * @return $this
   */
  public function setChannel($channel);

  /**
   * Gets the name of the contact responsible for this content.
   *
   * @return string
   */
  public function getContactName();

  /**
   * Sets the name of the person to contact in regards to this content.
   *
   * @param string $contact_name
   *   The Contact Name.
   *
   * @return $this
   */
  public function setContactName($contact_name);

  /**
   * Gets the Contact Email for the Contact Person responsible for this content.
   *
   * @return string
   */
  public function getContactEmail();

  /**
   * Sets the Contact Email for the Contact Person responsible for this content.
   *
   * @param string $contact_email
   *   The Contact Email.
   *
   * @return $this
   */
  public function setContactEmail($contact_email);

  /**
   * Gets the description of this content.
   *
   * @return string
   */
  public function getContentDescription();

  /**
   * Sets the description for this content.
   *
   * @param string $content_description
   *   The Content Description.
   *
   * @return $this
   */
  public function setContentDescription($content_description);

  /**
   * Gets the Purchase Order.
   *
   * @return string
   */
  public function getPurchaseOrder();

  /**
   * Sets the Purchase Order associated with the purchase of the translation.
   *
   * @param string $purchase_order
   *   The Purchase Order.
   *
   * @return $this
   */
  public function setPurchaseOrder($purchase_order);

  /**
   * Gets the External Style ID associated with this content.
   *
   * @return string
   */
  public function getExternalStyleId();

  /**
   * Sets the External Style ID that is associated with the marketing and style
   * the content is using.
   *
   * @param string $external_style_id
   *   The External Style ID.
   *
   * @return $this
   */
  public function setExternalStyleId($external_style_id);

  /**
   * Gets the Region the content is presented to.
   *
   * @return string
   */
  public function getRegion();

  /**
   * Sets the Region the content is meant for, presented to, or created in.
   *
   * @param string $region
   *   The Region.
   *
   * @return $this
   */
  public function setRegion($region);

  /**
   * Gets the Permission setting for Author Permission.
   *
   * @return bool
   */
  public function getAuthorPermission();

  /**
   * Sets the Permission setting for whether or not the author information
   * should be sent.
   *
   * @param bool $use_author
   *   Flag indicating if the author should be used.
   *
   * @return $this
   */
  public function setAuthorPermission($use_author);

  /**
   * Gets the Default Author Email.
   *
   * @return bool
   */
  public function getDefaultAuthorEmail();

  /**
   * Sets the Default Author Email that should be used.
   *
   * @param string $default_author_email
   *   The Default Author Email.
   *
   * @return $this
   */
  public function setDefaultAuthorEmail($default_author_email);

  /**
   * Gets the Permission setting for whether or not the Author Email should be
   * sent.
   *
   * @return bool
   */
  public function getAuthorEmailPermission();

  /**
   * Sets the Permission setting for whether or not an Author Email should be
   * used in the Intelligence Metadata.
   *
   * @param bool $use_author_email
   *   Flag indicating if the author email should be used.
   *
   * @return $this
   */
  public function setAuthorEmailPermission($use_author_email);

  /**
   * Gets the Permission setting for whether or not to use the Contact Email
   * as the author's email.
   *
   * @return bool
   */
  public function getContactEmailForAuthorPermission();

  /**
   * Sets the Permission setting for whether or not to use the Contact Email
   * as the author's email.
   *
   * @param bool $use_contact_email_for_author
   *   Flag indicating if we want to use contact email as author if author
   *   is not set.
   *
   * @return $this
   */
  public function setContactEmailForAuthorPermission($use_contact_email_for_author);

  /**
   * Gets the Permission setting for wheter or not to use the Business Unit.
   *
   * @return bool
   */
  public function getBusinessUnitPermission();

  /**
   * Sets the Permission setting for whether or not to use the Business Unit.
   *
   * @param bool $use_business_unit
   *   Flag indicating if we want to use a Business Unit.
   *
   * @return $this
   */
  public function setBusinessUnitPermission($use_business_unit);

  /**
   * Gets the Permission setting for whether or not to use the Business Division.
   *
   * @return bool
   */
  public function getBusinessDivisionPermission();

  /**
   * Sets the Permission setting for whether or not to use the Business Division.
   *
   * @param bool $use_business_division
   *   Flag indicating if we want to indicate the Business Division.
   *
   * @return $this
   */
  public function setBusinessDivisionPermission($use_business_division);

  /**
   * Gets the Permission setting for whether or not to use the Campaign Id.
   *
   * @return bool
   */
  public function getCampaignIdPermission();

  /**
   * Sets the Permission setting for whether or not to use the Campaign Id.
   *
   * @param bool $use_campaign_id
   *   Flag indicating if we want to indicate the Campaign ID.
   *
   * @return $this
   */
  public function setCampaignIdPermission($use_campaign_id);

  /**
   * Gets the Permission setting for whether or not the Campaign Rating should
   * be used and tracked.
   *
   * @return bool
   */
  public function getCampaignRatingPermission();

  /**
   * Sets the Permission setting for whether or not the Campaign Rating should
   * be used and tracked.
   *
   * @param bool $use_campaign_rating
   *   Flag indicating if we want to indicate the Campaign Rating.
   *
   * @return $this
   */
  public function setCampaignRatingPermission($use_campaign_rating);

  /**
   * Gets the Permission setting for whether or not the Channel should be used
   * and tracked.
   *
   * @return bool
   */
  public function getChannelPermission();

  /**
   * Sets the Permission setting for whether or not the Channel should be used
   * and tracked.
   *
   * @param bool $use_channel
   *   Flag indicating if we want to indicate the Channel.
   *
   * @return $this
   */
  public function setChannelPermission($use_channel);

  /**
   * Gets the Permission setting for whether or not to include the Contact Name.
   *
   * @return bool
   */
  public function getContactNamePermission();

  /**
   * Sets the Permission setting for whether or not to include the Contact Name.
   *
   * @param bool $use_contact_name
   *   Flag indicating if we want to indicate the Contact Name.
   *
   * @return $this
   */
  public function setContactNamePermission($use_contact_name);

  /**
   * Gets the Permission setting for whether or not to include the Contact Email.
   *
   * @return bool
   */
  public function getContactEmailPermission();

  /**
   * Sets the Permission setting for whether or not to include the Contact Email.
   *
   * @param bool $use_contact_email
   *   Flag indicating if we want to indicate the Contact Email.
   *
   * @return $this
   */
  public function setContactEmailPermission($use_contact_email);

  /**
   * Gets the Permission setting for whether or not to include the Content
   * Description.
   *
   * @return bool
   */
  public function getContentDescriptionPermission();

  /**
   * Sets the Permission setting for whether or not to include the Content
   * Description.
   *
   * @param bool $use_content_description
   *   Flag indicating if we want to indicate the Content Description.
   *
   * @return $this
   */
  public function setContentDescriptionPermission($use_content_description);

  /**
   * Gets the Permission setting for whether or not to include the External
   * Style Id.
   *
   * @return bool
   */
  public function getExternalStyleIdPermission();

  /**
   * Sets the Permission setting for whether or not to include the External
   * Style Id.
   *
   * @param bool $use_external_style_id
   *   Flag indicating if we want to indicate the External Style ID.
   *
   * @return $this
   */
  public function setExternalStyleIdPermission($use_external_style_id);

  /**
   * Gets the Permission setting for whether or not to include the Purchase
   * Order.
   *
   * @return bool
   */
  public function getPurchaseOrderPermission();

  /**
   * Sets the Permission setting for whether or not to include the Purchase
   * Order.
   *
   * @param bool $use_purchase_order
   *   Flag indicating if we want to indicate the Purchase Order.
   *
   * @return $this
   */
  public function setPurchaseOrderPermission($use_purchase_order);

  /**
   * Gets the Permission setting for whether or not to include the Region.
   *
   * @return bool
   */
  public function getRegionPermission();

  /**
   * Sets the Permission setting for whether or not to include the Region.
   *
   * @param bool $use_region
   *   Flag indicating if we want to indicate the Region.
   *
   * @return $this
   */
  public function setRegionPermission($use_region);

  /**
   * Gets the Permission setting for whether or not to include the Base Domain
   * in the metadata.
   *
   * @return bool
   */
  public function getBaseDomainPermission();

  /**
   * Sets the Permission setting for whether or not to include the Base Domain
   * in the metadata.
   *
   * @param bool $use_base_domain
   *   Flag indicating if we want to indicate the Base Domain.
   *
   * @return $this
   */
  public function setBaseDomainPermission($use_base_domain);

  /**
   * Gets the Permission setting for whether or not the Reference URL for this
   * content should be included in the metadata.
   *
   * @return bool
   */
  public function getReferenceUrlPermission();

  /**
   * Sets the Permission setting for whether or not the Reference URL for this
   * content should be included in the metadata.
   *
   * @param bool $use_reference_url
   *   Flag indicating if we want to indicate the Reference URL.
   *
   * @return $this
   */
  public function setReferenceUrlPermission($use_reference_url);

}
