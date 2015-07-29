<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Language\LanguageManager;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a node.
 *
 * @group lingotek
 */
class LingotekNodeTranslation extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that we can upload the document to Lingotek.
    $this->clickLink('Upload');
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node #1 is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for node #1.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for node #1 is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node #1 into es_ES has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

}