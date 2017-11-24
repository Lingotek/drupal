<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for updating the module weight.
 *
 * @group lingotek
 */
class LingotekIntelligenceMetadataUpdate8204Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8201.php.gz',
    ];
  }

  /**
   * Tests that the module weight update is executed correctly.
   */
  public function testIntelligenceMetadataUpdate() {
    $this->runUpdates();

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('lingotek.settings');

    $this->assertNull($config->get('intelligence.business_unit'), 'Business Unit is set to null');
    $this->assertNull($config->get('intelligence.business_division'), 'Business Division is set to null');
    $this->assertNull($config->get('intelligence.campaign_id'), 'Campaign ID is set to null');
    $this->assertIdentical(0, $config->get('intelligence.campaign_rating'), 'Campaign Rating is set to 0');
    $this->assertNull($config->get('intelligence.channel'), 'Channel is set to null');
    $this->assertNull($config->get('intelligence.contact_name'), 'Contact Name is set to null');
    $this->assertNull($config->get('intelligence.contact_email'), 'Contact Email is set to null');
    $this->assertNull($config->get('intelligence.content_description'), 'Content Description is set to null');
    $this->assertNull($config->get('intelligence.external_style_id'), 'External Style ID is set to null');
    $this->assertNull($config->get('intelligence.purchase_order'), 'Purchase Order is set to null');
    $this->assertNull($config->get('intelligence.region'), 'Region is set to null');
    $this->assertTrue($config->get('intelligence.use_author'), 'Use Author Permission is set to true');
    $this->assertNull($config->get('intelligence.default_author_email'), 'Default Author Email is set to null');
    $this->assertTrue($config->get('intelligence.use_author_email'), 'Use Author Email Permission is set to true');
    $this->assertFalse($config->get('intelligence.use_contact_email_for_author'), 'Use Contact Author for Author Permission is set to false');
    $this->assertTrue($config->get('intelligence.use_business_unit'), 'Use Business Unit Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_business_division'), 'Use Business Division Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_campaign_id'), 'Use Campaign ID Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_campaign_rating'), 'Use Campaign Rating Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_channel'), 'Use Channel Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_contact_name'), 'Use Contact Name Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_contact_email'), 'Use Contact Email Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_content_description'), 'Use Content Description Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_external_style_id'), 'Use External Style ID Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_purchase_order'), 'Use Purchase Order Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_region'), 'Use Region Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_base_domain'), 'Use Base Domain Permission is set to true');
    $this->assertTrue($config->get('intelligence.use_reference_url'), 'Use Reference URL Permission is set to true');
  }

}
