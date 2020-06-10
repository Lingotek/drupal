<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek config translation document upload hook.
 *
 * @group lingotek
 */
class LingotekConfigTranslationDocumentUploadHookTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block'];

  protected function setUp(): void {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $this->saveLingotekConfigTranslationSettings([
      'block' => 'automatic',
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'block.powered-by');
  }

  /**
   * Tests that a block can be translated.
   */
  public function testBlockTranslation() {
    // Place the block with title that contains a token.
    $this->drupalPlaceBlock('system_powered_by_block', [
      'label' => t('Title with [site:name]'),
    ]);

    // Check that [token] is encoded via hook_lingotek_config_entity_document_upload().
    // @see lingotek_test_lingotek_config_entity_document_upload()
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual($data['settings.label'], 'Title with [***SITE:NAME***]');
  }

}
