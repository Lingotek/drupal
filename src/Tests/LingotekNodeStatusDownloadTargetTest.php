<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a node.
 *
 * @group lingotek
 */
class LingotekNodeStatusDownloadTargetTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

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
   * Tests that a node can be translated.
   */
  public function testNodeSourceStatusUponTargetDownload() {
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
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/node');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Edit the Source
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->drupalPostForm('node/1/edit', $edit, t('Save and keep published'));

    $this->goToContentBulkManagementForm();

    $edit = [
      'table[1]' => TRUE,  // Node 1.
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that the Spanish translation is still EDITED.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_MX');

    // Download the Spanish translation.
    $edit = [
      'table[1]' => TRUE,  // Node 1.
      'operation' => 'download'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check the status is not Current for source or for Spanish
    $source_edited = $this->xpath("//span[contains(@class,'language-icon') and contains(@class, 'source-edited')  and contains(@title, 'Re-upload (content has changed since last upload)')]");
    $this->assertEqual(count($source_edited), 1, 'Source is marked as edited.');
    $es_edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    $this->assertEqual(count($es_edited), 1, 'Spanish is marked as edited.');

  }
}
