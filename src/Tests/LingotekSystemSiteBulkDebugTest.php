<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

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
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
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
    $this->assertFalse($this->xpath('//select[@id=:id]//optgroup[@label=:label]', array(':id' => 'edit-operation', ':label' => 'debug')), 'There is no debug group.');

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], t('Enable debug operations'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // There should be a 'debug' option group with the right operation.
    $this->assertTrue($this->xpath('//select[@id=:id]//optgroup[@label=:label]', array(':id' => 'edit-operation', ':label' => 'debug')), 'There is a debug group.');
    $this->assertTrue($this->xpath('//select[@id=:id]//option[@value=:value]', array(':id' => 'edit-operation', ':value' => 'debug.export')), 'There is a debug export option.');
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
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'debug.export'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertText('Exports available');
    // Download the file.
    $this->clickLink('config.system.site_information_settings.json');

    $response = json_decode($this->content, true);
    $this->assertIdentical('Drupal', $response['system.site']['name']);
    $this->assertIdentical('', $response['system.site']['slogan']);
    $this->assertIdentical('system.site_information_settings (config): System information', $response['_debug']['title']);
    $this->assertIdentical('<null>', $response['_debug']['profile']);
    $this->assertIdentical('en_US', $response['_debug']['source_locale']);
  }

}
