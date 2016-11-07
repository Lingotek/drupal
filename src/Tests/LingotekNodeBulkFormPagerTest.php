<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkFormPagerTest extends LingotekTestBase {

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
   * Tests that the bulk management pager works correctly.
   */
  public function testBulkPager() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $edit = array();
      $edit['title[0][value]'] = 'Llamas are cool ' . $i ;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Navigate to page 2.
    $this->clickLink(t('Page 2'));
    $this->assertUrl('admin/lingotek/manage/node?page=1');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/11?destination=' . $basepath .'/admin/lingotek/manage/node');
    $edit = [
      'table[11]' => TRUE,  // Node 11.
      'table[12]' => TRUE,  // Node 12.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // The current page is kept.
    $this->assertUrl('admin/lingotek/manage/node?page=1');

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/node');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool 11 is complete.');

    // The current page is kept.
    $this->assertUrl('admin/lingotek/manage/node?page=1');
  }

}
