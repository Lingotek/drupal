<?php

namespace Drupal\lingotek\Tests;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkDisassociateTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

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
  public function testNodeDisassociate() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->createAndTranslateNodeWithLinks();

    $this->goToContentBulkManagementForm();

    // Mark the first two for disassociation.
    $edit = [
      'table[1]' => TRUE,  // Node 1.
      'operation' => 'disassociate'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $node = Node::load(1);

    /** @var LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert that no document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(0, count($deleted_docs), 'No document has been deleted remotely because the module is not configured to perform the operation.');

    $this->assertNull($content_translation_service->getDocumentId($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));

    // We can request again.
    $this->createAndTranslateNodeWithLinks();
  }

  /**
   * Tests that a node can be disassociated and the remote document will be removed.
   */
  public function testNodeDisassociateWithRemovalOfRemoteDocument() {
    // Enable remote delete while disassociating.
    $edit = [
      'delete_tms_documents_upon_disassociation' => TRUE,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-preferences-form');

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->createAndTranslateNodeWithLinks();

    $this->goToContentBulkManagementForm();

    // Mark the first two for disassociation.
    $edit = [
      'table[1]' => TRUE,  // Node 1.
      'operation' => 'disassociate'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $node = Node::load(1);

    // Assert that the document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(1, count($deleted_docs), 'The document has been deleted remotely.');


    /** @var LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    $this->assertNull($content_translation_service->getDocumentId($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));

    // We can request again.
    $this->createAndTranslateNodeWithLinks();
  }

  protected function createAndTranslateNodeWithLinks() {
    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');

    // There is a link for checking status.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Locale 'es_ES' was added as a translation target for node Llamas are cool.");

    // Check status of the Spanish translation.
    $this->clickLink('ES');
    $this->assertText('The es_ES translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_ES has been downloaded.');
  }

}
