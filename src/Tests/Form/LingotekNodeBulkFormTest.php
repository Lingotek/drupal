<?php

namespace Drupal\lingotek\Tests\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Tests\LingotekTestBase;

/**
 * Tests the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * A node used for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
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
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
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
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/11?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      // Node 11.
      'table[11]' => TRUE,
      // Node 12.
      'table[12]' => TRUE,
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // The current page is kept.
    $this->assertUrl('admin/lingotek/manage/node?page=1');

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool 11 is complete.');

    // The current page is kept.
    $this->assertUrl('admin/lingotek/manage/node?page=1');
  }

  /**
   * Tests that the bulk management profile filtering works correctly.
   */
  public function testProfileFilter() {
    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $profile = 'automatic';
      if ($i % 2 == 0) {
        $profile = 'manual';
      }
      elseif ($i % 3 == 0) {
        $profile = 'disabled';
      }

      $edit = array();
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = $profile;
      $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $this->assertLinkByHref('?page=1');

    // After we filter by automatic profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][profile]' => 'automatic',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $this->assertLink('Llamas are cool ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool 2');

    // After we filter by manual profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][profile]' => 'manual',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $this->assertLink('Llamas are cool ' . $j);
    }
    $this->assertNoLink('Page 2');
    $this->assertNoLink('Llamas are cool 1');

    // After we filter by disabled profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][profile]' => 'disabled',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([3, 9] as $j) {
      $this->assertLink('Llamas are cool ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool 5');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach (range(1, 10) as $j) {
      $this->assertLink('Llamas are cool ' . $j);
    }
    $this->assertLinkByHref('?page=1');
  }

  /**
   * Tests that the bulk management label filtering works correctly.
   */
  public function testLabelFilter() {
    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $animal = 'Llamas';
      if ($i % 2 == 0) {
        $animal = 'Dogs';
      }
      elseif ($i % 3 == 0) {
        $animal = 'Cats';
      }

      $edit = array();
      $edit['title[0][value]'] = $animal . ' are cool ' . $i;
      $edit['body[0][value]'] = $animal . ' are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $this->assertLinkByHref('?page=1');

    // After we filter by label 'Llamas', there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][label]' => 'Llamas',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $this->assertLink('Llamas are cool ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Dogs are cool 2');

    // After we filter by label 'Dogs', there is no pager and the rows selected
    // are the ones expected.
    $edit = [
      'filters[wrapper][label]' => 'Dogs',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $this->assertLink('Dogs are cool ' . $j);
    }
    $this->assertNoLink('Page 2');
    $this->assertNoLink('Llamas are cool 1');

    // After we filter by label 'Cats', there is no pager and the rows selected
    // are the ones expected.
    $edit = [
      'filters[wrapper][label]' => 'Cats',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([3, 9] as $j) {
      $this->assertLink('Cats are cool ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Dogs are cool 5');

    // After we reset, we get back to having a pager and all the content under
    // limit of 10.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach ([1, 5, 7] as $j) {
      $this->assertLink('Llamas are cool ' . $j);
    }
    foreach ([2, 4, 6, 8, 10] as $j) {
      $this->assertLink('Dogs are cool ' . $j);
    }
    foreach ([3, 9] as $j) {
      $this->assertLink('Cats are cool ' . $j);
    }

    $this->assertLinkByHref('?page=1');
  }

  /**
   * Tests that the bulk management profile filtering works correctly.
   */
  public function testLanguageFilter() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $langcode = 'es';
      if ($i % 2 == 0) {
        $langcode = 'it';
      }
      elseif ($i % 3 == 0) {
        $langcode = 'en';
      }

      $edit = [];
      $edit['title[0][value]'] = new FormattableMarkup('Llamas are cool @langcode @i', ['@langcode' => strtoupper($langcode), '@i' => $i]);
      $edit['body[0][value]'] = $edit['title[0][value]'];
      $edit['langcode[0][value]'] = $langcode;
      $edit['lingotek_translation_profile'] = 'manual';
      $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $this->assertLinkByHref('?page=1');

    // After we filter by automatic profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][source_language]' => 'es',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $this->assertLink('Llamas are cool ES ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool IT 2');

    // After we filter by manual profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][source_language]' => 'it',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $this->assertLink('Llamas are cool IT ' . $j);
    }
    $this->assertNoLink('Page 2');
    $this->assertNoLink('Llamas are cool ES 1');

    // After we filter by disabled profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][source_language]' => 'en',
    ];
    $this->drupalPostForm(NULL, $edit, 'Filter');
    foreach ([3, 9] as $j) {
      $this->assertLink('Llamas are cool EN ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool ES 5');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach ([1, 5, 7] as $j) {
      $this->assertLink('Llamas are cool ES ' . $j);
    }
    foreach ([2, 4, 6, 8, 10] as $j) {
      $this->assertLink('Llamas are cool IT ' . $j);
    }
    foreach ([3, 9] as $j) {
      $this->assertLink('Llamas are cool EN ' . $j);
    }
    $this->assertLinkByHref('?page=1');
  }

}
