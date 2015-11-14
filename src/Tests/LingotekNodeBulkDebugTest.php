<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests debugging a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkDebugTest extends LingotekTestBase {

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

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testDebugOptionsDisplay() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');

    // There is no 'debug' option group.
    $this->assertFalse($this->xpath('//select[@id=:id]//optgroup[@label=:label]', array(':id' => 'edit-operation', ':label' => 'debug')), 'There is no debug group.');

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], t('Enable debug operations'));

    // Back to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');
    // There should be a 'debug' option group with the right operation.
    $this->assertTrue($this->xpath('//select[@id=:id]//optgroup[@label=:label]', array(':id' => 'edit-operation', ':label' => 'debug')), 'There is a debug group.');
    $this->assertTrue($this->xpath('//select[@id=:id]//option[@value=:value]', array(':id' => 'edit-operation', ':value' => 'debug.export')), 'There is a debug export option.');
  }

  public function testDebugExport() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], t('Enable debug operations'));

    // Go to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');

    $edit = [
      'table[1]' => TRUE,  // Node 1.
      'operation' => 'debug.export'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertText('Exports available');
    // Download the file.
    $this->clickLink('node.article.1.json');

    $response = json_decode($this->content, true);
    $this->assertIdentical('Llamas are cool', $response['title'][0]['value']);
    $this->assertIdentical('Llamas are very cool', $response['body'][0]['value']);
    $this->assertIdentical('article (node): Llamas are cool', $response['_debug']['title']);
    $this->assertIdentical('manual', $response['_debug']['profile']);
    $this->assertIdentical('en_US', $response['_debug']['source_locale']);
  }

}
