<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the module can be enabled.
 *
 * @group lingotek
 */
class LingotekModuleInstallationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the module can be enabled.
   */
  public function testEnableModule() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Navigate to the Extend page.
    $this->drupalGet('/admin/modules');

    // Ensure the module is not enabled yet.
    $this->assertNoFieldChecked('edit-modules-lingotek-enable');

    // Post the form enabling the lingotek module.
    $edit = ['modules[lingotek][enable]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Install');

    // Dependencies installation is requested.
    $this->assertText('Some required modules must be enabled');
    $this->drupalPostForm(NULL, [], 'Continue');

    // The module is enabled successfully with its dependencies.
    $this->assertText('modules have been enabled: Lingotek Translation');
  }

  /**
   * Tests that the weight of the module is higher than content_translation.
   */
  public function testModuleWeightAgainstContentTranslation() {
    $this->testEnableModule();
    $extension_config = $this->config('core.extension');
    $content_translation_weight = $extension_config->get('module.content_translation');
    $lingotek_weight = $extension_config->get('module.lingotek');

    $this->assertTrue($lingotek_weight > $content_translation_weight, 'Lingotek weight is higher than content_translation.');
  }

}
