<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek config translation document upload hook.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekConfigObjectTranslationDocumentUploadHookTest extends LingotekTestBase {

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
  }

}
