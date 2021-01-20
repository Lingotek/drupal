<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
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
  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $this->drupalCreateContentType(['type' => 'custom_type', 'name' => 'Custom Type']);
    $this->drupalCreateContentType(['type' => 'not_configured', 'name' => 'Not Configured']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'custom_type')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'custom_type', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'not_configured')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'not_configured', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that the bulk management pager works correctly.
   */
  public function testBulkPager() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Navigate to page 2.
    $this->clickLink(t('Page 2'));
    $this->assertUrl('admin/lingotek/manage/node?page=1');

    // I can init the upload of content.
    $this->assertLingotekUploadLink(11, 'node');
    $this->assertLingotekUploadLink(12, 'node');

    $key1 = $this->getBulkSelectionKey('en', 11);
    $key2 = $this->getBulkSelectionKey('en', 12);

    $edit = [
      // Node 11.
      $key1 => TRUE,
      // Node 12.
      $key2 => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // The current page is kept.
    $this->assertUrl('admin/lingotek/manage/node?page=1');

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool 11 is complete.');

    // The current page is kept.
    $this->assertUrl('admin/lingotek/manage/node?page=1');
  }

  /**
   * Tests that the bulk management profile filtering works correctly.
   */
  public function testProfileFilter() {
    $assert_session = $this->assertSession();

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
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = $profile;
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we filter by automatic profile, there is no pager and the rows
    // listed are the ones expected.
    $edit = [
      'filters[advanced_options][profile][]' => 'automatic',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    foreach ([1, 5, 7, 11, 13] as $j) {
      // The filtered id is shown, but not others.
      $assert_session->linkExists('Llamas are cool ' . $indexes[$j]);
      $assert_session->linkNotExists('Llamas are cool 2');

      // The value is retained in the filter.
      $this->assertFieldByName('filters[advanced_options][profile][]', 'automatic', 'The value is retained in the filter.');
    }

    // After we filter by manual profile, there is no pager and the rows
    // listed are the ones expected.
    $edit = [
      'filters[advanced_options][profile][]' => 'manual',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      // The filtered id is shown, but not others.
      $assert_session->linkExists('Llamas are cool ' . $indexes[$j]);
      $assert_session->linkNotExists('Llamas are cool 2');

      // The value is retained in the filter.
      $this->assertFieldByName('filters[advanced_options][profile][]', 'manual', 'The value is retained in the filter.');
    }

    // After we filter by disabled profile, there is no pager and the rows
    // listed are the ones expected.
    $edit = [
      'filters[advanced_options][profile][]' => 'disabled',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    foreach ([3, 9] as $j) {
      // The filtered id is shown, but not others.
      $assert_session->linkExists('Llamas are cool ' . $indexes[$j]);
      $assert_session->linkNotExists('Llamas are cool 2');

      // The value is retained in the filter.
      $this->assertFieldByName('filters[advanced_options][profile][]', 'disabled', 'The value is retained in the filter.');
    }

    $assert_session->linkNotExists('Llamas are cool 15');
    $assert_session->linkByHrefNotExists('?page=1');
  }

  /**
   * Tests that the bulk management job filter works correctly.
   */
  public function testJobIdFilter() {
    $assert_session = $this->assertSession();

    $nodes = [];
    // See https://www.drupal.org/project/drupal/issues/2925290.
    $indexes = "ABCDEFGHIJKLMNOPQ";
    // Create some nodes.
    for ($i = 1; $i < 15; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $indexes[$i];
      $edit['body[0][value]'] = 'Llamas are very cool ' . $indexes[$i];
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we filter by an unexisting job, there is no content and no rows.
    $edit = [
      'filters[wrapper][job]' => 'this job does not exist',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $assert_session->linkNotExists('Llamas are cool');
    $assert_session->linkByHrefNotExists('?page=1');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach (range(1, 10) as $j) {
      $assert_session->linkExists('Llamas are cool ' . $indexes[$j]);
    }
    $assert_session->linkByHrefExists('?page=1');

    // Show 50 results.
    \Drupal::service('tempstore.private')->get('lingotek.management.items_per_page')->set('limit', 50);
    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(11, 'node');
    $edit = [
      'table[4]' => TRUE,
      'table[6]' => TRUE,
      'table[8]' => TRUE,
      'table[10]' => TRUE,
      'table[12]' => TRUE,
      'table[14]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
      'job_id' => 'even numbers',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      'table[5]' => TRUE,
      'table[7]' => TRUE,
      'table[11]' => TRUE,
      'table[13]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
      'job_id' => 'prime numbers',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Show 10 results.
    \Drupal::service('tempstore.private')->get('lingotek.management.items_per_page')->set('limit', 10);
    $this->goToContentBulkManagementForm();

    // After we filter by prime, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][job]' => 'prime',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 2, 3, 5, 7, 11, 13] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $indexes[$j]);
    }
    $assert_session->linkNotExists('Page 2');
    $assert_session->linkNotExists('Llamas are cool ' . $indexes[4]);

    $this->assertFieldByName('filters[wrapper][job]', 'prime', 'The value is retained in the filter.');

    // After we filter by even, there is no pager and the rows selected are the
    // ones expected.
    $edit = [
      'filters[wrapper][job]' => 'even',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([4, 6, 8, 10, 12, 14] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $indexes[$j]);
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Llamas are cool ' . $indexes[5]);

    $this->assertFieldByName('filters[wrapper][job]', 'even', 'The value is retained in the filter.');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach (range(1, 10) as $j) {
      $assert_session->linkExists('Llamas are cool ' . $indexes[$j]);
    }
    $assert_session->linkByHrefExists('?page=1');
  }

  /**
   * Tests that the bulk management label filtering works correctly.
   */
  public function testLabelFilter() {
    $assert_session = $this->assertSession();

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
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we filter by label 'Llamas', there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][label]' => 'Llamas',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j);
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Dogs are cool 2');

    $this->assertFieldByName('filters[wrapper][label]', 'Llamas', 'The value is retained in the filter.');

    // After we filter by label 'Dogs', there is no pager and the rows selected
    // are the ones expected.
    $edit = [
      'filters[wrapper][label]' => 'Dogs',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $assert_session->linkExists('Dogs are cool ' . $j);
    }
    $assert_session->linkNotExists('Page 2');
    $assert_session->linkNotExists('Llamas are cool 1');

    $this->assertFieldByName('filters[wrapper][label]', 'Dogs', 'The value is retained in the filter.');

    // After we filter by label 'Cats', there is no pager and the rows selected
    // are the ones expected.
    $edit = [
      'filters[wrapper][label]' => 'Cats',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Cats are cool ' . $j);
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Dogs are cool 5');

    $this->assertFieldByName('filters[wrapper][label]', 'Cats', 'The value is retained in the filter.');

    // After we reset, we get back to having a pager and all the content under
    // limit of 10.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach ([1, 5, 7] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j);
    }
    foreach ([2, 4, 6, 8, 10] as $j) {
      $assert_session->linkExists('Dogs are cool ' . $j);
    }
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Cats are cool ' . $j);
    }

    $assert_session->linkByHrefExists('?page=1');

    // If we filter with extra spaces, we still show content.
    $edit = [
      'filters[wrapper][label]' => '  Cats   ',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Cats are cool ' . $j);
    }
    $this->assertFieldByName('filters[wrapper][label]', 'Cats', 'The value is trimmed in the filter.');
  }

  /**
   * Tests that the bulk management language filtering works correctly.
   */
  public function testLanguageFilter() {
    $assert_session = $this->assertSession();

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
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we filter by Spanish source language, there is no pager and the
    // rows selected are the ones expected.
    $edit = [
      'filters[advanced_options][source_language]' => 'es',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $assert_session->linkExists('Llamas are cool ES ' . $j);
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Llamas are cool IT 2');

    $this->assertFieldByName('filters[advanced_options][source_language]', 'es', 'The value is retained in the filter.');

    // After we filter by Italian source language, there is no pager and the
    // rows selected are the ones expected.
    $edit = [
      'filters[advanced_options][source_language]' => 'it',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $assert_session->linkExists('Llamas are cool IT ' . $j);
    }
    $assert_session->linkNotExists('Page 2');
    $assert_session->linkNotExists('Llamas are cool ES 1');

    $this->assertFieldByName('filters[advanced_options][source_language]', 'it', 'The value is retained in the filter.');

    // After we filter by English source language, there is no pager and the
    // rows selected are the ones expected.
    $edit = [
      'filters[advanced_options][source_language]' => 'en',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Llamas are cool EN ' . $j);
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Llamas are cool ES 5');

    $this->assertFieldByName('filters[advanced_options][source_language]', 'en', 'The value is retained in the filter.');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach ([1, 5, 7] as $j) {
      $assert_session->linkExists('Llamas are cool ES ' . $j);
    }
    foreach ([2, 4, 6, 8, 10] as $j) {
      $assert_session->linkExists('Llamas are cool IT ' . $j);
    }
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Llamas are cool EN ' . $j);
    }
    $assert_session->linkByHrefExists('?page=1');
  }

  /**
   * Tests that the bulk management bundle filtering works correctly.
   */
  public function testBundleFilter() {
    $assert_session = $this->assertSession();

    // Create Page node types.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    ContentLanguageSettings::loadByEntityTypeBundle('node', 'custom_type')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'custom_type', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['page']);
    $this->saveLingotekContentTranslationSettingsForNodeTypes(['custom_type']);

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $bundle = 'page';
      if ($i % 2 == 0) {
        $bundle = 'custom_type';
      }
      if ($i % 3 == 0) {
        $bundle = 'article';
      }

      $edit = [];
      $edit['title[0][value]'] = new FormattableMarkup('Llamas are cool @bundle @i', ['@bundle' => $bundle, '@i' => $i]);
      $edit['body[0][value]'] = $edit['title[0][value]'];
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit, $bundle);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we filter by article, there is no pager and the rows selected are
    // the ones expected.
    $edit = [
      'filters[wrapper][bundle][]' => 'page',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $assert_session->linkExists('Llamas are cool page ' . $j);
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Llamas are cool article 3');

    $this->assertFieldByName('filters[wrapper][bundle][]', 'page', 'The value is retained in the filter.');

    // After we filter by custom_type, there is no pager and the rows selected are
    // the ones expected.
    $edit = [
      'filters[wrapper][bundle][]' => 'custom_type',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([2, 4, 8, 10, 14] as $j) {
      $assert_session->linkExists('Llamas are cool custom_type ' . $j);
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Llamas are cool article 3');
    $assert_session->linkNotExists('Llamas are cool page 1');

    $this->assertFieldByName('filters[wrapper][bundle][]', 'custom_type', 'The value is retained in the filter.');

    // After we filter by article, there is no pager and the rows selected are the
    // ones expected.
    $edit = [
      'filters[wrapper][bundle][]' => 'article',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([3, 6, 9, 12] as $j) {
      $assert_session->linkExists('Llamas are cool article ' . $j);
    }
    $assert_session->linkNotExists('Page 2');
    $assert_session->linkNotExists('Llamas are cool page 1');

    $this->assertFieldByName('filters[wrapper][bundle][]', 'article', 'The value is retained in the filter.');

    // After we filter by both page and article, there is no pager and the rows
    // selected are the ones expected.
    $edit = [];
    $edit['filters[wrapper][bundle][]'] = [
        'page',
        'article',
      ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 5, 7] as $j) {
      $assert_session->linkExists('Llamas are cool page ' . $j);
    }
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Llamas are cool article ' . $j);
    }

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach ([1, 5, 7] as $j) {
      $assert_session->linkExists('Llamas are cool page ' . $j);
    }
    foreach ([2, 4, 8, 10] as $j) {
      $assert_session->linkExists('Llamas are cool custom_type ' . $j);
    }
    foreach ([3, 6, 9] as $j) {
      $assert_session->linkExists('Llamas are cool article ' . $j);
    }
    $assert_session->linkByHrefExists('?page=1');
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
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Go and upload this node.
    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation for Spanish.
    $this->assertLingotekRequestTranslationLink('es_MX');

    // Then we disable the Spanish language.
    \Drupal::service('lingotek.configuration')->disableLanguage(ConfigurableLanguage::load('es'));

    // And we check that Spanish is not there anymore.
    $this->goToContentBulkManagementForm();
    $this->assertNoLingotekRequestTranslationLink('es_MX');

    // We re-enable Spanish.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfig */
    $lingotekConfig = \Drupal::service('lingotek.configuration');
    $language = ConfigurableLanguage::load('es');
    $lingotekConfig->enableLanguage($language);

    // And Spanish should be back in the management form.
    $this->goToContentBulkManagementForm();
    $this->assertLingotekRequestTranslationLink('es_MX');
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
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
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
   * Tests that default profile is used after cancelation.
   */
  public function testUploadAfterCancelation() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // The node was re-uploaded and target statuses reset.
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
  }

  /**
   * Tests that default profile is used after cancelation.
   */
  public function testNoAutomaticUploadAfterCancelation() {
    $assert_session = $this->assertSession();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->goToContentBulkManagementForm();
    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);
  }

  /**
   * Tests if job id is uploaded on upload.
   */
  public function testJobIdOnUpload() {
    $assert_session = $this->assertSession();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'job_id' => 'my_custom_job_id',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];

    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertIdentical('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEqual('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }

    // The column for Job ID exists and there are values.
    $this->assertText('Job ID');
    $this->assertText('my_custom_job_id');
  }

  /**
   * Tests job id is uploaded on update.
   */
  public function testJobIdOnUpdate() {
    $assert_session = $this->assertSession();

    // Create a node with automatic. This will trigger upload.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertNull($metadata->getJobId(), 'There was no job id to save along with metadata.');
    }

    // I can check the status of the upload. So next operation will perform an
    // update.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id');
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id-1');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'job_id' => 'my_custom_job_id',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertIdentical('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEquals('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }

    // The column for Job ID exists and there are values.
    $this->assertText('Job ID');
    $this->assertText('my_custom_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation.
   */
  public function testAssignJobIds() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no upload.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID without notification to the TMS, no update happens.
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no upload.
    \Drupal::state()->resetCache();
    $this->assertNotNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNotNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdate() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is an update.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithADocumentLockedError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Document node Llamas are cool has a new version. The document id has been updated for all future interactions. Please try again.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithADocumentArchivedError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Document node Llamas are cool has been archived. Please upload again.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithAPaymentRequiredError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdateWithAnError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The Job ID change submission for node Llamas are cool failed. Please try again.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is no update.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    $this->assertText('my_custom_job_id');
    $this->assertText('other_job_id');
  }

  /**
   * Tests that we cannot assign job ids with invalid chars.
   */
  public function testAssignInvalidJobIdsWithTMSUpdate() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(2, 'node');
    $this->clickLink('EN');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'job_id' => 'my\invalid\id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The job ID name cannot contain invalid chars as "/" or "\".');

    // There is no update, because it's not valid.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    $edit = [
      'job_id' => 'my/invalid/id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The job ID name cannot contain invalid chars as "/" or "\".');

    // There is no update, because it's not valid.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));
  }

  /**
   * Tests that can we cancel assignation of job ids with the bulk operation.
   */
  public function testCancelAssignJobIds() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    // Canceling resets.
    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Llamas are cool');
    $this->assertNoText('Dogs are cool');
    $this->drupalPostForm(NULL, [], 'Cancel');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertNoText('Llamas are cool');
    $this->assertText('Dogs are cool');
  }

  /**
   * Tests that can we reset assignation of job ids with the bulk operation.
   */
  public function testResetAssignJobIds() {
    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    // Canceling resets.
    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Llamas are cool');
    $this->assertNoText('Dogs are cool');

    $this->goToContentBulkManagementForm();

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertNoText('Llamas are cool');
    $this->assertText('Dogs are cool');
  }

  /**
   * Tests clearing job ids.
   */
  public function testClearJobIds() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $this->clickLink('EN');

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, [], 'Clear Job ID');
    $this->assertText('Job ID was cleared successfully.');

    // There is no upload.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertNoText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdate() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $this->clickLink('EN');

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Job ID was cleared successfully.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertNoText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithAnError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $this->clickLink('EN');

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('The Job ID change submission for node Llamas are cool failed. Please try again.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithADocumentArchivedError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $this->clickLink('EN');

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Document node Llamas are cool has been archived. Please upload again.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithADocumentLockedError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $this->clickLink('EN');

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Document node Llamas are cool has a new version. The document id has been updated for all future interactions. Please try again.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdateWithAPaymentRequiredError() {
    $assert_session = $this->assertSession();

    // Create a couple of nodes.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $this->clickLink('EN');

    $edit = [
      'table[1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    // If we update the job ID with notification to the TMS, an update happens.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
    $this->assertText('Job ID for some content failed to sync to the TMS.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Test if doc id filter works
   */
  public function testDocIdFilter() {
    $assert_session = $this->assertSession();

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');

    foreach (range(1, 10) as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j);
    }

    $assert_session->linkByHrefExists('?page=1');

    // After we filter by an existing document_id, there are filtered rows.
    $edit = [
    'filters[advanced_options][document_id]' => '1',
    ];

    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    // Our fake doc ids are dummy-document-hash-id-X. We know we will find
    // dummy-document-hash-id, dummy-document-hash-id-1 and those after dummy-document-hash-id-10.
    foreach ([1, 2, 11, 12, 13, 14] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j);
    }

    // And we won't find the others.
    foreach ([3, 4, 5, 6, 7, 8, 9, 10] as $j) {
      $assert_session->linkNotExists('Llamas are cool ' . $j);
    }

    $this->assertFieldByName('filters[advanced_options][document_id]', 1, 'The value is retained in the filter.');

    $assert_session->linkByHrefNotExists('?page=1');
  }

  /**
   * Test if doc id filter works with multiple values.
   */
  public function testDocIdFilterMultiple() {
    $assert_session = $this->assertSession();

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we filter by an existing document_id, there are filtered rows.
    $edit = [
      'filters[advanced_options][document_id]' => 'dummy-document-hash-id-2, dummy-document-hash-id-3',
    ];

    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    // Our fake doc ids are dummy-document-hash-id-X. We know we will find
    // dummy-document-hash-id-2 and dummy-document-hash-id-3.
    $assert_session->linkExists('Llamas are cool 3');
    $assert_session->linkExists('Llamas are cool 4');
    $assert_session->linkNotExists('Llamas are cool 1');

    $this->assertFieldByName('filters[advanced_options][document_id]', 'dummy-document-hash-id-2, dummy-document-hash-id-3', 'The value is retained in the filter.');

    // Assert there is no pager.
    $assert_session->linkByHrefNotExists('?page=1');
  }

  /**
   * Tests if entity id filter works with multiple values.
   */
  public function testEntityIdFilterMultiple() {
    $assert_session = $this->assertSession();

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }
    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    $edit = [
      'filters[advanced_options][entity_id]' => '1,2,4',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    // The filtered id is shown, but not others.
    $assert_session->linkExists('Llamas are cool 1');
    $assert_session->linkExists('Llamas are cool 2');
    $assert_session->linkExists('Llamas are cool 4');
    $assert_session->linkNotExists('Llamas are cool 3');

    // The value is retained in the filter.
    $this->assertFieldByName('filters[advanced_options][entity_id]', '1,2,4', 'The value is retained in the filter.');
    $assert_session->linkByHrefNotExists('?page=1');
  }

  /**
   * Tests if entity id filter works
   */
  public function testEntityIdFilter() {
    $assert_session = $this->assertSession();

    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 15; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    foreach (range(1, 14) as $j) {
      $edit = [
        'filters[advanced_options][entity_id]' => $j,
      ];
      $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

      // The filtered id is shown, but not others.
      $assert_session->linkExists('Llamas are cool ' . $j);
      $assert_session->linkNotExists('Llamas are cool ' . ($j + 1));

      // The value is retained in the filter.
      $this->assertFieldByName('filters[advanced_options][entity_id]', $j, 'The value is retained in the filter.');
    }

    $assert_session->linkNotExists('Llamas are cool 15');
    $assert_session->linkByHrefNotExists('?page=1');
  }

  /**
   * Tests if source status filter works correctly
   */
  public function testSourceStatusFilter() {
    $assert_session = $this->assertSession();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Go to the bulk management form.
    $this->goToContentBulkManagementForm();

    // Ensure there is a link to upload and click it.
    $this->assertLingotekUploadLink();
    $this->clickLink('EN');

    $assert_session->optionExists('filters[advanced_options][source_status]', 'All');
    $assert_session->optionExists('filters[advanced_options][source_status]', 'UPLOAD_NEEDED');
    $assert_session->optionExists('filters[advanced_options][source_status]', 'CURRENT');
    $assert_session->optionExists('filters[advanced_options][source_status]', 'IMPORTING');
    $assert_session->optionExists('filters[advanced_options][source_status]', 'EDITED');
    $assert_session->optionExists('filters[advanced_options][source_status]', 'CANCELLED');
    $assert_session->optionExists('filters[advanced_options][source_status]', 'ERROR');

    // After we filter by "IMPORTING", there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[advanced_options][source_status]' => 'IMPORTING',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $assert_session->linkExists('Llamas are cool');

    $this->assertFieldByName('filters[advanced_options][source_status]', 'IMPORTING', 'The value is retained in the filter.');

    // Ensure there is a link to upload and click it.
    $this->assertLingotekCheckSourceStatusLink();
    $this->clickLink('EN');

    // After we filter by "CURRENT", there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[advanced_options][source_status]' => 'CURRENT',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $assert_session->linkExists('Llamas are cool');

    $this->assertFieldByName('filters[advanced_options][source_status]', 'CURRENT', 'The value is retained in the filter.');
  }

  /**
    * Tests if target status filter works correctly
    */
  public function testTargetStatusFilter() {
    $assert_session = $this->assertSession();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Go to the bulk management form.
    $this->goToContentBulkManagementForm();

    // Ensure there is a link to upload and click it.
    $this->assertLingotekUploadLink();
    $this->clickLink('EN');

    $assert_session->optionExists('filters[advanced_options][target_status]', 'All');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'CURRENT');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'EDITED');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'PENDING');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'READY');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'INTERMEDIATE');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'REQUEST');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'CANCELLED');
    $assert_session->optionExists('filters[advanced_options][target_status]', 'ERROR');

    // After we filter by "PENDING", there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[advanced_options][target_status]' => 'PENDING',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $this->assertFieldByName('filters[advanced_options][target_status]', 'PENDING', 'The value is retained in the filter.');
    $assert_session->linkNotExists('Llamas are cool');

    // Reset filters.
    $this->drupalPostForm(NULL, [], 'Reset');

    // Ensure there is a link to request and click it.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');

    // After we filter by "READY", there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[advanced_options][target_status]' => 'PENDING',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $this->assertFieldByName('filters[advanced_options][target_status]', 'PENDING', 'The value is retained in the filter.');
    $assert_session->linkExists('Llamas are cool');
  }

  /**
   * Tests if the "Needs Upload" source status filter works in combination
   * with other filters.
   */
  public function testNeedsUploadSourceStatusFilter() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article', 'custom_type'], 'manual');

    $node_defaults = [
      'type' => 'article',
      'langcode' => 'en',
    ];
    /** @var \Drupal\node\Entity\Node[] $nodes */
    $nodes = [
      Node::create(['title' => 'CustomType edited ready ready', 'type' => 'custom_type'] + $node_defaults),
      Node::create(['title' => 'Article current error current'] + $node_defaults),
      Node::create(['title' => 'Article importing null null'] + $node_defaults),
      Node::create(['title' => 'Article null null null'] + $node_defaults),
      Node::create(['title' => 'CustomType edited current edited', 'type' => 'custom_type'] + $node_defaults),
      Node::create(['title' => 'CustomType edited edited current', 'type' => 'custom_type', 'langcode' => 'de'] + $node_defaults),
      Node::create(['title' => 'Article error edited ready'] + $node_defaults),
      Node::create(['title' => 'Article current interim ready'] + $node_defaults),
      Node::create(['title' => 'CustomType error null null', 'type' => 'custom_type'] + $node_defaults),
      Node::create(['title' => 'CustomType current current ready', 'type' => 'custom_type'] + $node_defaults),
      Node::create(['title' => 'Article cancelled cancelled cancelled'] + $node_defaults),
    ];
    foreach ($nodes as $node) {
      $node->save();
    }

    $metadata_defaults = [
      'profile' => 'automatic',
      'translation_source' => 'en',
    ];
    $metadatas = [
      ['profile' => 'manual', 'translation_status' => [['language' => 'en', 'value' => 'edited'], ['language' => 'de', 'value' => 'ready'], ['language' => 'es', 'value' => 'ready']]] + $metadata_defaults,
      ['profile' => 'manual', 'translation_status' => [['language' => 'en', 'value' => 'current'], ['language' => 'de', 'value' => 'error'], ['language' => 'es', 'value' => 'current']]] + $metadata_defaults,
      ['translation_status' => [['language' => 'en', 'value' => 'importing']]] + $metadata_defaults,
      ['translation_status' => []] + $metadata_defaults,
      ['translation_status' => [['language' => 'en', 'value' => 'edited'], ['language' => 'de', 'value' => 'current'], ['language' => 'es', 'value' => 'edited']]] + $metadata_defaults,
      ['translation_status' => [['language' => 'en', 'value' => 'edited'], ['language' => 'de', 'value' => 'edited'], ['language' => 'es', 'value' => 'current']]] + $metadata_defaults,
      ['profile' => 'manual', 'translation_status' => [['language' => 'en', 'value' => 'error'], ['language' => 'de', 'value' => 'edited'], ['language' => 'es', 'value' => 'ready']]] + $metadata_defaults,
      ['translation_status' => [['language' => 'en', 'value' => 'current'], ['language' => 'de', 'value' => 'interim'], ['language' => 'es', 'value' => 'ready']]] + $metadata_defaults,
      ['translation_status' => [['language' => 'en', 'value' => 'error']]] + $metadata_defaults,
      ['translation_status' => [['language' => 'en', 'value' => 'current'], ['language' => 'de', 'value' => 'current'], ['language' => 'es', 'value' => 'ready']]] + $metadata_defaults,
      ['translation_status' => [['language' => 'en', 'value' => 'cancelled'], ['language' => 'de', 'value' => 'cancelled'], ['language' => 'es', 'value' => 'cancelled']]] + $metadata_defaults,
    ];
    $index = 0;
    foreach ($metadatas as $metadata_data) {
      ++$index;
      $metadata = LingotekContentMetadata::loadByTargetId('node', $index);
      $metadata->setDocumentId('document_id_' . $index);
      $metadata->set('translation_status', $metadata_data['translation_status']);
      $metadata->set('profile', $metadata_data['profile']);
      $metadata->set('translation_source', $metadata_data['translation_source']);
      $metadata->save();
    }
    Node::create(['title' => 'CustomType nothing nothing nothing', 'type' => 'custom_type'] + $node_defaults)->save();
    Node::create(['title' => 'NotConfigured nothing nothing nothing', 'type' => 'not_configured'] + $node_defaults)->save();

    $this->assertEquals(12, count(LingotekContentMetadata::loadMultiple()));

    $this->goToContentBulkManagementForm();

    $this->assertText('CustomType edited ready ready');
    $this->assertText('Article current error current');
    $this->assertText('Article importing null null');
    $this->assertText('Article null null null');
    $this->assertText('CustomType edited current edited');
    $this->assertText('CustomType edited edited current');
    $this->assertText('Article error edited ready');
    $this->assertText('Article current interim ready');
    $this->assertText('CustomType error null null');
    $this->assertText('CustomType current current ready');
    $this->assertNoText('CustomType nothing nothing nothing');
    $this->assertNoText('NotConfigured nothing nothing nothing');
    $this->assertNoText('Article cancelled cancelled cancelled');

    // Change page limit
    \Drupal::service('tempstore.private')->get('lingotek.management.items_per_page')->set('limit', 50);
    $this->goToContentBulkManagementForm();

    $this->assertText('CustomType edited ready ready');
    $this->assertText('Article current error current');
    $this->assertText('Article importing null null');
    $this->assertText('Article null null null');
    $this->assertText('CustomType edited current edited');
    $this->assertText('CustomType edited edited current');
    $this->assertText('Article error edited ready');
    $this->assertText('Article current interim ready');
    $this->assertText('CustomType error null null');
    $this->assertText('CustomType current current ready');
    $this->assertText('CustomType nothing nothing nothing');
    $this->assertText('NotConfigured nothing nothing nothing');
    $this->assertText('Article cancelled cancelled cancelled');

    $edit = [
      'filters[advanced_options][source_status]' => 'UPLOAD_NEEDED',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    $this->assertText('CustomType edited ready ready');
    $this->assertNoText('Article current error current');
    $this->assertNoText('Article importing null null');
    $this->assertText('Article null null null');
    $this->assertText('CustomType edited current edited');
    $this->assertText('CustomType edited edited current');
    $this->assertText('Article error edited ready');
    $this->assertNoText('Article current interim ready');
    $this->assertText('CustomType error null null');
    $this->assertNoText('CustomType current current ready');
    $this->assertText('CustomType nothing nothing nothing');
    $this->assertText('NotConfigured nothing nothing nothing');
    $this->assertText('Article cancelled cancelled cancelled');

    $edit = [
      'filters[advanced_options][source_status]' => 'UPLOAD_NEEDED',
      'filters[wrapper][bundle][]' => ['custom_type', 'not_configured'],
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    $this->assertText('CustomType edited ready ready');
    $this->assertNoText('Article current error current');
    $this->assertNoText('Article importing null null');
    $this->assertNoText('Article null null null');
    $this->assertText('CustomType edited current edited');
    $this->assertText('CustomType edited edited current');
    $this->assertNoText('Article error edited ready');
    $this->assertNoText('Article current interim ready');
    $this->assertText('CustomType error null null');
    $this->assertNoText('CustomType current current ready');
    $this->assertText('CustomType nothing nothing nothing');
    $this->assertText('NotConfigured nothing nothing nothing');
    $this->assertNoText('Article cancelled cancelled cancelled');

    $edit = [
      'filters[advanced_options][source_status]' => 'UPLOAD_NEEDED',
      'filters[wrapper][bundle][]' => ['custom_type', 'not_configured'],
      'filters[advanced_options][source_language]' => 'en',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    $this->assertText('CustomType edited ready ready');
    $this->assertNoText('Article current error current');
    $this->assertNoText('Article importing null null');
    $this->assertNoText('Article null null null');
    $this->assertText('CustomType edited current edited');
    $this->assertNoText('CustomType edited edited current');
    $this->assertNoText('Article error edited ready');
    $this->assertNoText('Article current interim ready');
    $this->assertText('CustomType error null null');
    $this->assertNoText('CustomType current current ready');
    $this->assertText('CustomType nothing nothing nothing');
    $this->assertText('NotConfigured nothing nothing nothing');
    $this->assertNoText('Article cancelled cancelled cancelled');

    $edit = [
      'filters[advanced_options][source_status]' => 'UPLOAD_NEEDED',
      'filters[wrapper][bundle][]' => ['custom_type', 'not_configured'],
      'filters[advanced_options][source_language]' => 'en',
      'filters[advanced_options][profile][]' => ['manual', 'automatic'],
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    $this->assertText('CustomType edited ready ready');
    $this->assertNoText('Article current error current');
    $this->assertNoText('Article importing null null');
    $this->assertNoText('Article null null null');
    $this->assertText('CustomType edited current edited');
    $this->assertNoText('CustomType edited edited current');
    $this->assertNoText('Article error edited ready');
    $this->assertNoText('Article current interim ready');
    $this->assertText('CustomType error null null');
    $this->assertNoText('CustomType current current ready');
    $this->assertNoText('CustomType nothing nothing nothing');
    $this->assertNoText('NotConfigured nothing nothing nothing');
    $this->assertNoText('Article cancelled cancelled cancelled');

    $edit = [
      'filters[advanced_options][source_status]' => 'UPLOAD_NEEDED',
      'filters[wrapper][bundle][]' => ['custom_type', 'not_configured'],
      'filters[advanced_options][source_language]' => 'en',
      'filters[advanced_options][profile][]' => ['manual', 'automatic'],
      'filters[advanced_options][target_status]' => 'READY',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');

    $this->assertText('CustomType edited ready ready');
    $this->assertNoText('Article current error current');
    $this->assertNoText('Article importing null null');
    $this->assertNoText('Article null null null');
    $this->assertNoText('CustomType edited current edited');
    $this->assertNoText('CustomType edited edited current');
    $this->assertNoText('Article error edited ready');
    $this->assertNoText('Article current interim ready');
    $this->assertNoText('CustomType error null null');
    $this->assertNoText('CustomType current current ready');
    $this->assertNoText('CustomType nothing nothing nothing');
    $this->assertNoText('NotConfigured nothing nothing nothing');
    $this->assertNoText('Article cancelled cancelled cancelled');
  }

  public function testTargetStatusFilterPagination() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article', 'custom_type'], 'manual');
    $node_defaults = [
      'type' => 'article',
      'langcode' => 'en',
    ];
    /** @var \Drupal\node\Entity\Node[] $nodes */
    $nodes = [
      Node::create(['title' => 'Article 1'] + $node_defaults),
      Node::create(['title' => 'Article 2'] + $node_defaults),
      Node::create(['title' => 'Article 3'] + $node_defaults),
      Node::create(['title' => 'Article 4'] + $node_defaults),
      Node::create(['title' => 'Article 5'] + $node_defaults),
      Node::create(['title' => 'Article 6'] + $node_defaults),
      Node::create(['title' => 'Article 7'] + $node_defaults),
      Node::create(['title' => 'Article 8'] + $node_defaults),
      Node::create(['title' => 'Article 9'] + $node_defaults),
      Node::create(['title' => 'Article 10'] + $node_defaults),
      Node::create(['title' => 'Article 11'] + $node_defaults),
    ];

    foreach ($nodes as $node) {
      $node->save();
    }

    $metadata_data = [
      'profile' => 'automatic',
      'translation_source' => 'en',
      'translation_status' => [
        ['language' => 'en', 'value' => 'current'],
        ['language' => 'de', 'value' => 'cancelled'],
        ['language' => 'es', 'value' => 'cancelled'],
      ],
    ];

    $index = 0;
    while ($index < 11) {
      ++$index;
      $metadata = LingotekContentMetadata::loadByTargetId('node', $index);
      $metadata->setDocumentId('document_id_' . $index);
      $metadata->set('translation_status', $metadata_data['translation_status']);
      $metadata->set('profile', $metadata_data['profile']);
      $metadata->set('translation_source', $metadata_data['translation_source']);
      $metadata->save();
    }

    $this->goToContentBulkManagementForm();
    $edit = [
      'filters[advanced_options][target_status]' => Lingotek::STATUS_CANCELLED,
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    // This ensures that pagination is working correctly with the Target Status filter.
    // If it isn't, there will be fewer than 10 nodes on the content bulk management form
    $this->assertText('Article 10');
  }

}
