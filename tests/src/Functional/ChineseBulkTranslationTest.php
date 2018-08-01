<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests translating into chinese locales.
 *
 * @group lingotek
 * @group legacy
 */
class ChineseBulkTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'comment'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add locale.
    // TODO: Use the dashboard for adding the language
    //    $post = [
    //      'code' => 'zh_CN',
    //      'language' => 'Chinese',
    //      'native' => 'Chinese',
    //      'direction' => '',
    //    ];
    //    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    ConfigurableLanguage::createFromLangcode('zh-hans')->setThirdPartySetting('lingotek', 'locale', 'zh_CN')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article'], 'manual');
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testNodeTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('zh_CN');
    $this->clickLink('EN');

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('zh_CN');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('zh_CN');
    $this->clickLink('ZH');
    $this->assertText("Locale 'zh_CN' was added as a translation target for node Llamas are cool.");
    // Check that the requested locale is the right one.
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('zh_CN');
    $this->clickLink('ZH');
    $this->assertText('The zh_CN translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('zh_CN');
    $this->clickLink('ZH');
    $this->assertText('The translation of node Llamas are cool into zh_CN has been downloaded.');

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('zh_CN');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testSystemSiteTranslationUsingLinks() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Chinese translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText("Translation to zh_CN requested successfully");
    // Check that the requested locale is the right one.
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Chinese translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to zh_CN checked successfully");

    // Download the Chinese translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText('Translation to zh_CN downloaded successfully');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('zh_CN');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testContentTypeTranslationUsingLinks() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText("Translation to zh_CN requested successfully");
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/node_type/article/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to zh_CN status checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/node_type/article/zh_CN?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText('Translation to zh_CN downloaded successfully');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('zh_CN');
  }

}
