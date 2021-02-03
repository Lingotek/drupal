<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\NodeType;
use Drupal\lingotek\Entity\LingotekConfigMetadata;

/**
 * Tests translating a config entity using the bulk management form.
 *
 * @group lingotek
 */
class LingotekContentTypeBulkTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'frozenintime'];

  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testContentTypeTranslationUsingLinks() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Ensure it has the expected timestamp for updated and upload
    $timestamp = \Drupal::time()->getRequestTime();
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEmpty($metadata->getLastUpdated());
      $this->assertEquals($timestamp, $metadata->getLastUploaded());
    }

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX status checked successfully");

    // Download the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
  }

  /**
   * Tests that a config can be translated using the actions on the management page.
   */
  public function testContentTypeTranslationUsingActions() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('de', 'node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslation('de', 'node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'download:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT', 'dummy-document-hash-id', 'DE');
  }

  /**
   * Tests that a config entity can be translated using the actions on the management page.
   */
  public function testContentTypeMultipleLanguageTranslationUsingActions() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Create another node type
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // I can check current status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request all the translations.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/page/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check status of all the translations.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/page/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Download all the translations.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/page/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testEditedConfigTranslationUsingLinks() {
    $assert_session = $this->assertSession();

    // We need a node with translations first.
    $this->testContentTypeTranslationUsingLinks();

    // Set upload as manual.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:manual',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Add a language so we can check that it's not marked as for requesting if
    // it was already requested.
    ConfigurableLanguage::createFromLangcode('ko')->setThirdPartySetting('lingotek', 'locale', 'ko_KR')->save();

    // Edit the object
    $this->drupalPostForm('/admin/structure/types/manage/article', ['name' => 'Article EDITED'], t('Save content type'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    // Check the source status is edited.
    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $this->assertNoTargetStatus('EU', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('EU', Lingotek::STATUS_REQUEST);

    // Request korean, with outdated content available.
    $this->clickLink('KO');
    $this->assertText("Translation to ko_KR requested successfully");

    // Reupload the content.
    $this->clickLink('EN');
    $this->assertText('Article EDITED has been updated.');

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('Article EDITED status checked successfully');

    // Korean should still be marked as requested, so we can check target.
    $this->assertTargetStatus('KO', 'pending');

    // Check the translation after having been edited.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testEditedConfigTranslationUsingLinksInAutomaticUploadMode() {
    $assert_session = $this->assertSession();

    // We need a node with translations first.
    $this->testContentTypeTranslationUsingLinks();

    // Set upload as automatic.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the object
    $this->drupalPostForm('/admin/structure/types/manage/article', ['name' => 'Article EDITED'], t('Save content type'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    // The source status is 'Importing' because of automatic upload.
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);

    // Ensure it has the expected timestamp for updated and upload
    $timestamp = \Drupal::time()->getRequestTime();
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEquals($timestamp, $metadata->getLastUpdated());
      $this->assertEquals($timestamp, $metadata->getLastUploaded());
    }

    // Check the target statuses are not affected, but the current is back to pending.
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);
    $this->assertTargetStatus('EU', Lingotek::STATUS_REQUEST);

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('Article EDITED status checked successfully');

    // Check the translation after having been edited.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testAddingLanguageAllowsRequesting() {
    $assert_session = $this->assertSession();

    // We need a node with translations first.
    $this->testContentTypeTranslationUsingLinks();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('ca')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // There is a link for requesting the Catalan translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('CA');
    $this->assertText("Translation to ca_ES requested successfully");
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testFormWorksAfterRemovingLanguageWithStatuses() {
    $assert_session = $this->assertSession();

    // We need a language added and requested.
    $this->testAddingLanguageAllowsRequesting();

    // Delete a language.
    ConfigurableLanguage::load('es')->delete();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // There is no link for the Spanish translation.
    $assert_session->linkNotExists('ES');
    $assert_session->linkExists('CA');
  }

  /**
   * Test that when a config is uploaded in a different locale that locale is used.
   * ToDo: Add a test for this.
   */
  public function testAddingConfigInDifferentLocale() {
    $this->pass('Test not implemented yet.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Article upload failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The node type has been marked as error.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Blogpost update failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAPaymentRequiredError() {
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedError() {
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Document node_type Blogpost has been archived. Please upload again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedError() {
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Document node_type Blogpost has a new version. The document id has been updated for all future interactions. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorUsingActions() {
    $assert_session = $this->assertSession();
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must fail.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Article upload failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredErrorUsingActions() {
    $assert_session = $this->assertSession();
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must fail.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnErrorUsingActions() {
    $assert_session = $this->assertSession();
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_type');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Blogpost update failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedErrorUsingActions() {
    $assert_session = $this->assertSession();
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    $this->goToConfigBulkManagementForm('node_type');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Document node_type Blogpost has a new version. The document id has been updated for all future interactions. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAPaymentRequiredErrorUsingActions() {
    $assert_session = $this->assertSession();
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    $this->goToConfigBulkManagementForm('node_type');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedErrorUsingActions() {
    $assert_session = $this->assertSession();
    // Set upload as manual.
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->goToConfigBulkManagementForm('node_type');

    // Upload the document, which must succeed.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node type.
    $edit = ['name' => 'Blogpost'];
    $this->drupalPostForm('/admin/structure/types/manage/article', $edit, t('Save content type'));

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    $this->goToConfigBulkManagementForm('node_type');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Document node_type Blogpost has been archived. Please upload again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Blogpost uploaded successfully');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node_type Article translations request failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->assertText('Document node_type Article has been archived. Please upload again.');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node_type Article has a new version. The document id has been updated for all future interactions. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Article es_MX translation request failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->assertText('Document node_type Article has been archived. Please upload again.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node_type Article has a new version. The document id has been updated for all future interactions. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node_type Article es_MX translation request failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->assertText('Document node_type Article has been archived. Please upload again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node_type Article has a new version. The document id has been updated for all future interactions. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckTranslationsAction() {
    $assert_session = $this->assertSession();
    // Add a couple of languages.
    ConfigurableLanguage::create(['id' => 'de_AT', 'label' => 'German (Austria)'])->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();
    ConfigurableLanguage::createFromLangcode('ca')->setThirdPartySetting('lingotek', 'locale', 'ca_ES')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Assert that I could request translations.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Check statuses, that may been requested externally.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows that there are translations ready.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Even if I just add a new language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Ensure locales are handled correctly by setting manual values.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['de-AT' => 50, 'de-DE' => 100, 'es-MX' => 10]);
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows which translations are ready.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/download/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/de_DE?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');

    \Drupal::state()->set('lingotek.document_completion_statuses', ['it-IT' => 100, 'de-DE' => 50, 'es-MX' => 10]);
    // Check all statuses again.
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // All translations must be updated according exclusively with the
    // information from the TMS.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/de_DE?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Source status must be kept too.
    $this->assertSourceStatusStateCount(Lingotek::STATUS_CURRENT, 'EN', 1);
  }

  /**
   * Tests that unrequested locales are not marked as error when downloading all.
   */
  public function testTranslationDownloadWithUnrequestedLocales() {
    $assert_session = $this->assertSession();
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX status checked successfully");

    // Download all the translations.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // The translations not requested shouldn't change its status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/de_DE?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // They aren't marked as error.
    $this->assertNoConfigTargetError('Article', 'DE', 'de_DE');
    $this->assertNoConfigTargetError('Article', 'IT', 'it_IT');
  }

  /**
   * Tests that current locales are not cleared when checking statuses.
   */
  public function testCheckTranslationsWithDownloadedLocales() {
    $assert_session = $this->assertSession();
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_DE')
      ->save();
    ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT')
      ->save();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    \Drupal::state()->resetCache();

    // Request italian.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('IT');
    $this->assertText("Translation to it_IT requested successfully");
    $this->assertIdentical('it_IT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX status checked successfully");

    \Drupal::state()->resetCache();

    // Check status of the Italian translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('IT');
    $this->assertIdentical('it_IT', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to it_IT status checked successfully");

    // Download all the translations.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // They are marked with the right status.
    $this->assertTargetStatus('ES', 'current');
    $this->assertTargetStatus('IT', 'current');
    $this->assertTargetStatus('DE', 'request');

    // We check all translations.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['es-ES' => 100, 'it-IT' => 100]);
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // And statuses should remain the same.
    $this->assertTargetStatus('ES', 'current');
    $this->assertTargetStatus('IT', 'current');
    $this->assertTargetStatus('DE', 'request');
  }

  /**
   * Tests translation with quotation marks.
   */
  public function testWithEncodedQuotations() {
    $assert_session = $this->assertSession();
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type+htmlquotes');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX status checked successfully");

    // Download the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Let's edit the translation and assert the html decoded values.
    $this->drupalGet('/admin/structure/types');
    $this->clickLink('Translate');
    $this->clickLink('Edit', 1);
    $this->assertFieldByName('translation[config_names][node.type.article][name]', 'Clase de "Artículo"');
    $this->assertFieldByName('translation[config_names][node.type.article][description]', 'Uso de "artículos" sensibles al tiempo.');
  }

  /**
   * Tests that we update the statuses when a translation is deleted.
   */
  public function testDeleteTranslationUpdatesStatuses() {
    $this->testContentTypeTranslationUsingActions();

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertTargetStatus('DE', Lingotek::STATUS_CURRENT);

    $this->drupalGet('/admin/structure/types/manage/article/translate');
    $this->clickLink('Delete');
    $this->drupalPostForm(NULL, [], t('Delete'));

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertTargetStatus('DE', Lingotek::STATUS_READY);
  }

  /**
   * Tests that no config entity is uploaded or listed if has not specified language.
   */
  public function testNotSpecifiedLanguageConfigEntityInBulkManagement() {
    $this->drupalCreateContentType([
      'type' => 'und_language_content_type',
      'name' => 'Not specified language content type',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $this->goToConfigBulkManagementForm('node_type');

    // Nothing was uploaded even with automatic profile, and nothing is listed
    // for upload.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNoText('Not specified language content type');
    $this->assertText('Article content type');
  }

  protected function getBulkSelectionKey($langcode, $revision_id, $entity_type_id = 'article') {
    return 'table[' . $entity_type_id . ']';
  }

  /**
   * Asserts there is a link for uploading the content.
   *
   * @param int|string $entity_id
   *   The entity ID. Optional, defaults to 1.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertLingotekUploadLink($entity_id = 'article', $entity_type_id = 'node_type', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/config/upload/' . $entity_type_id . '/' . $entity_id;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $assert_session->linkByHrefExists($href);
  }

  protected function assertLingotekCheckSourceStatusLink($document_id = 'article', $entity_type_id = 'node_type', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/config/check_upload/' . $entity_type_id . '/' . $document_id;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $assert_session->linkByHrefExists($href);
  }

  protected function assertLingotekRequestTranslationLink($locale, $document_id = 'article', $entity_type_id = 'node_type', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/config/request/' . $entity_type_id . '/' . $document_id . '/' . $locale;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $assert_session->linkByHrefExists($href);
  }

  protected function getDestination($entity_type_id = 'node', $prefix = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    return '?destination=' . $basepath . $this->getConfigBulkManagementFormUrl($entity_type_id, $prefix);
  }

  protected function getConfigBulkManagementFormUrl($entity_type_id = 'node', $prefix = NULL) {
    return ($prefix === NULL ? '' : '/' . $prefix) . '/admin/lingotek/config/manage';
  }

}
