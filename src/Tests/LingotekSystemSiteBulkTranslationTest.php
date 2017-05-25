<?php

namespace Drupal\lingotek\Tests;

use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;

/**
 * Tests translating a config object using the bulk management form.
 *
 * @group lingotek
 */
class LingotekSystemSiteBulkTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testSystemSiteTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
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
  public function testSystemSiteTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'request_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'check_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
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
   * Tests that a config can be translated using the links on the management page.
   */
  public function testEditedConfigTranslationUsingLinks() {
    // We need a config object with translations first.
    $this->testSystemSiteTranslationUsingLinks();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Add a language so we can check that it's not marked as for requesting if
    // it was already requested.
    ConfigurableLanguage::createFromLangcode('ko')->setThirdPartySetting('lingotek', 'locale', 'ko_KR')->save();

    // Edit the object
    $this->drupalPostForm('/admin/config/system/site-information', ['site_name' => 'My site'], t('Save configuration'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

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
    $this->clickLink('EN', 1);
    $this->assertText('System information has been updated.');

    // Korean should be marked as requested, so we can check target.
    $status = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-pending')  and contains(text(), 'KO')]");
    $this->assertEqual(count($status), 1, 'Korean is requested, so we can still check the progress status of the translation');

    // Recheck status.
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Check the translation after having been edited.
    // Check status of the Spanish translation.
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'check_translation:es'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Operations completed.');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that a config object can be translated using the actions on the management page.
   */
  public function testSystemSiteMultipleLanguageTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'request_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check status of all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Download all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'download'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testAddingLanguageAllowsRequesting() {
    // We need a config object with translations first.
    $this->testSystemSiteTranslationUsingLinks();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('ca')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // There is a link for requesting the Catalan translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/ca_ES?destination=' . $basepath .'/admin/lingotek/config/manage');
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

    $this->goToConfigBulkManagementForm();

    // Upload the document, which must fail.
    $this->clickLink('EN', 1);
    $this->assertText('System information upload failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The system information has been marked as error.');

    // The config mapper has been marked with the error status.
    /** @var ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    $this->goToConfigBulkManagementForm();

    // Upload the document, which must succeed.
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));

    // Check upload.
    $this->clickLink('EN', 1);

    // Edit the system site information.
    $edit = ['site_name' => 'Llamas are cool'];
    $this->drupalPostForm('/admin/config/system/site-information', $edit, t('Save configuration'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm();

    // Update the document, which must fail.
    $this->clickLink('EN', 1);
    $this->assertText('System information update failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The system information has been marked as error.');

    // The config mapper has been marked with the error status.
    /** @var ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN', 1);
    $this->assertText('System information has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorUsingActions() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm();

    // Upload the document, which must fail.
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('System information upload failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The system information has been marked as error.');

    // The config mapper has been marked with the error status.
    /** @var ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnErrorUsingActions() {
    $this->goToConfigBulkManagementForm();

    // Upload the document, which must succeed.
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Operations completed.');

    // Check upload.
    $this->clickLink('EN', 1);

    // Edit the system site information.
    $edit = ['site_name' => 'Llamas are cool'];
    $this->drupalPostForm('/admin/config/system/site-information', $edit, t('Save configuration'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm();
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertText('System information update failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The system information has been marked as error.');

    // The config mapper has been marked with the error status.
    /** @var ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN', 1);
    $this->assertText('System information has been updated.');
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckTranslationsAction() {
    // Add a language.
    ConfigurableLanguage::create(['id' => 'de_AT', 'label' => 'German (Austria)'])->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Assert that I could request translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');

    // Check statuses, that may been requested externally.
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Now Drupal knows that there are translations ready.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');

    // Even if I just add a new language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');

    // Ensure locales are handled correctly by setting manual values.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['de-AT' => 50, 'de-DE' => 100, 'es-MX' => 10]);
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Now Drupal knows which translations are ready.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_DE?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
  }

}
