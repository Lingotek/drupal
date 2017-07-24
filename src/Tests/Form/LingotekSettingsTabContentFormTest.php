<?php

namespace Drupal\lingotek\Tests\Form;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Tests\LingotekTestBase;

/**
 * Tests the Lingotek content settings form.
 *
 * @group lingotek
 */
class LingotekSettingsTabContentFormTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'image'];

  protected function setUp() {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article'
      ]);

      $this->createImageField('field_image', 'article');
      $this->createImageField('user_picture', 'user', 'user');
    }

  }

  /**
   * Test that if there are no entities, there is a proper feedback to the user.
   */
  public function testNoUntranslatableEntitiesAreShown() {
    $this->drupalGet('admin/lingotek/settings');
    $this->assertText('There are no translatable content entities specified');
  }

  /**
   * Test that we can configure entities at the subfield level.
   */
  public function testConfigureTranslatableEntityWithFieldsAndSubfields() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('user', 'user')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('user', 'user', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertNoText('There are no translatable content types specified');
    $this->assertNoField('node[article][fields][langcode]');
    $this->assertField('node[article][enabled]');
    $this->assertField('node[article][profiles]');
    $this->assertField('node[article][fields][title]');
    $this->assertField('node[article][fields][body]');

    // Check the title and body fields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
      'user[user][enabled]' => 1,
      'user[user][fields][user_picture]' => 1,
      'user[user][fields][user_picture:properties][alt]' => 'alt',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Check that values are kept in the form.
    $this->assertFieldChecked('edit-node-article-enabled');
    $this->assertFieldByName('node[article][profiles]', 'automatic');
    $this->assertFieldChecked('edit-node-article-fields-title');
    $this->assertFieldChecked('edit-node-article-fields-body');
    $this->assertFieldChecked('edit-node-article-fields-field-image');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-title');

    // Check that the config is correctly saved.
    $config_data = $this->config('lingotek.settings')->getRawData();
    $this->assertTrue($config_data['translate']['entity']['node']['article']['enabled']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['title']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['body']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['field_image']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['alt']);
    $this->assertFalse($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['title']);
    $this->assertFalse(array_key_exists('revision_log', $config_data['translate']['entity']['node']['article']['field']));
    $this->assertEqual('automatic', $config_data['translate']['entity']['node']['article']['profile']);
  }

  public function testICanDisableFields() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/lingotek/settings');
    // Check the title and body fields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');
    // Assert that body translation is enabled.
    $this->assertFieldChecked('edit-node-article-fields-body');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');

    // Submit again unchecking body and image including subfields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => FALSE,
      'node[article][fields][field_image]' => FALSE,
      'node[article][fields][field_image:properties][alt]' => FALSE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Those checkboxes should not be checked anymore.
    $this->assertNoFieldChecked('edit-node-article-fields-body');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-alt');

  }

  /**
   * Test that if we disable content translation for an entity or an entity
   * field, they are disabled for Lingotek.
   *
   * @throws \Exception
   */
  public function testFieldsAreDisabledInLingotekIfFieldsAreMarkedAsNotTranslatable() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/lingotek/settings');
    // Check the title and body fields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');
    // Assert that body translation is enabled.
    $this->assertFieldChecked('edit-node-article-fields-title', 'The title field is enabled after enabled for Lingotek translation');
    $this->assertFieldChecked('edit-node-article-fields-body', 'The body field is enabled after enabled for Lingotek translation');
    $this->assertFieldChecked('edit-node-article-fields-field-image', 'The image field is enabled after enabled for Lingotek translation');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt', 'The image alt property is enabled after enabled for Lingotek translation');

    // Go to the content language settings, and disable the body field.
    // It should result that the field is disabled in Lingotek too.
    $edit = [
      'entity_types[node]' => TRUE,
      'settings[node][article][settings][language][language_alterable]' => TRUE,
      'settings[node][article][translatable]' => TRUE,
      'settings[node][article][fields][title]' => TRUE,
      'settings[node][article][fields][body]' => FALSE,
      'settings[node][article][fields][field_image]' => FALSE,
    ];
    $this->drupalPostForm('admin/config/regional/content-language', $edit, t('Save configuration'));

    // Get the form and check the fields are not available, because they cannot be translated.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertFieldChecked('edit-node-article-fields-title', 'The title field is enabled after other fields were disabled for content translation');
    $this->assertNoFieldById('edit-node-article-fields-body', 'The body field is not present after disabled for content translation');
    $this->assertNoFieldById('edit-node-article-fields-field-image', 'The image field is not present after disabled for content translation');
    $this->assertNoFieldById('edit-node-article-fields-field-imageproperties-alt', 'The image alt property is not present after image was disabled for content translation');

    // But also check that the fields are not enabled.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $this->assertFalse($lingotek_config->isFieldLingotekEnabled('node', 'article', 'body'), 'The body field is disabled after being disabled for content translation');
    $this->assertFalse($lingotek_config->isFieldLingotekEnabled('node', 'article', 'image'), 'The image field is disabled after being disabled for content translation');

    // And if we disable the entity itself, it should not be enabled anymore.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', FALSE);
    $this->assertFalse($lingotek_config->isEnabled('node', 'article'), 'The article entity is disabled after being disabled for content translation');
    $this->assertFalse($lingotek_config->isFieldLingotekEnabled('node', 'article', 'title'), 'The title field is disabled after the entity being disabled for content translation');
  }

  public function testFieldsAreNotAvailableIfTranslatableEvenIfStorageIsTranslatable() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    // Ensure field storage is translatable.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_image');
    $field_storage->setTranslatable(TRUE)->save();

    // Ensure field instance is not translatable.
    $field = FieldConfig::loadByName('node', 'article', 'field_image');
    $field->setTranslatable(FALSE)->save();

    // Ensure changes were saved correctly.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_image');
    $field = FieldConfig::loadByName('node', 'article', 'field_image');
    $this->assertTrue($field_storage->isTranslatable(), 'Field storage is translatable.');
    $this->assertFalse($field->isTranslatable(), 'Field instance is not translatable.');

    // Get the form and check the field is not available, even if the storage
    // is translatable.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertNoFieldById('edit-node-article-fields-field-image', '', 'The image field is not present after marked as not translatable.');

    // Make the field translatable again.
    $field->setTranslatable(TRUE)->save();

    // If the field is translatable, the field is available again.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertFieldById('edit-node-article-fields-field-image', '', 'The image field is present after marked as translatable.');
  }

}
