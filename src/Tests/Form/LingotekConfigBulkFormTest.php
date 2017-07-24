<?php

namespace Drupal\lingotek\Tests\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Tests\LingotekTestBase;

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
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

  }

  /**
   * Tests that the config filtering works correctly.
   */
  public function testConfigFilter() {
    $this->goToConfigBulkManagementForm();

    // Assert that there is a "Bundle" header on the second position.
    // First position is the checkbox, that's why we care about the second.
    $second_header = (string)$this->xpath('//*[@id="edit-table"]/thead/tr/th[2]')[0];
    $this->assertEqual($second_header, 'Entity', 'There is a Entity header.');
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
    $second_header = (string)$this->xpath('//*[@id="edit-table"]/thead/tr/th[2]')[0];
    $this->assertEqual($second_header, 'Bundle', 'There is a Bundle header.');

    $third_header = (string)$this->xpath('//*[@id="edit-table"]/thead/tr/th[3]')[0];
    $this->assertEqual($third_header, 'Entity', 'There is a Entity header.');

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

}
