<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests debugging a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekFieldBodyBulkDebugTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'field_ui'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $type = $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

  }

  /**
   * Tests that a config can be exported using the debug options on the management page.
   */
  public function testDebugOptionsDisplay() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    // There is no 'debug' option group.
    $this->assertFalse($this->xpath('//select[@id=:id]//optgroup[@label=:label]', array(':id' => 'edit-operation', ':label' => 'debug')), 'There is no debug group.');

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], t('Enable debug operations'));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
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
    $this->drupalGet('admin/lingotek/config/manage');
    $edit = [
      'filters[wrapper][bundle]' => 'node_fields',
    ];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'debug.export'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertText('Exports available');
    // Download the file.
    $this->clickLink('config.node.article.body.json');

    $response = json_decode($this->content, true);
    $this->assertIdentical('Body', $response['field.field.node.article.body']['label']);
    $this->assertIdentical('', $response['field.field.node.article.body']['description']);
    $this->assertIdentical('node.article.body (config): Body', $response['_debug']['title']);
    $this->assertIdentical('automatic', $response['_debug']['profile']);
    $this->assertIdentical('en_US', $response['_debug']['source_locale']);
  }

}
