<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek intelligence metadata settings form.
 *
 * @group lingotek
 */
class LingotekSettingsTabIntelligenceMetadataFormTest extends LingotekTestBase {

  use IntelligenceMetadataFormTestTrait;

  /**
   * Test intelligence metadata is saved.
   */
  public function testIntelligenceMetadataIsSaved() {
    $this->drupalGet('admin/lingotek/settings');

    $this->assertRaw('<summary role="button" aria-controls="edit-intelligence-metadata" aria-expanded="false" aria-pressed="false">Lingotek Intelligence Metadata</summary>',
      'Lingotek Intelligence Metadata tab is present.');

    // Assert defaults are correct.
    $this->assertIntelligenceFieldDefaults();

    // Check we can store the values.
    $this->drupalGet('admin/lingotek/settings');
    $edit = [
      'intelligence_metadata[use_author]' => TRUE,
      'intelligence_metadata[use_author_email]' => TRUE,
      'intelligence_metadata[use_contact_email_for_author]' => FALSE,
      'intelligence_metadata[use_business_unit]' => 1,
      'intelligence_metadata[use_business_division]' => 1,
      'intelligence_metadata[use_campaign_id]' => 1,
      'intelligence_metadata[use_campaign_rating]' => 1,
      'intelligence_metadata[use_channel]' => 1,
      'intelligence_metadata[use_contact_name]' => 1,
      'intelligence_metadata[use_contact_email]' => 1,
      'intelligence_metadata[use_content_description]' => 1,
      'intelligence_metadata[use_external_style_id]' => 1,
      'intelligence_metadata[use_purchase_order]' => 1,
      'intelligence_metadata[use_region]' => 1,
      'intelligence_metadata[use_base_domain]' => 1,
      'intelligence_metadata[use_reference_url]' => 1,
      'intelligence_metadata[default_author_email]' => 'test@example.com',
      'intelligence_metadata[business_unit]' => 'Test Business Unit',
      'intelligence_metadata[business_division]' => 'Test Business Division',
      'intelligence_metadata[campaign_id]' => 'Campaign ID',
      'intelligence_metadata[campaign_rating]' => 5,
      'intelligence_metadata[channel]' => 'Channel Test',
      'intelligence_metadata[contact_name]' => 'Test Contact Name',
      'intelligence_metadata[contact_email]' => 'contact@example.com',
      'intelligence_metadata[content_description]' => 'Content description',
      'intelligence_metadata[external_style_id]' => 'my-style-id',
      'intelligence_metadata[purchase_order]' => 'PO32',
      'intelligence_metadata[region]' => 'region2',
    ];
    $this->submitForm($edit, 'Save Lingotek Intelligence Metadata', 'lingotekintelligence-metadata-form');

    $this->assertText('Lingotek Intelligence Metadata saved correctly.');

    // The values shown are correct.
    $this->assertNoFieldChecked('edit-intelligence-metadata-use-contact-email-for-author');
    $this->assertFieldByName('intelligence_metadata[default_author_email]', 'test@example.com');
    $this->assertFieldByName('intelligence_metadata[business_unit]', 'Test Business Unit');
    $this->assertFieldByName('intelligence_metadata[business_division]', 'Test Business Division');
    $this->assertFieldByName('intelligence_metadata[campaign_id]', 'Campaign ID');
    $this->assertFieldByName('intelligence_metadata[campaign_rating]', 5);
    $this->assertFieldByName('intelligence_metadata[channel]', 'Channel Test');
    $this->assertFieldByName('intelligence_metadata[contact_name]', 'Test Contact Name');
    $this->assertFieldByName('intelligence_metadata[contact_email]', 'contact@example.com');
    $this->assertFieldByName('intelligence_metadata[content_description]', 'Content description');
    $this->assertFieldByName('intelligence_metadata[external_style_id]', 'my-style-id');
    $this->assertFieldByName('intelligence_metadata[purchase_order]', 'PO32');
    $this->assertFieldByName('intelligence_metadata[region]', 'region2');

    /** @var \Drupal\lingotek\LingotekIntelligenceMetadataInterface $intelligence */
    $intelligence = \Drupal::service('lingotek.intelligence');
    $this->assertTrue($intelligence->getAuthorPermission());
    $this->assertTrue($intelligence->getAuthorEmailPermission());
    $this->assertFalse($intelligence->getContactEmailForAuthorPermission());
    $this->assertTrue($intelligence->getBusinessUnitPermission());
    $this->assertTrue($intelligence->getBusinessDivisionPermission());
    $this->assertTrue($intelligence->getCampaignIdPermission());
    $this->assertTrue($intelligence->getCampaignRatingPermission());
    $this->assertTrue($intelligence->getChannelPermission());
    $this->assertTrue($intelligence->getContactNamePermission());
    $this->assertTrue($intelligence->getContactEmailPermission());
    $this->assertTrue($intelligence->getContentDescriptionPermission());
    $this->assertTrue($intelligence->getExternalStyleIdPermission());
    $this->assertTrue($intelligence->getPurchaseOrderPermission());
    $this->assertTrue($intelligence->getRegionPermission());
    $this->assertTrue($intelligence->getBaseDomainPermission());
    $this->assertTrue($intelligence->getReferenceUrlPermission());

    $this->assertIdentical($intelligence->getDefaultAuthorEmail(), 'test@example.com');
    $this->assertIdentical($intelligence->getBusinessUnit(), 'Test Business Unit');
    $this->assertIdentical($intelligence->getBusinessDivision(), 'Test Business Division');
    $this->assertIdentical($intelligence->getCampaignId(), 'Campaign ID');
    $this->assertIdentical($intelligence->getCampaignRating(), 5);
    $this->assertIdentical($intelligence->getChannel(), 'Channel Test');
    $this->assertIdentical($intelligence->getContactName(), 'Test Contact Name');
    $this->assertIdentical($intelligence->getContactEmail(), 'contact@example.com');
    $this->assertIdentical($intelligence->getContentDescription(), 'Content description');
    $this->assertIdentical($intelligence->getExternalStyleId(), 'my-style-id');
    $this->assertIdentical($intelligence->getPurchaseOrder(), 'PO32');
    $this->assertIdentical($intelligence->getRegion(), 'region2');
  }

}
