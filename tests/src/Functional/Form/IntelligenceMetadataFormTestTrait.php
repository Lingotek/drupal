<?php

namespace Drupal\Tests\lingotek\Functional\Form;

/**
 * Utility methods for testing the intelligence metadata forms.
 *
 * @package Drupal\Tests\lingotek\Functional\Form
 */
trait IntelligenceMetadataFormTestTrait {

  /**
   * Assert field defaults are correct.
   */
  protected function assertIntelligenceFieldDefaults() {
    $this->assertFieldChecked('edit-intelligence-metadata-use-author');
    $this->assertFieldChecked('edit-intelligence-metadata-use-author-email');
    $this->assertNoFieldChecked('edit-intelligence-metadata-use-contact-email-for-author');

    $this->assertFieldChecked('edit-intelligence-metadata-use-business-unit');
    $this->assertFieldByName('intelligence_metadata[business_unit]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-business-division');
    $this->assertFieldByName('intelligence_metadata[business_division]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-campaign-id');
    $this->assertFieldByName('intelligence_metadata[campaign_id]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-campaign-rating');
    $this->assertFieldByName('intelligence_metadata[campaign_rating]', '0');

    $this->assertFieldChecked('edit-intelligence-metadata-use-channel');
    $this->assertFieldByName('intelligence_metadata[channel]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-contact-name');
    $this->assertFieldByName('intelligence_metadata[contact_name]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-contact-email');
    $this->assertFieldByName('intelligence_metadata[contact_email]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-content-description');
    $this->assertFieldByName('intelligence_metadata[content_description]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-base-domain');
    $this->assertFieldChecked('edit-intelligence-metadata-use-reference-url');

    $this->assertFieldChecked('edit-intelligence-metadata-use-external-style-id');
    $this->assertFieldByName('intelligence_metadata[external_style_id]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-purchase-order');
    $this->assertFieldByName('intelligence_metadata[purchase_order]', '');

    $this->assertFieldChecked('edit-intelligence-metadata-use-region');
    $this->assertFieldByName('intelligence_metadata[region]', '');
  }

}
