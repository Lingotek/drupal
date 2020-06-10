<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the module can be uninstalled.
 *
 * @group lingotek
 */
class LingotekModuleUninstallationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'lingotek'];

  protected function setUp(): void {
    parent::setUp();
    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);
  }

  /**
   * Tests that the module can be enabled.
   */
  public function testUninstallModule() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);
    // Navigate to the Extend page.
    $this->drupalGet('/admin/modules');

    // Ensure the module is not enabled yet.
    $this->assertSession()->checkboxChecked('edit-modules-lingotek-enable');

    $this->clickLink('Uninstall');

    // Post the form uninstalling the lingotek module.
    $edit = ['uninstall[lingotek]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Uninstall');

    // We get an advice and we can confirm.
    $this->assertText('The following modules will be completely uninstalled from your site, and all data from these modules will be lost!');
    $this->assertSession()->responseContains('The listed configuration will be deleted.');
    $this->assertSession()->responseContains('Lingotek Profile');

    $this->drupalPostForm(NULL, [], 'Uninstall');

    $this->assertSession()->responseContains('The selected modules have been uninstalled.');
  }

}
