<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a config object using the bulk management form.
 *
 * @group lingotek
 */
class LingotekSystemSiteExistingBulkTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  /**
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    $language = ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX');
    $language->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Create a translation for system.site object.
    $config = \Drupal::languageManager()->getLanguageConfigOverride('es', 'system.site');
    $config->set('name', 'Translated Site Name')->save();
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testSystemSiteIsUntracked() {
    $assert_session = $this->assertSession();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Assert the untracked translation is shown.
    $this->assertTargetStatus('ES', 'untracked');
    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Assert the untracked translation is shown.
    $this->assertTargetStatus('ES', 'untracked');

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    // Download the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
  }

}
