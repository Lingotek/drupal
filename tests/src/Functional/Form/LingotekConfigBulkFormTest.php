<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the config bulk management form.
 *
 * @group lingotek
 */
class LingotekConfigBulkFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->drupalGet('admin/lingotek/settings');
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[page][enabled]' => 1,
      'node[page][profiles]' => 'automatic',
      'node[page][fields][title]' => 1,
      'node[page][fields][body]' => 1,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'automatic',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-configuration-form');
  }

  /**
   * Tests that the config filtering works correctly.
   */
  public function testConfigFilter() {
    $this->goToConfigBulkManagementForm();

    // Assert that there is a "Bundle" header on the second position.
    // First position is the checkbox, that's why we care about the second.
    $second_header = $this->xpath('//*[@id="edit-table"]/thead/tr/th[2]')[0];
    $this->assertEqual($second_header->getHtml(), 'Entity', 'There is a Entity header.');
  }

  /**
   * Tests that the field config filtering works correctly.
   */
  public function testFieldConfigFilter() {
    $this->goToConfigBulkManagementForm();

    // Let's filter by node fields.
    $edit = ['filters[wrapper][bundle]' => 'node_fields'];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    // Assert that there is a "Bundle" header on the second position.
    // First position is the checkbox, that's why we care about the second.
    $second_header = $this->xpath('//*[@id="edit-table"]/thead/tr/th[2]')[0];
    $this->assertEqual($second_header->getHtml(), 'Bundle', 'There is a Bundle header.');

    $third_header = $this->xpath('//*[@id="edit-table"]/thead/tr/th[3]')[0];
    $this->assertEqual($third_header->getHtml(), 'Entity', 'There is a Entity header.');

    // Assert that there is a bundle printed with the Body field, and by that
    // Body must be appear twice.
    $this->assertUniqueText('Article');
    $this->assertUniqueText('Page');
    $this->assertText('Body');
    $this->assertNoUniqueText('Body');
  }

  /**
   * Tests that the config bulk form doesn't show a language if it's disabled.
   */
  public function testDisabledLanguage() {
    // Go and upload a field.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('EN');

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Then we disable the Spanish language.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfig */
    $lingotekConfig = \Drupal::service('lingotek.configuration');
    $language = ConfigurableLanguage::load('es');
    $lingotekConfig->disableLanguage($language);

    // And we check that Spanish is not there anymore.
    $this->goToConfigBulkManagementForm();
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    // We re-enable Spanish.
    $lingotekConfig->enableLanguage($language);

    // And Spanish should be back in the management form.
    $this->goToConfigBulkManagementForm();
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
  }

  /**
   * Tests job id is uploaded on upload.
   */
  public function testJobIdOnUpload() {
    // Go and upload a field.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/node_type/page?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      'table[page]' => TRUE,
      'job_id' => 'my_custom_job_id',
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertEquals('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata[] $metadatas */
    $metadatas = LingotekConfigMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEquals('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }
  }

  /**
   * Tests job id is uploaded on update.
   */
  public function testJobIdOnUpdate() {
    // Create a node type with automatic. This will trigger upload.
    $this->drupalCreateContentType(['type' => 'banner', 'name' => 'Banner']);
    $this->drupalCreateContentType(['type' => 'book', 'name' => 'Book']);
    $this->drupalCreateContentType(['type' => 'ingredient', 'name' => 'Ingredient']);
    $this->drupalCreateContentType(['type' => 'recipe', 'name' => 'Recipe']);

    $this->goToConfigBulkManagementForm('node_type');

    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata[] $metadatas */
    $metadatas = LingotekConfigMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertNull($metadata->getJobId(), 'There was no job id to save along with metadata.');
    }

    $basepath = \Drupal::request()->getBasePath();

    // I can check the status of the upload. So next operation will perform an
    // update.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/book?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/recipe?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[ingredient]' => TRUE,
      'table[recipe]' => TRUE,
      'table[book]' => TRUE,
      'table[banner]' => TRUE,
      'job_id' => 'my_custom_job_id',
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertEquals('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata[] $metadatas */
    $metadatas = LingotekConfigMetadata::loadMultiple();
    foreach ($metadatas as $metadata) {
      $this->assertEquals('my_custom_job_id', $metadata->getJobId(), 'The job id was saved along with metadata.');
    }
  }

  /**
   * Tests that the bulk management filtering works correctly.
   */
  public function testJobIdFilter() {
    \Drupal::configFactory()->getEditable('lingotek.settings')->set('translate.config.node_type.profile', 'manual')->save();

    $basepath = \Drupal::request()->getBasePath();

    $node_types = [];
    // See https://www.drupal.org/project/drupal/issues/2925290.
    $indexes = "ABCDEFGHIJKLMNOPQ";
    // Create some nodes.
    for ($i = 1; $i < 10; $i++) {
      $node_types[$i] = $this->drupalCreateContentType(['type' => 'content_type_' . $i, 'name' => 'Content Type ' . $indexes[$i]]);
    }

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertNoText('No content available');

    // After we filter by an unexisting job, there is no content and no rows.
    $edit = [
      'filters[wrapper][job]' => 'this job does not exist',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    $this->assertText('No content available');

    // After we reset, we get back to having all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    $this->goToConfigBulkManagementForm('node_type');
    foreach (range(1, 9) as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[content_type_2]' => TRUE,
      'table[content_type_4]' => TRUE,
      'table[content_type_6]' => TRUE,
      'table[content_type_8]' => TRUE,
      'operation' => 'upload',
      'job_id' => 'even numbers',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[content_type_1]' => TRUE,
      'table[content_type_2]' => TRUE,
      'table[content_type_3]' => TRUE,
      'table[content_type_5]' => TRUE,
      'table[content_type_7]' => TRUE,
      'operation' => 'upload',
      'job_id' => 'prime numbers',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // After we filter by prime, there is no pager and the rows
    // selected are the ones expected.
    $edit = [
      'filters[wrapper][job]' => 'prime',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 2, 3, 5, 7] as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    $this->assertNoText('Content Type ' . $indexes[4]);
    $this->assertNoText('Content Type ' . $indexes[6]);

    // After we filter by even, there is no pager and the rows selected are the
    // ones expected.
    $edit = [
      'filters[wrapper][job]' => 'even',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([4, 6, 8] as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
    $this->assertNoText('Content Type ' . $indexes[5]);

    // After we reset, we get back to having all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    $this->goToConfigBulkManagementForm('node_type');
    foreach (range(1, 9) as $j) {
      $this->assertText('Content Type ' . $indexes[$j]);
    }
  }

}
