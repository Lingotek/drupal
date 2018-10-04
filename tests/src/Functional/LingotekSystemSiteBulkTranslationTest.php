<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\user\Entity\Role;

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

    // Login as translations manager, but including the 'translate configuration'
    // permission.
    $roles = $this->translationManagerUser->getRoles(TRUE);
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load($roles[0]);
    $role->grantPermission('translate configuration')->save();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testSystemSiteTranslationUsingLinks() {
    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX');
  }

  /**
   * Tests that a config can be translated using the actions on the management page.
   */
  public function testSystemSiteTranslationUsingActions() {
    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'request_translation:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'download:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testEditedConfigTranslationUsingLinks() {
    // We need a config object with translations first.
    $this->testSystemSiteTranslationUsingLinks();

    // Login as translation manager.
    $this->drupalLogin($this->rootUser);

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Add a language so we can check that it's not marked as for requesting if
    // it was already requested.
    ConfigurableLanguage::createFromLangcode('ko')->setThirdPartySetting('lingotek', 'locale', 'ko_KR')->save();

    // Edit the object
    $this->drupalPostForm('/admin/config/system/site-information', ['site_name' => 'My site'], t('Save configuration'));

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

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
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:es',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that a config object can be translated using the actions on the management page.
   */
  public function testSystemSiteMultipleLanguageTranslationUsingActions() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'request_translations',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check status of all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translations',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Download all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'download',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
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
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('CA');
    $this->assertText("Translation to ca_ES requested successfully");
  }

  /**
   * Test that when a config is uploaded in a different locale that locale is used.
   */

  /**
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

    $this->goToConfigBulkManagementForm();

    // Upload the document, which must fail.
    $this->clickLink('EN', 1);
    $this->assertText('System information upload failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The system information has been marked as error.');

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
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
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
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
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('System information upload failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The system information has been marked as error.');

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
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
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Check upload.
    $this->clickLink('EN', 1);

    // Edit the system site information.
    $edit = ['site_name' => 'Llamas are cool'];
    $this->drupalPostForm('/admin/config/system/site-information', $edit, t('Save configuration'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToConfigBulkManagementForm();
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('System information update failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The system information has been marked as error.');

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN', 1);
    $this->assertText('System information has been updated.');
  }

  /**
   * Test that we handle errors in download for configs.
   */
  public function testDownloadingWithAnError() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    \Drupal::state()->set('lingotek.must_error_in_download', TRUE);
    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('System information es_MX translation download failed. Please try again.');
    $this->assertIdentical(NULL, \Drupal::state()->get('lingotek.downloaded_locale'));

    $this->goToConfigBulkManagementForm();
    // Check the right class is added.
    $this->assertTargetStatus('ES', Lingotek::STATUS_ERROR);

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $target_status = $translation_service->getConfigTargetStatus($mapper, 'es');
    $this->assertEqual(Lingotek::STATUS_ERROR, $target_status, 'The system information has been marked as error.');

    \Drupal::state()->set('lingotek.must_error_in_download', FALSE);
    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckTranslationsAction() {
    // Add a couple of languages.
    ConfigurableLanguage::create(['id' => 'de_AT', 'label' => 'German (Austria)'])->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();
    ConfigurableLanguage::createFromLangcode('ca')->setThirdPartySetting('lingotek', 'locale', 'ca_ES')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Assert that I could request translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Check statuses, that may been requested externally.
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translations',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows that there are translations ready.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Even if I just add a new language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Ensure locales are handled correctly by setting manual values.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['de-AT' => 50, 'de-DE' => 100, 'es-MX' => 10]);
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows which translations are ready.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/de_DE?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');

    \Drupal::state()->set('lingotek.document_completion_statuses', ['it-IT' => 100, 'de-DE' => 50, 'es-MX' => 10]);
    // Check all statuses again.
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // All translations must be updated according exclusively with the
    // information from the TMS.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/de_DE?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Source status must be kept too.
    $this->assertSourceStatusStateCount(Lingotek::STATUS_CURRENT, 'EN', 1);
  }

  /**
   * Tests that unrequested locales are not marked as error when downloading all.
   */
  public function testTranslationDownloadWithUnrequestedLocales() {
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    // Download all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'download',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // The translations not requested shouldn't change its status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_DE?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // They aren't marked as error.
    $this->assertNoConfigTargetError('System information', 'DE', 'de_DE');
    $this->assertNoConfigTargetError('System information', 'IT', 'it_IT');
  }

  /**
   * Tests that current locales are not cleared when checking statuses.
   */
  public function testCheckTranslationsWithDownloadedLocales() {
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_DE')
      ->save();
    ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT')
      ->save();

    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    \Drupal::state()->resetCache();

    // Request italian.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('IT');
    $this->assertText("Translation to it_IT requested successfully");
    $this->assertIdentical('it_IT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    \Drupal::state()->resetCache();

    // Check status of the Italian translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/it_IT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('IT');
    $this->assertIdentical('it_IT', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to it_IT checked successfully");

    // Download all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'download',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // They are marked with the right status.
    $this->assertTargetStatus('ES', 'current');
    $this->assertTargetStatus('IT', 'current');
    $this->assertTargetStatus('DE', 'request');

    // We check all translations.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['es-ES' => 100, 'it-IT' => 100]);
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translations',
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
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site+htmlquotes');

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Let's edit the translation and assert the html decoded values.
    $this->drupalGet('/admin/config/system/site-information/translate');
    $this->clickLink('Edit');
    $this->assertFieldByName('translation[config_names][system.site][name]', '"Durpal"');
    $this->assertFieldByName('translation[config_names][system.site][slogan]', '"Las llamas" son muy chulas');
  }

  /**
   * Tests that we update the statuses when a translation is deleted.
   */
  public function testDeleteTranslationUpdatesStatuses() {
    $this->testSystemSiteTranslationUsingActions();

    $this->goToConfigBulkManagementForm();
    $this->assertTargetStatus('DE', Lingotek::STATUS_CURRENT);

    $this->drupalGet('/admin/config/system/site-information/translate');
    $this->clickLink('Delete');
    $this->drupalPostForm(NULL, [], t('Delete'));

    $this->goToConfigBulkManagementForm();
    $this->assertTargetStatus('DE', Lingotek::STATUS_READY);
  }

}
