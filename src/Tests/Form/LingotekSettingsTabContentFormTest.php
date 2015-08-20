<?php

namespace Drupal\lingotek\Tests\Form;

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
      $this->drupalCreateContentType(array(
        'type' => 'article',
        'name' => 'Article'
      ));

      $this->createImageField('field_image', 'article');
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

    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    $this->rebuildContainer();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertNoText('There are no translatable content types specified');
    $this->assertNoField('node[article][fields][langcode]');
    $this->assertField('node[article][enabled]');
    $this->assertField('node[article][profiles]');
    $this->assertField('node[article][fields][title]');
    $this->assertField('node[article][fields][revision_log]');
    $this->assertField('node[article][fields][body]');

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

    // Check that values are kept in the form.
    $this->assertFieldChecked('edit-node-article-enabled');
    $this->assertFieldByName('node[article][profiles]', 'automatic');
    $this->assertFieldChecked('edit-node-article-fields-title');
    $this->assertFieldChecked('edit-node-article-fields-body');
    $this->assertFieldChecked('edit-node-article-fields-field-image');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-title');
    $this->assertNoFieldChecked('edit-node-article-fields-revision-log');

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


}