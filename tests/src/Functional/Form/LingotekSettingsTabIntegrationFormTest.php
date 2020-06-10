<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek integrations settings form.
 *
 * @group lingotek
 */
class LingotekSettingsTabIntegrationFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test that if there are no integration settings, there is no tab at all.
   */
  public function testTabNotShownIfThereAreNoSettings() {
    $this->drupalGet('admin/lingotek/settings');
    $this->assertNoText('Integrations Settings');
  }

}
