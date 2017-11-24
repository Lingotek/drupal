<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek account form.
 *
 * @group lingotek
 */
class LingotekAccountFormTest extends LingotekTestBase {

  /**
   * Test that we can disconnect.
   */
  public function testAccountDetails() {
    $this->drupalGet('admin/lingotek/settings');

    $xpath = $this->xpath('//details[@data-drupal-selector="edit-account"]//tr[@data-drupal-selector="edit-account-table-status-row"]//td[text()="Active"]');
    $this->assertIdentical(1, count($xpath), 'Status indicator found');

    $xpath = $this->xpath('//details[@data-drupal-selector="edit-account"]//tr[@data-drupal-selector="edit-account-table-plan-row"]//td[text()="No"]');
    $this->assertIdentical(1, count($xpath), 'Enterprise plan indicator found');

    $xpath = $this->xpath('//details[@data-drupal-selector="edit-account"]//tr[@data-drupal-selector="edit-account-table-activation-row"]//td[text()="testUser@example.com"]');
    $this->assertIdentical(1, count($xpath), 'Activation Name found');
  }

}
