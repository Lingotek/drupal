<?php

namespace Drupal\lingotek\Tests;

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

  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $edit = [
      'table[block][enabled]' => 1,
      'table[block][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');
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
    $this->assertEqual($data['settings.label'], 'Title with [***c2l0ZTpuYW1l***]');
  }

}