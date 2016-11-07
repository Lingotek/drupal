<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a config entity into locales using the bulk management form.
 *
 * @group lingotek
 */
class LingotekContentTypeBulkLocaleTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'comment'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));
    
    // Create a locale outside of Lingotek dashboard.
    ConfigurableLanguage::create(['id' => 'de-at', 'name' => 'German (AT)'])->save();
    
    // Add locales.
    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testSystemSiteTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_AR?destination=' . $basepath .'/admin/lingotek/config/manage');

    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_AR?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('DE-AT');
    $this->assertText("Translation to de_AT requested successfully");
    // Check that the requested locale is the right one.
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_AR?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_AR requested successfully");
    // Check that the requested locale is the right one.
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/node_type/article/es_AR?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_AR status checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/node_type/article/es_AR?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_AR downloaded successfully');
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_AR');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/es_AR' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');

    // Check that the order of target languages is always alphabetical.
    $target_links = $this->xpath("//a[contains(@class,'language-icon')]");
    $this->assertEqual(count($target_links), 3, 'The three languages appear as targets');
    $this->assertEqual('DE-AT', (string)$target_links[0], 'DE-AT is the first language');
    $this->assertEqual('ES', (string)$target_links[1], 'ES is the second language');
    $this->assertEqual('ES-ES', (string)$target_links[2], 'ES-ES is the third language');
  }

  /**
   * Tests that source is updated after requesting translation.
   */
  public function testSourceUpdatedAfterRequestingTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Upload it
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/de_AT?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('DE-AT');
    $this->assertText("Translation to de_AT requested successfully");

    // Check that the source status has been updated.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath .'/admin/lingotek/config/manage');
  }

}
