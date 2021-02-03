<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests translating a node.
 *
 * @group lingotek
 */
class LingotekNodeTranslationTest extends LingotekTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'image', 'frozenintime'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Create Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }
    $this->createImageField('field_image', 'article');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

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

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'field_image' => ['alt'],
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $test_image = current($this->getTestFiles('image'));

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['files[field_image_0]'] = \Drupal::service('file_system')->realpath($test_image->uri);

    $this->drupalPostForm('node/add/article', $edit, t('Preview'));

    unset($edit['files[field_image_0]']);
    $edit['field_image[0][alt]'] = 'Llamas are cool';
    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_image'][0]));
    $this->assertTrue(isset($data['field_image'][0]['alt']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_IMPORTING, $source_status, 'The node has been marked as importing.');

    // Assert the updated time and uploaded time have expected values
    $metadata = $this->node->lingotek_metadata->entity;
    $expected_time = \Drupal::time()->getRequestTime();
    $this->assertEmpty($metadata->getLastUpdated());
    $this->assertEquals($expected_time, $metadata->getLastUploaded());

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Assert the link keeps the language.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertNoLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'node', 'es');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that a node cannot be translated if not configured, and will provide user-friendly messages.
   */
  public function testNodeTranslationMessageWhenBundleNotConfigured() {
    $assert_session = $this->assertSession();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Pages are cool';
    $edit['body[0][value]'] = 'Pages are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContains('Cannot upload Page Pages are cool. That Content type is not enabled for Lingotek translation.');
    $assert_session->pageTextContains('Uploaded 0 documents to Lingotek.');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithManualTranslation() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $test_image = current($this->getTestFiles('image'));

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['files[field_image_0]'] = \Drupal::service('file_system')->realpath($test_image->uri);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';

    $this->drupalPostForm('node/add/article', $edit, t('Preview'));

    unset($edit['files[field_image_0]']);
    $edit['field_image[0][alt]'] = 'Llamas are cool';
    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node has been marked as untracked.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_image'][0]));
    $this->assertTrue(isset($data['field_image'][0]['alt']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The automatic profile was used.');

    // The document should have been uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Assert the link keeps the language.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertNoLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'node', 'es');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    $assert_session->linkNotExists('Download completed translation');
    $assert_session->linkExists('Re-download completed translation');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that a node can be translated after edited.
   */
  public function testEditedNodeTranslation() {
    // We need a node with translations first.
    $this->testNodeTranslation();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['body[0][value]'] = 'Llamas are very cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    // Assert the updated time and uploaded time are, in this case, equal
    $this->node = $this->resetStorageCachesAndReloadNode();
    $metadata = $this->node->lingotek_metadata->entity;
    $expected_time = \Drupal::time()->getRequestTime();
    $this->assertEquals($expected_time, $metadata->getLastUpdated());
    $this->assertEquals($expected_time, $metadata->getLastUploaded());

    $this->clickLink('Translate');

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $this->assertLingotekRequestTranslationLink('eu_ES', 'dummy-document-hash-id-1');
    $this->assertNoLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id-1');

    // Recheck status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_MX translation for node Llamas are cool EDITED is ready for download.');

    // Download the translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool EDITED into es_MX has been downloaded.');

    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that a node is correctly translated after body is deleted.
   */
  public function testEditedNodeTranslationWhenBodyRemoved() {
    // We need a node with translations first.
    $this->testNodeTranslation();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['body[0][value]'] = '';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    $this->clickLink('Translate');

    // Re-upload.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertTrue(isset($data['body']));
    $this->assertEmpty(count($data['body']));

    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+emptybody');

    // Recheck status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_MX translation for node Llamas are cool EDITED is ready for download.');

    // Download the translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool EDITED into es_MX has been downloaded.');

    $this->clickLink('Las llamas son chulas EDITADO');
    $this->assertNoText('Las llamas son muy chulas');
  }

  /**
   * Test that when a node is uploaded in a different locale that locale is used.
   */
  public function testAddingContentInDifferentLocale() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool es-MX';
    $edit['body[0][value]'] = 'Llamas are very cool es-MX';
    $edit['langcode[0][value]'] = 'es';
    $this->saveAndPublishNodeForm($edit);

    $this->assertText('Llamas are cool es-MX sent to Lingotek successfully.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.uploaded_locale'));
  }

  /**
   * Test that when a node is created we cannot assign a profile if using a restricted user.
   */
  public function testCannotAssignProfileToContentWithoutRightPermission() {
    $editor = $this->drupalCreateUser(['bypass node access']);
    // Login as editor.
    $this->drupalLogin($editor);
    // Get the node form.
    $this->drupalGet('node/add/article');
    // Assert translation profile cannot be assigned.
    $this->assertNoField('lingotek_translation_management[lingotek_translation_profile]');

    $translation_manager = $this->drupalCreateUser([
      'bypass node access',
      'assign lingotek translation profiles',
    ]);
    // Login as translation manager.
    $this->drupalLogin($translation_manager);
    // Get the node form.
    $this->drupalGet('node/add/article');
    // Assert translation profile can be assigned.
    $this->assertField('lingotek_translation_management[lingotek_translation_profile]');
  }

  /**
   * Tests that no translation can be requested if the language is disabled.
   */
  public function testLanguageDisabled() {
    $assert_session = $this->assertSession();
    // Add a language.
    $italian = ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT');
    $italian->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // There are two links for requesting translations, or we can add them
    // manually.
    $this->assertLingotekRequestTranslationLink('it_IT');
    $this->assertLingotekRequestTranslationLink('es_MX');
    $assert_session->linkByHrefExists('/node/1/translations/add/en/it');
    $assert_session->linkByHrefExists('/node/1/translations/add/en/es');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1/translations');

    // Italian is not present anymore, but still can add a translation.
    $this->assertNoLingotekRequestTranslationLink('it_IT');
    $this->assertLingotekRequestTranslationLink('es_MX');
    $assert_session->linkByHrefExists('/node/1/translations/add/en/it');
    $assert_session->linkByHrefExists('/node/1/translations/add/en/es');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    $metadata = $this->node->lingotek_metadata->entity;
    $expected_time = \Drupal::time()->getRequestTime();
    $this->assertEmpty($metadata->getLastUploaded());
    $this->assertEmpty($metadata->getLastUpdated());

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    $this->node = $this->resetStorageCachesAndReloadNode();
    $metadata = $this->node->lingotek_metadata->entity;
    $this->assertEquals($expected_time, $metadata->getLastUploaded());
    $this->assertEmpty($metadata->getLastUpdated());
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that the upload succeeded.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // Go back to the form.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('The update for node Llamas are cool EDITED failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAPaymentRequiredError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that the upload succeeded.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // Go back to the form.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that the upload succeeded.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // Go back to the form.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Document node Llamas are cool EDITED has been archived. Please upload again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node has been marked as untracked, needs new upload.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithADocumentArchivedErrorViaAutomaticUpload() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // The document was updated automatically and failed.
    $this->assertText('Document node Llamas are cool EDITED has been archived. Please upload again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node has been marked as untracked, needs new upload.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that the upload succeeded.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // Go back to the form.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Document node Llamas are cool EDITED has a new version. The document id has been updated for all future interactions. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node has been marked as edited, needs new upload.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithADocumentLockedErrorViaAutomaticUpload() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // The document was updated automatically and failed.
    $this->assertText('Document node Llamas are cool EDITED has a new version. The document id has been updated for all future interactions. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node has been marked as edited, needs new upload.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorViaAutomaticUpload() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    // The document was uploaded automatically and failed.
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredErrorViaAutomaticUpload() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    // The document was uploaded automatically and failed.
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAnErrorViaAutomaticUpload() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // The document was updated automatically and failed.
    $this->assertText('The update for node Llamas are cool EDITED failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAPaymentRequiredErrorViaAutomaticUpload() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // The document was updated automatically and failed.
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');
  }

  /**
   * Test that we handle errors in request translation.
   */
  public function testRequestTranslationWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertText('The translation request for node failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_CURRENT, $source_status, 'The node has not been marked as error.');
  }

  /**
   * Test that we handle errors in request translation.
   */
  public function testRequestTranslationWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');

    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual($source_status, Lingotek::STATUS_CURRENT);
    $this->assertEqual($translation_service->getTargetStatus($this->node, 'ES'), Lingotek::STATUS_UNTRACKED);
  }

  /**
   * Test that we handle errors in request translation.
   */
  public function testRequestTranslationWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');

    $this->assertText('Document node Llamas are cool has been archived. Please upload again.');

    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual($source_status, Lingotek::STATUS_UNTRACKED);
    $this->assertEqual($translation_service->getTargetStatus($this->node, 'ES'), Lingotek::STATUS_UNTRACKED);
  }

  /**
   * Test that we handle errors in request translation.
   */
  public function testRequestTranslationWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');

    $this->assertText('Document node Llamas are cool has a new version. The document id has been updated for all future interactions. Please try again.');

    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual($source_status, Lingotek::STATUS_CURRENT);
    $this->assertEqual($translation_service->getTargetStatus($this->node, 'ES'), Lingotek::STATUS_UNTRACKED);
  }

  /**
   * Tests downloading a translation for an invalid revision.
   */
  public function testDownloadingInvalidRevision() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+invalidrevision');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->clickLink('Translate');
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-MX',
        'locale' => 'es_MX',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');
    $this->assertEqual('Document downloaded.', $response['messages'][0]);

    $this->drupalGet('node/1/translations');
    $this->assertText('Las llamas son chulas');
  }

  /**
   * Tests that the node operations are as expected.
   */
  public function testContentFormOperations() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that we can add a translation for Spanish when content is not
    // uploaded.
    $this->assertSession()->linkExists('Add');
    $this->assertSession()->linkByHrefExists('/es/node/1/translations/add/en/es');

    // Upload the document.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Uploaded 1 document to Lingotek.');

    // Check that we can add a translation for Spanish when content is just
    // uploaded.
    $this->assertSession()->linkExists('Add');
    $this->assertSession()->linkByHrefExists('/es/node/1/translations/add/en/es');

    // The document should have been uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Check that we can add a translation for Spanish when content is correctly
    // uploaded.
    $this->assertSession()->linkExists('Add');
    $this->assertSession()->linkByHrefExists('/es/node/1/translations/add/en/es');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertSession()->pageTextContains("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check that we can add a translation for Spanish when translation is
    // already requested.
    $this->assertSession()->linkExists('Add');
    $this->assertSession()->linkByHrefExists('/es/node/1/translations/add/en/es');

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertSession()->pageTextContains('The es_MX translation for node Llamas are cool is ready for download.');

    // Check that we can add a translation for Spanish when translation is
    // ready.
    $this->assertSession()->linkExists('Add');
    $this->assertSession()->linkByHrefExists('/es/node/1/translations/add/en/es');
    $this->assertSession()->linkExistsExact('Edit in Lingotek Workbench');
    $this->assertSession()->linkByHrefExists('/admin/lingotek/workbench/dummy-document-hash-id/es_MX');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Check that we can edit a translation for Spanish when translation is
    // downloaded. Also locally.
    $this->assertSession()->linkExistsExact('Edit in Lingotek Workbench');
    $this->assertSession()->linkByHrefExists('/admin/lingotek/workbench/dummy-document-hash-id/es_MX');
    $this->assertSession()->linkExistsExact('Edit');
    $this->assertSession()->linkByHrefExists('/es/node/1/edit');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');
  }

  protected function getDestination($entity_type_id = 'node', $prefix = NULL) {
    return '';
  }

}
