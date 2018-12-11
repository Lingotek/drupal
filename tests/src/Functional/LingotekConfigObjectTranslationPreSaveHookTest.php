<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek config object translation pre save hook.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekConfigObjectTranslationPreSaveHookTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['views', 'node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'rss-publishing');

    // Create a node.
    $this->drupalCreateContentType(['type' => 'article', 'name' => t('Article')]);
    $this->drupalCreateNode(['type' => 'article', 'promote' => 1]);
  }

  /**
   * Tests that rss publishing settings can be translated.
   */
  public function testRssPublishingTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('admin/config/services/rss-publishing');
    $edit = [
      'feed_description' => 'Llamas feed description',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $this->goToConfigBulkManagementForm();

    $this->clickLink('EN', 2);

    // Check that Llamas is replaced via hook_lingotek_config_object_document_upload().
    // @see lingotek_test_lingotek_config_object_document_upload()
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertEqual($data['system.rss']['channel.description'], 'Cats feed description');

    // Translate the config using Lingotek.
    $this->clickLink('ES');
    $this->clickLink('ES');
    $this->clickLink('ES');

    // Check that Gatos is replaced via hook_lingotek_config_object_translation_presave().
    // @see lingotek_test_lingotek_config_object_translation_presave()
    $this->drupalGet("admin/config/services/rss-publishing/translate/es/edit");
    $this->assertFieldByName("translation[config_names][system.rss][channel][description]", 'Perros alimentados descripción');

    // ToDo: See core issue https://www.drupal.org/project/drupal/issues/3019793.
    // $this->drupalGet('es/rss.xml');
    // $this->assertRaw('Perros alimentados descripción');
  }

}
