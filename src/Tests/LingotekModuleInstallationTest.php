<?php

/**
 * @file
 * Contains \Drupal\lingotek\Tests\LingotekModuleInstallationTest.
 */

namespace Drupal\lingotek\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests that the module can be enabled.
 *
 * @group lingotek
 */
class LingotekModuleInstallationTest extends WebTestBase {

  /**
   * Tests that the module can be enabled.
   */
  public function testEnableModule() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Navigate to the Extend page.
    $this->drupalGet('/admin/modules');

    // Ensure the module is not enabled yet.
    $this->assertNoFieldChecked('edit-modules-multilingual-lingotek-enable');

    // Post the form enabling the lingotek module.
    $edit = ['modules[Multilingual][lingotek][enable]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    // Dependencies installation is requested.
    $this->assertText('Some required modules must be enabled');
    $this->drupalPostForm(NULL, [], 'Continue');

    // The module is enabled successfully.
    $this->assertText('The configuration options have been saved.');
  }

}
