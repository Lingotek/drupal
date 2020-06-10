<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek config translation pre save hook.
 *
 * @group lingotek
 */
class LingotekConfigTranslationPreSaveHookTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
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
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Place the block with title that contains a token.
    $block = $this->drupalPlaceBlock('system_powered_by_block', [
      'id' => 'powered_by_block',
      'label' => t('Title with [site:name]'),
      'label_display' => TRUE,
    ]);
    $block_id = $block->id();

    // Check that [token] is encoded via hook_lingotek_config_entity_document_upload().
    // @see lingotek_test_lingotek_config_entity_document_upload()
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertEqual($data['settings.label'], 'Title with [***SITE:NAME***]');

    // Translate the block using the Lingotek translate config admin form.
    $this->drupalGet("admin/structure/block/manage/$block_id/translate");

    $this->clickLink('Upload');
    $this->clickLink('Request translation');
    $this->clickLink('Check Download');
    $this->clickLink('Download');

    // Check that [token] is decoded via hook_lingotek_config_entity_translation_presave().
    // @see lingotek_test_lingotek_config_entity_translation_presave()
    $this->drupalGet("admin/structure/block/manage/$block_id/translate/es/edit");
    $this->assertFieldByName("translation[config_names][block.block.$block_id][settings][label]", 'Título con [site:name]');

    $this->drupalGet('es/user');
    $this->assertText('Título con [site:name]');
  }

}
