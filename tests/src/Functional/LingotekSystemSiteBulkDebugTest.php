<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests debugging a config object using the bulk management form.
 *
 * @group lingotek
 */
class LingotekSystemSiteBulkDebugTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  /**
   * @var \Drupal\node\Entity\NodeInterface
   */
  protected $node;

  protected function setUp(): void {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();
  }

  /**
   * Tests that a config can be exported using the debug options on the management page.
   */
  public function testDebugOptionsDisplay() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // There is no 'debug' option group.
    $this->assertEmpty($this->xpath('//select[@id=:id]//optgroup[@label=:label]', [':id' => 'edit-operation', ':label' => 'debug']), 'There is no debug group.');

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], t('Enable debug operations'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // There should be a 'debug' option group with the right operation.
    $this->assertNotEmpty($this->xpath('//select[@id=:id]//optgroup[@label=:label]', [':id' => 'edit-operation', ':label' => 'debug']), 'There is a debug group.');
    $this->assertNotEmpty($this->xpath('//select[@id=:id]//option[@value=:value]', [':id' => 'edit-operation', ':value' => 'debug.export']), 'There is a debug export option.');
  }

  public function testDebugExport() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], t('Enable debug operations'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'debug.export',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Exports available');
    // Download the file.
    $this->clickLink('config.system.site_information_settings.json');

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIdentical('Drupal', $response['system.site']['name']);
    $this->assertIdentical('', $response['system.site']['slogan']);
    $this->assertIdentical('system.site_information_settings (config): System information', $response['_debug']['title']);
    $this->assertIdentical('manual', $response['_debug']['profile']);
    $this->assertIdentical('en_US', $response['_debug']['source_locale']);
  }

}
