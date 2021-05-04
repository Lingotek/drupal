<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek config translation document upload hook.
 *
 * @group lingotek
 */
class LingotekConfigObjectTranslationDocumentUploadHookTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'user', 'lingotek_test_config_object'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'lingotek_test_config_object');
  }

  /**
   * Tests that account config object settings can be translated.
   */
  public function testConfigObjectTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/lingotek/lingotek_test_config_object');
    $edit = [
      'property_1' => 'Llamas feed description',
      'property_2' => 'Llamas feed description',
      'property_3' => 'Llamas feed description',
      'property_4' => 'Llamas feed description',
      'property_5' => 'Llamas feed description',
      'property_6' => 'Llamas feed description',
    ];
    $this->submitForm($edit, 'Save configuration');

    $this->goToConfigBulkManagementForm();

    // In Drupal 9.2 the order of the elements changed, so we need to find it.
    $label = "Lingotek Test Config Object";
    $enLink = $this->xpath("//td[contains(text(), :label)]/following-sibling::td//a", [':label' => $label]);
    $enLink[0]->click();

    // Check that Llamas is replaced via hook_lingotek_config_object_document_upload().
    // @see lingotek_test_lingotek_config_object_document_upload()

    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    // Only the proper translatable typed properties are being uploaded.
    $this->assertFalse(isset($data['lingotek_test_config_object.settings']['property_1']));
    $this->assertFalse(isset($data['lingotek_test_config_object.settings']['property_2']));
    $this->assertEqual($data['lingotek_test_config_object.settings']['property_3'], 'Cats feed description');
    $this->assertEqual($data['lingotek_test_config_object.settings']['property_4'], 'Llamas feed description');
    $this->assertEqual($data['lingotek_test_config_object.settings']['property_5'], 'Cats feed description');
    $this->assertEqual($data['lingotek_test_config_object.settings']['property_6'], 'Llamas feed description');
  }

}
