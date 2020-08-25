<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Tests the append type to document title option.
 *
 * @group lingotek
 */
class LingotekNodeTranslationAppendTypeTitleOptionTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'manual',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Sets the setting for appending type title through the UI.
   *
   * @param bool $value
   *   TRUE if we should append type title. FALSE if not.
   */
  protected function setSettingsAppendTypeTitle($value) {
    $this->drupalGet('/admin/lingotek/settings');
    $edit = [
      'append_type_to_title' => $value,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], 'lingoteksettings-tab-preferences-form');
  }

  /**
   * Test to append type title by using the settings.
   */
  public function testSettingsAppendTypeTitle() {
    $this->setSettingsAppendTypeTitle(TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Check that the title used was the right one.
    $uploaded_title = \Drupal::state()->get('lingotek.uploaded_title');
    $this->assertIdentical('article (node): Llamas are cool', $uploaded_title, 'The node title was used appending type.');
  }

  /**
   * Test to not append type title by using the settings.
   */
  public function testSettingsNoAppendTypeTitle() {
    $this->setSettingsAppendTypeTitle(FALSE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Check that the title used was the right one.
    $uploaded_title = \Drupal::state()->get('lingotek.uploaded_title');
    $this->assertIdentical('Llamas are cool', $uploaded_title, 'The node title was used without appending type.');
  }

  /**
   * Test to append type title by using the profile options.
   */
  public function testProfileAppendTypeTitle() {
    $this->setSettingsAppendTypeTitle(FALSE);

    $profile = LingotekProfile::create([
      'id' => 'custom',
      'label' => 'Profile with overrides',
      'auto_upload' => FALSE,
      'auto_download' => FALSE,
      'append_type_to_title' => 'yes',
    ]);
    $profile->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'custom';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Check that the title used was the right one.
    $uploaded_title = \Drupal::state()->get('lingotek.uploaded_title');
    $this->assertIdentical('article (node): Llamas are cool', $uploaded_title, 'The node title was used appending type.');
  }

  /**
   * Test to not append type title by using the profile options.
   */
  public function testProfileNoAppendTypeTitle() {
    $this->setSettingsAppendTypeTitle(TRUE);

    $profile = LingotekProfile::create([
      'id' => 'custom',
      'label' => 'Profile with overrides',
      'auto_upload' => FALSE,
      'auto_download' => FALSE,
      'append_type_to_title' => 'no',
    ]);
    $profile->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'custom';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Check that the title used was the right one.
    $uploaded_title = \Drupal::state()->get('lingotek.uploaded_title');
    $this->assertIdentical('Llamas are cool', $uploaded_title, 'The node title was used without appending type.');
  }

  /**
   * Test to append type title by using the profile options defaulting to settings.
   */
  public function testProfileUseGlobalSettingsAppendTypeTitle() {
    $this->setSettingsAppendTypeTitle(TRUE);

    $profile = LingotekProfile::create([
      'id' => 'custom',
      'label' => 'Profile with overrides',
      'auto_upload' => FALSE,
      'auto_download' => FALSE,
      'append_type_to_title' => 'global_setting',
    ]);
    $profile->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'custom';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Check that the title used was the right one.
    $uploaded_title = \Drupal::state()->get('lingotek.uploaded_title');
    $this->assertIdentical('article (node): Llamas are cool', $uploaded_title, 'The node title was used appending type.');
  }

  /**
   * Test to not append type title by using the profile options defaulting to settings.
   */
  public function testProfileUseGlobalSettingsNoAppendTypeTitle() {
    $this->setSettingsAppendTypeTitle(FALSE);

    $profile = LingotekProfile::create([
      'id' => 'custom',
      'label' => 'Profile with overrides',
      'auto_upload' => FALSE,
      'auto_download' => FALSE,
      'append_type_to_title' => 'global_setting',
    ]);
    $profile->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'custom';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Check that the title used was the right one.
    $uploaded_title = \Drupal::state()->get('lingotek.uploaded_title');
    $this->assertIdentical('Llamas are cool', $uploaded_title, 'The node title was used without appending type.');
  }

}
