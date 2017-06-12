<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a field.
 *
 * @group lingotek
 */
class LingotekFieldBodyTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'field_ui'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $type = $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testFieldTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertText(t('Body uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertTrue(array_key_exists('label', $data['field.field.node.article.body']));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data['field.field.node.article.body']));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->clickLink(t('Check upload status'));
    $this->assertText('Body status checked successfully');

    $this->clickLink(t('Request translation'));
    $this->assertText(t('Translation to es_MX requested successfully'));
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    $this->clickLink(t('Check Download'));
    $this->assertText(t('Translation to es_MX status checked successfully'));

    $this->clickLink('Download');
    $this->assertText(t('Translation to es_MX downloaded successfully'));

    // Check that the edit link is there.
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath. '/admin/structure/types/manage/article/fields/node.article.body/translate/es/edit');

    // Check that the values are correct.
    $this->clickLink('Edit', 1);
    $this->assertFieldByName('translation[config_names][field.field.node.article.body][label]', 'Cuerpo');
    $this->assertFieldByName('translation[config_names][field.field.node.article.body][description]', 'Cuerpo del contenido');
  }

  /**
   * Tests that a config can be translated after edited.
   */
  public function testEditedFieldBodyTranslation() {
    // We need a config with translations first.
    $this->testFieldTranslation();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the field.
    $edit = ['label' => 'Contents'];
    $this->drupalPostForm('/admin/structure/types/manage/article/fields/node.article.body', $edit, t('Save settings'));
    $this->assertText('Saved Contents configuration.');

    $this->clickLink(t('Translate'));

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $this->assertLinkByHref('/admin/lingotek/config/request/node_fields/node.article.body/eu_ES');

    $this->clickLink(t('Request translation'), 1);
    $this->assertText(t('Translation to es_MX requested successfully'));
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Recheck status.
    $this->clickLink('Check Download');
    $this->assertText('Translation to es_MX status checked successfully');

    // Download the translation.
    $this->clickLink('Download');
    $this->assertText('Translation to es_MX downloaded successfully');
  }


  /**
   * Tests that no translation can be requested if the language is disabled.
   */
  public function testLanguageDisabled() {
    // Add a language.
    $italian = ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT');
    $italian->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertText(t('Body uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertEqual(2, count($data['field.field.node.article.body']));
    $this->assertTrue(array_key_exists('label', $data['field.field.node.article.body']));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data['field.field.node.article.body']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check upload status');
    $this->assertText('Body status checked successfully');

    // There are two links for requesting translations, or we can add them
    // manually.
    $this->assertLinkByHref('/admin/lingotek/config/request/node_fields/node.article.body/it_IT');
    $this->assertLinkByHref('/admin/lingotek/config/request/node_fields/node.article.body/es_MX');
    $this->assertLinkByHref('/admin/structure/types/manage/article/fields/node.article.body/translate/it/add');
    $this->assertLinkByHref('/admin/structure/types/manage/article/fields/node.article.body/translate/es/add');

    /** @var LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Check that the translate tab is in the node.
    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'));

    // Italian is not present anymore, but still can add a translation.
    $this->assertNoLinkByHref('/admin/lingotek/config/request/node_fields/node.article.body/it_IT');
    $this->assertLinkByHref('/admin/lingotek/config/request/node_fields/node.article.body/es_MX');
    $this->assertLinkByHref('/admin/structure/types/manage/article/fields/node.article.body/translate/it/add');
    $this->assertLinkByHref('/admin/structure/types/manage/article/fields/node.article.body/translate/es/add');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Check that the translate tab is in the field.
    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'));

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->assertText('Body upload failed. Please try again.');

    // The field has been marked with the error status.
    $fieldConfig = FieldConfig::load('node.article.body');
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($fieldConfig);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The field has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->assertText('Body uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Check that the translate tab is in the field.
    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->assertText('Body uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertText('Body status checked successfully');

    // Edit the field.
    $edit = ['label' => 'Contents'];
    $this->drupalPostForm('/admin/structure/types/manage/article/fields/node.article.body', $edit, t('Save settings'));
    $this->assertText('Saved Contents configuration.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->assertText('Contents update failed. Please try again.');

    // The field has been marked with the error status.
    $fieldConfig = FieldConfig::load('node.article.body');
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($fieldConfig);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The field has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->assertText('Contents has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorViaAPI() {
    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->drupalGet('admin/lingotek/settings');

    // Create a field.
    $edit  = ['label' => 'Excerpt', 'new_storage_type' => 'text', 'field_name' => 'excerpt'];
    $this->drupalPostForm('/admin/structure/types/manage/article/fields/add-field', $edit, 'Save and continue');

    // The document was uploaded automatically and failed.
    $this->assertText('The upload for field_config Excerpt failed. Please try again.');

    // The field has been marked with the error status.
    $fieldConfig = FieldConfig::load('node.article.field_excerpt');
    /** @var LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($fieldConfig);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The field has been marked as error.');
  }

}
