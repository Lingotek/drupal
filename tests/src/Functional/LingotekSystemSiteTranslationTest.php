<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating a config object.
 *
 * @group lingotek
 */
class LingotekSystemSiteTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'image', 'frozenintime'];

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'header']);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'header']);
    // Place branding block with site name and slogan into header region.
    $this->drupalPlaceBlock('system_branding_block', ['region' => 'header']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testSystemSiteTranslation() {
    $assert_session = $this->assertSession();

    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    $this->clickLink(t('Upload'));
    $this->assertText(t('System information uploaded successfully'));

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual(1, count($data));
    $this->assertTrue(array_key_exists('system.site', $data));
    $this->assertEqual(2, count($data['system.site']));
    $this->assertTrue(array_key_exists('name', $data['system.site']));
    $this->assertTrue(array_key_exists('slogan', $data['system.site']));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Ensure it has the expected timestamp for upload
    $timestamp = \Drupal::time()->getRequestTime();
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEquals($timestamp, $metadata->getLastUploaded());
      $this->assertEmpty($metadata->getLastUpdated());
    }

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(NULL, $uploaded_url, 'There was not associated url.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    $this->clickLink(t('Check upload status'));
    $this->assertText(t('System information status checked successfully'));

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.added_target_locale'));
    $this->assertText(t('Translation to es_AR requested successfully'));

    // Check translation status.
    $this->clickLink(t('Check Download'));
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText(t('Translation to es_AR checked successfully'));

    $this->clickLink('Download');
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.downloaded_locale'));
    $this->assertText(t('Translation to es_AR downloaded successfully'));

    // Check that the edit link is there.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/config/system/site-information/translate/es/edit');
    // Edit the Spanish translation.
    $this->clickLink('Edit', 1);
    $this->assertFieldByName('translation[config_names][system.site][slogan]', 'Las llamas son muy chulas');

    // The content is translated and published.
    $this->drupalGet('/es/');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that a config can be translated after edited.
   */
  public function testEditedSystemConfigTranslation() {
    $assert_session = $this->assertSession();

    // We need a config with translations first.
    $this->testSystemSiteTranslation();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the config.
    $edit = ['site_name' => 'The Llamas site'];
    $this->drupalPostForm('admin/config/system/site-information', $edit, 'Save configuration');

    $this->clickLink('Translate system information');

    // We need to reupload again. It's manual for configuration.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();

    // Ensure it has the expected timestamp for upload
    $timestamp = \Drupal::time()->getRequestTime();
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEquals($timestamp, $metadata->getLastUploaded());
      $this->assertEquals($timestamp, $metadata->getLastUpdated());
    }

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $assert_session->linkByHrefExists('admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/eu_ES');

    // Recheck status.
    $this->clickLink('Check Download');
    $this->assertText('Translation to es_AR checked successfully');

    // Download the translation.
    $this->clickLink('Download');
    $this->assertText('Translation to es_AR downloaded successfully');
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

    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    $this->clickLink(t('Upload'));
    $this->assertText(t('System information uploaded successfully'));

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual(1, count($data));
    $this->assertTrue(array_key_exists('system.site', $data));
    $this->assertEqual(2, count($data['system.site']));
    $this->assertTrue(array_key_exists('name', $data['system.site']));
    $this->assertTrue(array_key_exists('slogan', $data['system.site']));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    // The document should have been uploaded, so let's check the upload status.
    $this->clickLink(t('Check upload status'));
    $this->assertText(t('System information status checked successfully'));

    // There are two links for requesting translations, or we can add them
    // manually.
    $assert_session->linkByHrefExists('/admin/config/system/site-information/translate/it/add');
    $assert_session->linkByHrefExists('/admin/config/system/site-information/translate/es/add');
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/it_IT');
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_AR');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Check that the translate tab is in the node.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    // Italian is not present anymore, but still can add a translation.
    $assert_session->linkByHrefExists('/admin/config/system/site-information/translate/it/add');
    $assert_session->linkByHrefExists('/admin/config/system/site-information/translate/es/add');
    $assert_session->linkByHrefNotExists('/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/it_IT');
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_AR');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    $this->clickLink(t('Upload'));
    $this->assertText('System information upload failed. Please try again.');

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
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    $this->clickLink(t('Upload'));
    $this->checkForMetaRefresh();
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('System information status checked successfully');

    // Edit the system site information.
    $edit['site_name'] = 'Llamas are cool';
    $this->drupalPostForm('/admin/config/system/site-information', $edit, t('Save configuration'));

    // Go back to the form.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information update failed. Please try again.');

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
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAPaymentRequiredError() {
    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('System information status checked successfully');

    // Edit the system site information.
    $edit['site_name'] = 'Llamas are cool';
    $this->drupalPostForm('/admin/config/system/site-information', $edit, t('Save configuration'));

    // Go back to the form.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedError() {
    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('System information status checked successfully');

    // Edit the system site information.
    $edit['site_name'] = 'Llamas are cool';
    $this->drupalPostForm('/admin/config/system/site-information', $edit, t('Save configuration'));

    // Go back to the form.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Document System information has been archived. Please upload again.');

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedError() {
    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('System information status checked successfully');

    // Edit the system site information.
    $edit['site_name'] = 'Llamas are cool';
    $this->drupalPostForm('/admin/config/system/site-information', $edit, t('Save configuration'));

    // Go back to the form.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Document System information has a new version. The document id has been updated for all future interactions. Please try again.');

    // The config mapper has been marked with the error status.
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapperManager */
    $mapperManager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mapper = $mapperManager->getMappers()['system.site_information_settings'];
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getConfigSourceStatus($mapper);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The system information has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('System information has been updated.');
  }

}
