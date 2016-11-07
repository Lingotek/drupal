<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a node into locales using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkLocaleTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'comment'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Create a locale outside of Lingotek dashboard.
    ConfigurableLanguage::create(['id' => 'de-at', 'name' => 'German (AT)'])->save();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

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

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testNodeTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath .'/admin/lingotek/manage/node');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_AR?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('EN');

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/node');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_AR?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('DE-AT');
    $this->assertText("Locale 'de_AT' was added as a translation target for node Llamas are cool.");
    // Check that the requested locale is the right one.
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_AR?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");
    // Check that the requested locale is the right one.
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_AR?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_AR?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

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

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/node');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath .'/admin/lingotek/manage/node');

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('DE-AT');
    $this->assertText("Locale 'de_AT' was added as a translation target for node Llamas are cool.");

    // Check that the source status has been updated.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/node');
  }

}
