<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests changing a profile using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkProfileTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  /**
   * @var \Drupal\node\Entity\NodeInterface
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
   * Tests that the translation profiles can be updated with the bulk actions.
   */
  public function testChangeTranslationProfileBulk() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create three nodes.
    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 4; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/2?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/3?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Manual Profile
    $manual_profile = $this->xpath("//td[contains(text(), 'Manual')]");
    $this->assertEqual(count($manual_profile), 3, 'There are three nodes with the Manual Profile set.');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'operation' => 'change_profile:automatic'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 3, 'There are three nodes with the Automatic Profile set.');

    $edit = [
      'table[2]' => TRUE,
      'operation' => 'change_profile:manual'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there is one node with the Manual Profile
    // Check that there are two nodes with the Automatic Profile
    $manual_profile = $this->xpath("//td[contains(text(), 'Manual')]");
    $this->assertEqual(count($manual_profile), 1, 'There is one node with the Manual Profile set.');
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 2, 'There are two nodes with the Automatic Profile set.');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'operation' => 'change_profile:disabled'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Disabled Profile
    $disabled_profile = $this->xpath("//td[contains(text(), 'Disabled')]");
    $this->assertEqual(count($disabled_profile), 3, 'There are three nodes with the Disabled Profile set.');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'operation' => 'change_profile:automatic'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 3, 'There are three nodes with the Automatic Profile set.');
  }

  /**
   * Tests that the translation profiles can be updated with the bulk actions after
   * disassociating.
   */
  public function testChangeTranslationProfileBulkAfterDisassociating() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create three nodes.
    $nodes = [];
    for ($i = 1; $i < 4; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/2?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/3?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'operation' => 'disassociate'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'operation' => 'change_profile:automatic'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 3, 'There are three nodes with the Automatic Profile set.');
  }

}
