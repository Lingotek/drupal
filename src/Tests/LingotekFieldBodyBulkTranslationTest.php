<?php

namespace Drupal\lingotek\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;

/**
 * Tests translating a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekFieldBodyBulkTranslationTest extends LingotekTestBase {

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

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testFieldBodyTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText(t('Body uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Body status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX status checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_MX');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/es_MX' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }

  /**
   * Tests that a config can be translated using the actions on the management page.
   */
  public function testFieldBodyTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,  // Article.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,  // Article.
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,  // Article.
      'operation' => 'request_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/field_config/node.article.body/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,  // Article.
      'operation' => 'check_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/field_config/node.article.body/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,  // Article.
      'operation' => 'download:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/de_AT');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/de_AT' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }

  /**
   * Tests that a field can be translated using the actions on the management page.
   */
  public function testFieldBodyMultipleLanguageTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Set upload as manual.
    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'request_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check status of all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/field_config/node.article.body/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Download all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'download'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testEditedConfigTranslationUsingLinks() {
    // We need a node with translations first.
    $this->testFieldBodyTranslationUsingLinks();

    // Set upload as manual.
    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Add a language so we can check that it's not marked as for requesting if
    // it was already requested.
    ConfigurableLanguage::createFromLangcode('ko')->setThirdPartySetting('lingotek', 'locale', 'ko_KR')->save();

    // Edit the object
    $this->drupalPostForm('/admin/structure/types/manage/article/fields/node.article.body', ['label' => 'Body EDITED'], t('Save settings'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Check the status is edited for Spanish.
    $untracked = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    $this->assertEqual(count($untracked), 1, 'Edited translation is shown.');

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $eu_edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_edited), 0, 'Vasque is not marked as edited.');
    $eu_request = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-request')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_request), 1, 'Vasque is ready for request.');

    // Request korean, with outdated content available.
    $this->clickLink('KO');
    $this->assertText("Translation to ko_KR requested successfully");

    // Reupload the content.
    $this->clickLink('EN');
    $this->assertText('Body EDITED has been updated.');

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('Body EDITED status checked successfully');

    // Korean should still be marked as requested, so we can check target.
    $status = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-pending')  and contains(text(), 'KO')]");
    $this->assertEqual(count($status), 1, 'Korean is still requested, so we can still check the progress status of the translation');

    // Check the translation after having been edited.
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Operations completed.');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testEditedConfigTranslationUsingLinksInAutomaticUploadMode() {
    // We need a node with translations first.
    $this->testFieldBodyTranslationUsingLinks();

    // Set upload as manual.
    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the object
    $this->drupalPostForm('/admin/structure/types/manage/article/fields/node.article.body', ['label' => 'Body EDITED'], t('Save settings'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Check the status is edited for Spanish.
    $untracked = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    $this->assertEqual(count($untracked), 1, 'Edited translation is shown.');

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $eu_edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_edited), 0, 'Vasque is not marked as edited.');
    $eu_request = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-request')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_request), 1, 'Vasque is ready for request.');

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('Body EDITED status checked successfully');

    // Check the translation after having been edited.
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Operations completed.');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testAddingLanguageAllowsRequesting() {
    // We need a node with translations first.
    $this->testFieldBodyTranslationUsingLinks();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('ca')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    $basepath = \Drupal::request()->getBasePath();

    // There is a link for requesting the Catalan translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('CA');
    $this->assertText("Translation to ca_ES requested successfully");
  }

  /**
   * Test that when a config is uploaded in a different locale that locale is used.
   */
  // ToDo: Add a test for this.
  public function testAddingConfigInDifferentLocale() {
    $this->pass('Test not implemented yet.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_fields');

    // Upload the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Body upload failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The field has been marked as error.');

    // The field has been marked with the error status.
    $fieldConfig = FieldConfig::load('node.article.body');
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($fieldConfig);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The field has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Body uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Set upload as manual.
    $edit = [
      'table[node_fields][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    $this->goToConfigBulkManagementForm('node_fields');

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Body uploaded successfully');

    // Check upload.
    $this->clickLink('EN');

    // Edit the field.
    $edit = ['label' => 'Contents'];
    $this->drupalPostForm('/admin/structure/types/manage/article/fields/node.article.body', $edit, t('Save settings'));
    $this->assertText('Saved Contents configuration.');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_fields');

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Contents update failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The field has been marked as error.');

    // The field has been marked with the error status.
    $fieldConfig = FieldConfig::load('node.article.body');
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($fieldConfig);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The field has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Contents has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorUsingActions() {
    // Set upload as manual.
    $edit = [
      'table[node_fields][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm('node_fields');

    // Upload the document, which must fail.
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Body upload failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The field has been marked as error.');

    // The field has been marked with the error status.
    $fieldConfig = FieldConfig::load('node.article.body');
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($fieldConfig);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The field has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Body uploaded successfully');
  }

}
