<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\contact\Entity\ContactForm;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a field in a contact message.
 *
 * @group lingotek
 * @group legacy
 * TODO: Remove legacy group when 8.8.x is not supported.
 * @see https://www.drupal.org/project/lingotek/issues/3153400
 */
class LingotekContactMessageBodyTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'field_ui', 'contact'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    ContactForm::create([
      'id' => 'contact_message',
      'label' => 'Test contact form',
    ])->save();

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'contact_message',
      'type' => 'text',
    ]);
    $fieldStorage->save();

    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'contact_message',
    ])->save();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'contact_message_field');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testFieldTranslation() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/contact_message_fields');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertText(t('field_test uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertTrue(array_key_exists('label', $data['field.field.contact_message.contact_message.field_test']));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data['field.field.contact_message.contact_message.field_test']));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->clickLink(t('Check upload status'));
    $this->assertText('field_test status checked successfully');

    $this->clickLink(t('Request translation'));
    $this->assertText(t('Translation to es_MX requested successfully'));
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    $this->clickLink(t('Check Download'));
    $this->assertText(t('Translation to es_MX status checked successfully'));

    $this->clickLink('Download');
    $this->assertText(t('Translation to es_MX downloaded successfully'));

    // Check that the edit link is there.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/structure/contact/manage/contact_message/fields/contact_message.contact_message.field_test/translate/es/edit');
  }

}
