<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

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

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
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
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
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
    // See https://www.drupal.org/project/drupal/issues/2925290.
    $indexes = "ABCDEFGHIJKLMNOPQ";
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $profile = 'automatic';
      if ($i % 2 == 0) {
        $profile = 'manual';
      }
      elseif ($i % 3 == 0) {
        $profile = Lingotek::PROFILE_DISABLED;
      }

      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $indexes[$i];
      $edit['body[0][value]'] = 'Llamas are very cool ' . $indexes[$i];
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = $profile;
      $this->saveAndPublishNodeForm($edit);
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
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool ' . $indexes[2]);

    // After we filter by manual profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][profile]' => 'manual',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
    }
    $this->assertNoLink('Page 2');
    $this->assertNoLink('Llamas are cool ' . $indexes[1]);

    // After we filter by disabled profile, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][profile]' => Lingotek::PROFILE_DISABLED,
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([3, 9] as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool ' . $indexes[5]);

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach (range(1, 10) as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
    }
    $this->assertLinkByHref('?page=1');
  }

  /**
   * Tests that the bulk management job filtering works correctly.
   */
  public function testJobIdFilter() {
    $basepath = \Drupal::request()->getBasePath();

    $nodes = [];
    // See https://www.drupal.org/project/drupal/issues/2925290.
    $indexes = "ABCDEFGHIJKLMNOPQ";
    // Create some nodes.
    for ($i = 1; $i < 15; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $indexes[$i];
      $edit['body[0][value]'] = 'Llamas are very cool ' . $indexes[$i];
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $this->assertLinkByHref('?page=1');

    // After we filter by an unexisting job, there is no content and no rows.
    $edit = [
      'filters[wrapper][job]' => 'this job does not exist',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $this->assertNoLink('Llamas are cool');
    $this->assertNoLinkByHref('?page=1');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach (range(1, 10) as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
    }
    $this->assertLinkByHref('?page=1');

    // Show 50 results.
    \Drupal::service('user.private_tempstore')->get('lingotek.management.items_per_page')->set('limit', 50);
    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/11?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[4]' => TRUE,
      'table[6]' => TRUE,
      'table[8]' => TRUE,
      'table[10]' => TRUE,
      'table[12]' => TRUE,
      'table[14]' => TRUE,
      'operation' => 'upload',
      'job_id' => 'even numbers',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'table[5]' => TRUE,
      'table[7]' => TRUE,
      'table[11]' => TRUE,
      'table[13]' => TRUE,
      'operation' => 'upload',
      'job_id' => 'prime numbers',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Show 10 results.
    \Drupal::service('user.private_tempstore')->get('lingotek.management.items_per_page')->set('limit', 10);
    $this->goToContentBulkManagementForm();

    // After we filter by prime, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][job]' => 'prime',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 2, 3, 5, 7, 11, 13] as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
    }
    $this->assertNoLink('Page 2');
    $this->assertNoLink('Llamas are cool ' . $indexes[4]);

    // After we filter by even, there is no pager and the rows selected are the
    // ones expected.
    $edit = [
      'filters[wrapper][job]' => 'even',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([4, 6, 8, 10, 12, 14] as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool ' . $indexes[5]);

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach (range(1, 10) as $j) {
      $this->assertLink('Llamas are cool ' . $indexes[$j]);
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

      $edit = [];
      $edit['title[0][value]'] = $animal . ' are cool ' . $i;
      $edit['body[0][value]'] = $animal . ' are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
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
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
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
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
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
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
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
   * Tests that the bulk management language filtering works correctly.
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
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $this->assertLinkByHref('?page=1');

    // After we filter by Spanish source language, there is no pager and the
    // rows selected are the ones expected.
    $edit = [
      'filters[wrapper][source_language]' => 'es',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $this->assertLink('Llamas are cool ES ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool IT 2');

    // After we filter by Italian source language, there is no pager and the
    // rows selected are the ones expected.
    $edit = [
      'filters[wrapper][source_language]' => 'it',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $this->assertLink('Llamas are cool IT ' . $j);
    }
    $this->assertNoLink('Page 2');
    $this->assertNoLink('Llamas are cool ES 1');

    // After we filter by English source language, there is no pager and the
    // rows selected are the ones expected.
    $edit = [
      'filters[wrapper][source_language]' => 'en',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
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

  /**
   * Tests that the bulk management bundle filtering works correctly.
   */
  public function testBundleFilter() {
    // Create Page node types.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['page']);

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $bundle = 'page';
      if ($i % 3 == 0) {
        $bundle = 'article';
      }

      $edit = [];
      $edit['title[0][value]'] = new FormattableMarkup('Llamas are cool @bundle @i', ['@bundle' => $bundle, '@i' => $i]);
      $edit['body[0][value]'] = $edit['title[0][value]'];
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_profile'] = 'manual';
      $this->saveAndPublishNodeForm($edit, $bundle);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $this->assertLinkByHref('?page=1');

    // After we filter by article, there is no pager and the rows selected are
    // the ones expected.
    $edit = [
      'filters[wrapper][bundle]' => 'page',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 2, 4, 5, 7, 8, 10, 11, 13, 14] as $j) {
      $this->assertLink('Llamas are cool page ' . $j);
    }
    $this->assertNoLinkByHref('?page=1');
    $this->assertNoLink('Llamas are cool article 3');

    // After we filter by page, there is no pager and the rows selected are the
    // ones expected.
    $edit = [
      'filters[wrapper][bundle]' => 'article',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([3, 6, 9, 12] as $j) {
      $this->assertLink('Llamas are cool article ' . $j);
    }
    $this->assertNoLink('Page 2');
    $this->assertNoLink('Llamas are cool page 1');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach ([1, 2, 4, 5, 7, 8, 10] as $j) {
      $this->assertLink('Llamas are cool page ' . $j);
    }
    foreach ([3, 6, 9] as $j) {
      $this->assertLink('Llamas are cool article ' . $j);
    }
    $this->assertLinkByHref('?page=1');
  }

  /**
   * Tests that the node bulk form doesn't show a language if it's disabled.
   */
  public function testDisabledLanguage() {
    // Create an article.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Go and upload this node.
    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('EN');

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    // And we can already request a translation for Spanish.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');

    // Then we disable the Spanish language.
    \Drupal::service('lingotek.configuration')->disableLanguage(ConfigurableLanguage::load('es'));

    // And we check that Spanish is not there anymore.
    $this->goToContentBulkManagementForm();
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');

    // We re-enable Spanish.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfig */
    $lingotekConfig = \Drupal::service('lingotek.configuration');
    $language = ConfigurableLanguage::load('es');
    $lingotekConfig->enableLanguage($language);

    // And Spanish should be back in the management form.
    $this->goToContentBulkManagementForm();
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
  }

  /**
   * Tests default profile is shown in the content management page.
   */
  public function testDefaultProfile() {
    // Create Page node types.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Enable translation for the page entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $nodes = [];
    // Create a node.
    for ($i = 1; $i <= 3; $i++) {
      $bundle = 'page';
      $edit = [];
      $edit['title[0][value]'] = new FormattableMarkup('Llamas are cool @bundle @i', ['@bundle' => $bundle, '@i' => $i]);
      $edit['body[0][value]'] = $edit['title[0][value]'];
      $edit['langcode[0][value]'] = 'en';
      $this->saveAndPublishNodeForm($edit, $bundle);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();
    // Ensure there is no profile shown.
    for ($i = 1; $i <= 3; $i++) {
      $this->assertManagementFormProfile($i, 'Not enabled');
    }

    // Enable automatic profile for pages.
    $this->saveLingotekContentTranslationSettingsForNodeTypes(['page'], 'automatic');

    // Now we should see the automatic profile.
    $this->goToContentBulkManagementForm();
    // Ensure there is Automatic profile shown.
    for ($i = 1; $i <= 3; $i++) {
      $this->assertManagementFormProfile($i, 'Automatic');
    }

    // Let's upload one node. The profile should be stored.
    $this->clickLink('EN');
    // Ensure there is Automatic profile shown.
    for ($i = 1; $i <= 3; $i++) {
      $this->assertManagementFormProfile($i, 'Automatic');
    }

    // Now we change the default profile. Should still be the same for the node
    // we uploaded.
    $this->saveLingotekContentTranslationSettingsForNodeTypes(['page'], 'manual');

    $this->goToContentBulkManagementForm();
    // Ensure there is Automatic profile for node 1, but Manual profile for
    // nodes 2 and 3.
    $this->assertManagementFormProfile(1, 'Automatic');
    for ($i = 2; $i <= 3; $i++) {
      $this->assertManagementFormProfile($i, 'Manual');
    }
  }

  /**
   * Tests that default profile is used after dissasociation.
   */
  public function testUploadAfterDisassociating() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');

    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[1]' => TRUE,
      'operation' => 'disassociate',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertIdentical(NULL, \Drupal::state()->get('lingotek.uploaded_job_id'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');

    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there is a node with the Automatic (default) Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 1, 'There is one node with the Automatic Profile set.');
  }

  /**
   * Tests job id is uploaded on upload.
   */
  public function testJobIdOnUpload() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/2?destination=' . $basepath . '/admin/lingotek/manage/node');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'job_id' => 'my_custom_job_id',
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertIdentical('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEqual('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }
  }

  /**
   * Tests job id is uploaded on update.
   */
  public function testJobIdOnUpdate() {
    // Create a node with automatic. This will trigger upload.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertNull($metadata->getJobId(), 'There was no job id to save along with metadata.');
    }

    $basepath = \Drupal::request()->getBasePath();

    // I can check the status of the upload. So next operation will perform an
    // update.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id-1?destination=' . $basepath . '/admin/lingotek/manage/node');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'job_id' => 'my_custom_job_id',
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertIdentical('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEquals('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }
  }

}
