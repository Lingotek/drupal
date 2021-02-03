<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a config entity into locales using the bulk management form.
 *
 * @group lingotek
 */
class LingotekContentTypeBulkLocaleTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'comment'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create a locale outside of Lingotek dashboard.
    ConfigurableLanguage::create(['id' => 'de-at', 'name' => 'German (AT)'])->save();

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();
    ConfigurableLanguage::createFromLangcode('es-es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testSystemSiteTranslationUsingLinks() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_AR?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_AR?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

    // Request the German (AT) translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('DE-AT');
    $this->assertText("Translation to de_AT requested successfully");
    // Check that the requested locale is the right one.
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    \Drupal::state()->resetCache();

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_AR?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_AR requested successfully");
    // Check that the requested locale is the right one.
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/node_type/article/es_AR?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_AR status checked successfully");

    // Download the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/node_type/article/es_AR?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_AR downloaded successfully');
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'ES');

    // Check that the order of target languages is always alphabetical.
    $target_links = $this->xpath("//a[contains(@class,'language-icon')]");
    $this->assertEqual(count($target_links), 3, 'The three languages appear as targets');
    $this->assertEqual('DE-AT', $target_links[0]->getHtml(), 'DE-AT is the first language');
    $this->assertEqual('ES', $target_links[1]->getHtml(), 'ES is the second language');
    $this->assertEqual('ES-ES', $target_links[2]->getHtml(), 'ES-ES is the third language');
  }

  /**
   * Tests that source is updated after requesting translation.
   */
  public function testSourceUpdatedAfterRequestingTranslation() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Upload it
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Request the German (AT) translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('DE-AT');
    $this->assertText("Translation to de_AT requested successfully");

    // Check that the source status has been updated.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
  }

}
