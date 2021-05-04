<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek config object translation pre save hook.
 *
 * @group lingotek
 */
class LingotekConfigObjectTranslationPreSaveHookTest extends LingotekTestBase {

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
   * Tests that the hook works as expected.
   */
  public function testConfigObjectTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

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

    // Translate the config using Lingotek.
    $this->clickLink('ES');
    $this->clickLink('ES');
    $this->clickLink('ES');

    // Check that Gatos is replaced via hook_lingotek_config_object_translation_presave().
    // @see lingotek_test_lingotek_config_object_translation_presave()
    $this->drupalGet("/admin/config/lingotek/lingotek_test_config_object/translate/es/edit");
    $assert_session->fieldNotExists("translation[config_names][lingotek_test_config_object.settings][property_1]");
    $assert_session->fieldNotExists("translation[config_names][lingotek_test_config_object.settings][property_2]");
    $assert_session->fieldValueEquals("translation[config_names][lingotek_test_config_object.settings][property_3]", 'Perros alimentados descripción');
    $assert_session->fieldValueEquals("translation[config_names][lingotek_test_config_object.settings][property_4]", 'Gatos alimentados descripción');
    $assert_session->fieldValueEquals("translation[config_names][lingotek_test_config_object.settings][property_5]", 'Perros alimentados descripción');
    $assert_session->fieldValueEquals("translation[config_names][lingotek_test_config_object.settings][property_6]", 'Gatos alimentados descripción');
  }

}
