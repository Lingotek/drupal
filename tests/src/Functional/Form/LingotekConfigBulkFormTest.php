<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the config bulk management form.
 *
 * @group lingotek
 */
class LingotekConfigBulkFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article', 'page']);
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);
  }

  /**
   * Tests that the config filtering works correctly.
   */
  public function testConfigFilter() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm();

    // Assert that there is a "Bundle" header on the second position.
    // First position is the checkbox, that's why we care about the second.
    $second_header = $this->xpath('//*[@id="edit-table"]/thead/tr/th[2]')[0];
    $this->assertEqual($second_header->getHtml(), 'Entity', 'There is a Entity header.');
  }

  /**
   * Tests that the field config filtering works correctly.
   */
  public function testFieldConfigFilter() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm();

    // Let's filter by node fields.
    $edit = ['filters[wrapper][bundle]' => 'node_fields'];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    // Assert that there is a "Bundle" header on the second position.
    // First position is the checkbox, that's why we care about the second.
    $second_header = $this->xpath('//*[@id="edit-table"]/thead/tr/th[2]/a')[0];
    $this->assertEqual($second_header->getHtml(), 'Bundle', 'There is a Bundle header.');

    $third_header = $this->xpath('//*[@id="edit-table"]/thead/tr/th[3]/a')[0];
    $this->assertEqual($third_header->getHtml(), 'Entity', 'There is a Entity header.');

    // Assert that there is a bundle printed with the Body field, and by that
    // Body must be appear twice.
    $td = $this->xpath('//td[text()="Article"]');
    $this->assertCount(1, $td);

    $td = $this->xpath('//td[text()="Page"]');
    $this->assertCount(1, $td);

    // There are two bodies, one for page and one for article.
    $td = $this->xpath('//td[text()="Body"]');
    $this->assertCount(2, $td);
  }

  /**
   * Tests that the config bulk form doesn't show a language if it's disabled.
   */
  public function testDisabledLanguage() {
    $assert_session = $this->assertSession();

    // Go and upload a field.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Then we disable the Spanish language.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfig */
    $lingotekConfig = \Drupal::service('lingotek.configuration');
    $language = ConfigurableLanguage::load('es');
    $lingotekConfig->disableLanguage($language);

    // And we check that Spanish is not there anymore.
    $this->goToConfigBulkManagementForm();
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    // We re-enable Spanish.
    $lingotekConfig->enableLanguage($language);

    // And Spanish should be back in the management form.
    $this->goToConfigBulkManagementForm();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
  }

  /**
   * Tests job id is uploaded on upload.
   */
  public function testJobIdOnUpload() {
    $assert_session = $this->assertSession();

    // Go and upload a field.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      'job_id' => 'my_custom_job_id',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertEquals('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata[] $metadatas */
    $metadatas = LingotekConfigMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEquals('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }
    // The column for Job ID exists and there are values.
    $this->assertText('Job ID');
    $this->assertText('my_custom_job_id');
  }

  /**
   * Tests job id is uploaded on update.
   */
  public function testJobIdOnUpdate() {
    $assert_session = $this->assertSession();

    // Create a node type with automatic. This will trigger upload.
    $this->drupalCreateContentType(['type' => 'banner', 'name' => 'Banner']);
    $this->drupalCreateContentType(['type' => 'book', 'name' => 'Book']);
    $this->drupalCreateContentType(['type' => 'ingredient', 'name' => 'Ingredient']);
    $this->drupalCreateContentType(['type' => 'recipe', 'name' => 'Recipe']);

    $this->goToConfigBulkManagementForm('node_type');

    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata[] $metadatas */
    $metadatas = LingotekConfigMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEmpty($metadata->getJobId(), 'There was no job id to save along with metadata.');
    }

    $basepath = \Drupal::request()->getBasePath();

    // I can check the status of the upload. So next operation will perform an
    // update.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/book?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/recipe?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[ingredient]' => TRUE,
      'table[recipe]' => TRUE,
      'table[book]' => TRUE,
      'table[banner]' => TRUE,
      'job_id' => 'my_custom_job_id',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertEquals('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata[] $metadatas */
    $metadatas = LingotekConfigMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEquals('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }
    // The column for Job ID exists and there are values.
    $this->assertText('Job ID');
    $this->assertText('my_custom_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation.
   */
  public function testAssignJobIds() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no upload.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Article uploaded successfully');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID without notification to the TMS, no update happens.
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no upload.
    \Drupal::state()->resetCache();
    $this->assertNotNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNotNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdate() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Article uploaded successfully');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is an update.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithADocumentArchivedError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Article uploaded successfully');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Document node_type Article has been archived. Please upload again.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithADocumentLockedError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Article uploaded successfully');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Document node_type Article has a new version. The document id has been updated for all future interactions. Please try again.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithAPaymentRequiredError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Article uploaded successfully');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithAnError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Article uploaded successfully');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The Job ID change submission for node_type Article failed. Please try again.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that we cannot assign job ids with invalid chars.
   */
  public function testAssignInvalidJobIdsWithTMSUpdate() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    // I can init the upload of content.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'job_id' => 'my\invalid\id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The job ID name cannot contain invalid chars as "/" or "\".');

    // There is no update, because it's not valid.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    $edit = [
      'job_id' => 'my/invalid/id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The job ID name cannot contain invalid chars as "/" or "\".');

    // There is no update, because it's not valid.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));
  }

  /**
   * Tests that can we cancel assignation of job ids with the bulk operation.
   */
  public function testCancelAssignJobIds() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Canceling resets.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Article content type');
    $this->assertNoText('Page content type');
    $this->drupalPostForm(NULL, [], 'Cancel');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertNoText('Article content type');
    $this->assertText('Page content type');
  }

  /**
   * Tests that can we reset assignation of job ids with the bulk operation.
   */
  public function testResetAssignJobIds() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Canceling resets.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Article content type');
    $this->assertNoText('Page content type');

    $this->goToConfigBulkManagementForm('node_type');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertNoText('Article content type');
    $this->assertText('Page content type');
  }

  /**
   * Tests clearing job ids.
   */
  public function testClearJobIds() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, [], 'Clear Job ID');
    $this->assertText('Job ID was cleared successfully.');

    // There is no upload.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertNoText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdate() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Job ID was cleared successfully.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertNoText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithAnError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('The Job ID change submission for node_type Article failed. Please try again.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithADocumentArchivedError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Document node_type Article has been archived. Please upload again.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithADocumentLockedError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Document node_type Article has a new version. The document id has been updated for all future interactions. Please try again.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithAPaymentRequiredError() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('The Job ID change submission for node_type Article failed. Please try again.');
    $this->assertText('Job ID for some config failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests that the bulk management filtering works correctly.
   */
  public function testJobIdFilter() {
    $assert_session = $this->assertSession();

    \Drupal::configFactory()->getEditable('lingotek.settings')->set('translate.config.node_type.profile', 'manual')->save();

    $basepath = \Drupal::request()->getBasePath();

    $node_types = [];
    // See https://www.drupal.org/project/drupal/issues/2925290.
    $indexes = "ABCDEFGHIJKLMNOPQ";
    // Create some nodes.
    for ($i = 1; $i < 10; $i++) {
      $node_types[$i] = $this->drupalCreateContentType(['type' => 'content_type_' . $i, 'name' => 'Content Type ' . $indexes[$i]]);
    }

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertNoText('No content available');

    // After we filter by an unexisting job, there is no content and no rows.
    $edit = [
      'filters[wrapper][job]' => 'this job does not exist',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $this->assertText('No content available');

    // After we reset, we get back to having all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    $this->goToConfigBulkManagementForm('node_type');
    foreach (range(1, 9) as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }

    // I can init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[content_type_2]' => TRUE,
      'table[content_type_4]' => TRUE,
      'table[content_type_6]' => TRUE,
      'table[content_type_8]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
      'job_id' => 'even numbers',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[content_type_1]' => TRUE,
      'table[content_type_2]' => TRUE,
      'table[content_type_3]' => TRUE,
      'table[content_type_5]' => TRUE,
      'table[content_type_7]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
      'job_id' => 'prime numbers',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // After we filter by prime, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][job]' => 'prime',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 2, 3, 5, 7] as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    $this->assertNoText('Content Type ' . $indexes[4]);
    $this->assertNoText('Content Type ' . $indexes[6]);

    // After we filter by even, there is no pager and the rows selected are the
    // ones expected.
    $edit = [
      'filters[wrapper][job]' => 'even',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([4, 6, 8] as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    $this->assertNoText('Content Type ' . $indexes[5]);

    // After we reset, we get back to having all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    $this->goToConfigBulkManagementForm('node_type');
    foreach (range(1, 9) as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
  }

  /**
   * Tests that the bulk management filtering works correctly.
   */
  public function testLabelFilter() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm();
    $this->assertNoField('filters[wrapper][label]');

    \Drupal::configFactory()->getEditable('lingotek.settings')->set('translate.config.node_type.profile', 'manual')->save();

    $node_types = [];
    // See https://www.drupal.org/project/drupal/issues/2925290.
    $indexes = "ABCDEFGHIJKLMNOPQ";
    // Create some nodes.
    for ($i = 1; $i < 10; $i++) {
      $odd_index = $i % 2 == 0;
      $name = 'Content Type ' . $indexes[$i] . ' ' . ($odd_index ? 'odd' : 'even');
      $node_types[$i] = $this->drupalCreateContentType(['type' => 'content_type_' . $i, 'name' => $name]);
    }

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertField('filters[wrapper][label]');
    $this->assertNoText('No content available');

    // After we filter by an unexisting label, there is no content and no rows.
    $edit = [
      'filters[wrapper][label]' => 'this label does not exist',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $this->assertText('No content available');

    // After we reset, we get back to having all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    $this->goToConfigBulkManagementForm('node_type');
    foreach (range(1, 9) as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }

    // After we filter by prime, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][label]' => 'even',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 3, 5, 7, 9] as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    $this->assertNoText('Content Type ' . $indexes[2]);
    $this->assertNoText('Content Type ' . $indexes[4]);
    $this->assertNoText('Content Type ' . $indexes[6]);

    // After we filter by even, there is no pager and the rows selected are the
    // ones expected.
    $edit = [
      'filters[wrapper][label]' => 'odd',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([2, 4, 6, 8] as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    $this->assertNoText('Content Type ' . $indexes[1]);
    $this->assertNoText('Content Type ' . $indexes[3]);
    $this->assertNoText('Content Type ' . $indexes[5]);

    // After we reset, we get back to having all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    $this->goToConfigBulkManagementForm('node_type');
    foreach (range(1, 9) as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    // If we filter with extra spaces, we still show configs.
    $edit = [
      'filters[wrapper][label]' => '  even   ',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 3, 5, 7, 9] as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    $this->assertFieldByName('filters[wrapper][label]', 'even', 'The value is trimmed in the filter.');
  }

  /**
   * Tests that config listed links to the config when there are links available.
   */
  public function testLabelsAndLinksWhenAvailable() {
    $assert_session = $this->assertSession();

    $this->goToConfigBulkManagementForm();
    $this->assertText('System maintenance');
    $assert_session->linkNotExists('System maintenance');

    $this->goToConfigBulkManagementForm('configurable_language');
    $assert_session->linkExists('Spanish language');
  }

}
