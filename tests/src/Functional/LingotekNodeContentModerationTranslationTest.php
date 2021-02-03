<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests translating a node with content moderation enabled.
 *
 * @group lingotek
 */
class LingotekNodeContentModerationTranslationTest extends LingotekTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'content_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page', 'new_revision' => FALSE]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Enable content moderation for articles.
    $workflow = $this->createEditorialWorkflow();
    $this->configureContentModeration('editorial', ['node' => ['article']]);

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article', 'page']);
  }

  /**
   * Tests that new revisions are created when processing with Lingotek.
   */
  public function testNewRevisionCreatedWhenProcessing() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    $this->clickLink('Llamas are cool');

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEqual(1, $result, 'Only one revision is stored.');

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');

    // Open the node.
    $this->clickLink('Llamas are cool');

    // There is a revisions tab as the translation creates a new revision.
    $assert_session->linkExists('Revisions');
    $this->clickLink('Revisions');
    $this->drupalGet('es/node/1/revisions');
    $this->assertText('Document translated into ES by Lingotek.');

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEqual(2, $result, 'A new revision is stored.');
  }

  /**
   * Tests that new revisions are created when processing with Lingotek.
   */
  public function testNoNewRevisionCreatedWhenProcessing() {
    $this->drupalGet('admin/structure/types/manage/page');

    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, 'page');

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    $this->clickLink('Llamas are cool');

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEqual(1, $result, 'Only one revision is stored.');

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');

    // Open the node.
    $this->clickLink('Llamas are cool');

    // There is a revisions tab as the translation creates a new revision.
    $assert_session->linkNotExists('Revisions');

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEqual(1, $result, 'A new revision has not been stored.');
  }

}
