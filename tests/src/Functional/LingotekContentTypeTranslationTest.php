<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\NodeType;

/**
 * Tests translating a content type.
 *
 * @group lingotek
 */
class LingotekContentTypeTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'image'];

  /**
   * @var \Drupal\node\Entity\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'header', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'header', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();
  }

  /**
   * Tests that a node can be translated.
   */
  public function testContentTypeTranslation() {
    $assert_session = $this->assertSession();
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertText(t('Article uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual(3, count($data));
    $this->assertTrue(array_key_exists('name', $data));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data));
    $this->assertTrue(array_key_exists('help', $data));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->clickLink(t('Check upload status'));
    $this->assertText(t('Article status checked successfully'));

    $this->clickLink(t('Request translation'));
    $this->assertText(t('Translation to es_MX requested successfully'));
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    $this->clickLink(t('Check Download'));
    $this->assertText(t('Translation to es_MX status checked successfully'));

    $this->clickLink('Download');
    $this->assertText(t('Translation to es_MX downloaded successfully'));

    // Check that the edit link is there.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/structure/types/manage/article/translate/es/edit');
  }

  /**
   * Tests that a config can be translated after edited.
   */
  public function testEditedContentTypeTranslation() {
    $assert_session = $this->assertSession();
    // We need a config with translations first.
    $this->testContentTypeTranslation();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    $this->clickLink(t('Translate'));

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $assert_session->linkByHrefExists('admin/lingotek/config/request/node_type/article/eu_ES');
    $assert_session->linkByHrefNotExists('admin/lingotek/config/request/node_type/article/es_MX');

    // Recheck status.
    $this->clickLink('Check Download');
    $this->assertText('Translation to es_MX status checked successfully');

    // Download the translation.
    $this->clickLink('Download');
    $this->assertText('Translation to es_MX downloaded successfully');
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

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertText(t('Article uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual(3, count($data));
    $this->assertTrue(array_key_exists('name', $data));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data));
    $this->assertTrue(array_key_exists('help', $data));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check upload status');
    $this->assertText(t('Article status checked successfully'));

    // There are two links for requesting translations, or we can add them
    // manually.
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/node_type/article/it_IT');
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/node_type/article/es_MX');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/it/add');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/es/add');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Check that the translate tab is in the node.
    $this->drupalGet('/admin/structure/types/manage/article/translate');

    // Italian is not present anymore, but still can add a translation.
    $assert_session->linkByHrefNotExists('/admin/lingotek/config/request/node_type/article/it_IT');
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/node_type/article/es_MX');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/it/add');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/es/add');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article upload failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredErrorViaAutomaticUpload() {
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Create a content type.
    $edit = ['name' => 'Landing Page', 'type' => 'landing_page'];
    $this->drupalPostForm('admin/structure/types/add', $edit, 'Save content type');

    // The document was uploaded automatically and failed.
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('landing_page');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAPaymentRequiredError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAPaymentRequiredErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink(t('Translate'));
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost update failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAnErrorViaAutomaticUpload() {
    // Create a content type.
    $edit = ['name' => 'Landing Page', 'type' => 'landing_page'];
    $this->drupalPostForm('admin/structure/types/add', $edit, 'Save content type');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Edit the content type.
    $edit['name'] = 'Landing Page EDITED';
    $this->drupalPostForm('/admin/structure/types/manage/landing_page', $edit, t('Save content type'));

    // The document was updated automatically and failed.
    $this->assertText('The update for node_type Landing Page EDITED failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('landing_page');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Document Blogpost has been archived. Please upload again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Document node_type Blogpost has a new version. The document id has been updated for all future interactions. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    $this->assertText('Document node_type Blogpost has a new version. The document id has been updated for all future interactions. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink(t('Translate'));
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));
    $this->assertText('The content type Blogpost has been updated.');

    $this->assertText('Document node_type Blogpost has been archived. Please upload again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink(t('Translate'));
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Blogpost uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorViaAutomaticUpload() {
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->drupalGet('admin/lingotek/settings');

    // Create a content type.
    $edit = ['name' => 'Landing Page', 'type' => 'landing_page'];
    $this->drupalPostForm('admin/structure/types/add', $edit, 'Save content type');

    // The document was uploaded automatically and failed.
    $this->assertText('The upload for node_type Landing Page failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('landing_page');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test trying translating a config entity which language doesn't exist.
   */
  public function testTranslatingFromUnexistingLocale() {
    // Create a node type.
    $this->drupalCreateContentType([
      'type' => 'aaa_test_content_type',
      'name' => 'AAA Test Content Type',
      'langcode' => 'nap',
    ]);
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink('Translate');
    $this->assertText('Translations for AAA Test Content Type content type');
    $this->assertText('Unknown (nap) (original)');
  }

}
