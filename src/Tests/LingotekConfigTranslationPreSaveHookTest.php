<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek config translation pre save hook.
 *
 * @group lingotek
 */
class LingotekConfigTranslationPreSaveHookTest extends LingotekTestBase {

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
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Place the block with title that contains a token.
    $block = $this->drupalPlaceBlock('system_powered_by_block', array(
      'label' => t('Title with [site:name]'),
    ));
    $block_id = $block->id();

    // Check that [token] is encoded via hook_lingotek_config_entity_document_upload().
    // @see lingotek_test_lingotek_config_entity_document_upload()
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertEqual($data['settings.label'], 'Title with [***c2l0ZTpuYW1l***]');

    // Translate the block using the Lingotek translate config admin form.
    $this->drupalGet("admin/lingotek/config/upload/block/$block_id");
    $this->drupalGet("admin/lingotek/config/request/block/$block_id/es_ES");
    $this->drupalGet("admin/lingotek/config/check_download/block/$block_id/es_ES");
    $this->drupalGet("admin/lingotek/config/download/block/$block_id/es_ES");

    // Check that [token] is decoded via hook_lingotek_config_entity_translation_presave().
    // @see lingotek_test_lingotek_config_entity_translation_presave()
    $this->drupalGet("admin/structure/block/manage/$block_id/translate/es/edit");
    $this->assertFieldByName("translation[config_names][block.block.$block_id][settings][label]", 'Title with [site:name]');
  }

}
