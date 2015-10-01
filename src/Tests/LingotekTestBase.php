<?php

namespace Drupal\lingotek\Tests;

use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;

/**
 * Base class for Lingotek test. Performs authorization of the account.
 */
abstract class LingotekTestBase extends WebTestBase {

  /*
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['lingotek', 'lingotek_test'];

  protected function setUp() {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
    $this->connectToLingotek();
  }

  /**
   * Create a new image field.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $type_name
   *   The node type that this field will be added to.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  function createImageField($name, $type_name, $storage_settings = array(), $field_settings = array(), $widget_settings = array()) {
    entity_create('field_storage_config', array(
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'image',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ))->save();

    $field_config = entity_create('field_config', array(
      'field_name' => $name,
      'label' => $name,
      'entity_type' => 'node',
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ));
    $field_config->save();

    entity_get_form_display('node', $type_name, 'default')
      ->setComponent($name, array(
        'type' => 'image_image',
        'settings' => $widget_settings,
      ))
      ->save();

    entity_get_display('node', $type_name, 'default')
      ->setComponent($name)
      ->save();

    return $field_config;

  }

  protected function connectToLingotek() {
    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect to Lingotek');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault'
    ], 'Save configuration');
  }


}