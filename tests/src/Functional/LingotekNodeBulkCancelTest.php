<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkCancelTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp(): void {
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
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that a node can be cancelled using the actions on the management page.
   */
  public function testNodeCancel() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->createAndTranslateNodeWithLinks();

    $this->goToContentBulkManagementForm();

    // Mark the first for cancelling.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert that The document has been cancelled remotely.
    $cancelled_docs = \Drupal::state()->get('lingotek.cancelled_docs', []);
    $this->assertEqual(1, count($cancelled_docs), 'The document has been cancelled remotely.');

    // Assert that no document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(0, count($deleted_docs), 'No document has been deleted remotely.');

    $node = Node::load(1);
    $this->assertNull($content_translation_service->getDocumentId($node));

    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $content_translation_service->getTargetStatus($node, 'es'));

    // We can request again.
    $this->assertLingotekUploadLink();
    $this->assertNoLingotekRequestTranslationLink('es_ES');

    $this->createAndTranslateNodeWithLinks();
  }

  /**
   * Tests that a node target can be cancelled using the actions on the management page.
   */
  public function testNodeCancelTarget() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->createAndTranslateNodeWithLinks();

    $this->goToContentBulkManagementForm();

    // Mark the first for cancelling.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancelTarget('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert that the document target has been cancelled remotely.
    $cancelled_locales = \Drupal::state()->get('lingotek.cancelled_locales', []);
    $this->assertTrue(isset($cancelled_locales['dummy-document-hash-id']) && in_array('es_ES', $cancelled_locales['dummy-document-hash-id']),
      'The document target has been cancelled remotely.');

    $entity = Node::load(1);
    $this->assertEquals('dummy-document-hash-id', $content_translation_service->getDocumentId($entity));

    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($entity));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $content_translation_service->getTargetStatus($entity, 'es'));

    // We cannot request again.
    $this->assertNoLingotekRequestTranslationLink('es_ES');
  }

  protected function createAndTranslateNodeWithLinks() {
    $this->goToContentBulkManagementForm();

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
