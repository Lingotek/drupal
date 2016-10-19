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
   *   The name of the new field (all lowercase).
   * @param string $type_name
   *   The bundle that this field will be added to.
   * @param string $entity_type_id
   *   The entity type that this field will be added to. Defaults to 'node'
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  function createImageField($name, $type_name, $entity_type_id = 'node', $storage_settings = array(), $field_settings = array(), $widget_settings = array()) {
    entity_create('field_storage_config', array(
      'field_name' => $name,
      'entity_type' => $entity_type_id,
      'type' => 'image',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ))->save();

    $field_config = entity_create('field_config', array(
      'field_name' => $name,
      'label' => $name,
      'entity_type' => $entity_type_id,
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ));
    $field_config->save();

    entity_get_form_display($entity_type_id, $type_name, 'default')
      ->setComponent($name, array(
        'type' => 'image_image',
        'settings' => $widget_settings,
      ))
      ->save();

    entity_get_display($entity_type_id, $type_name, 'default')
      ->setComponent($name)
      ->save();

    return $field_config;

  }

  protected function connectToLingotek() {
    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault'
    ], 'Save configuration');
  }

  /**
   * Go to the content bulk management form.
   *
   * @param string $entity_type_id
   *   Entity type ID we want to manage in bulk. By default is node.
   */
  protected function goToContentBulkManagementForm($entity_type_id = 'node') {
    $this->drupalGet('admin/lingotek/manage/' . $entity_type_id);
  }


  /**
   * Go to the config bulk management form and filter one kind of configuration.
   *
   * @param string $filter
   *   Config name of the filter to apply. By default is NULL and will use the
   *   current one.
   */
  protected function goToConfigBulkManagementForm($filter = NULL) {
    $this->drupalGet('admin/lingotek/config/manage');

    if ($filter !== NULL) {
      $edit = ['filters[wrapper][bundle]' => $filter];
      $this->drupalPostForm(NULL, $edit, t('Filter'));
    }
  }

  /**
   * Asserts if the uploaded data contains the expected number of fields.
   *
   * @param array $data
   *   The uploaded data.
   * @param $count
   *   The expected number of items.
   *
   * @return bool
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertUploadedDataFieldCount(array $data, $count) {
    // We have to add one item because of the metadata we include.
    return $this->assertEqual($count + 1, count($data));
  }

  /**
   * Asserts if there are a number of documents with a given status and language
   * label as source.
   *
   * @param string $status
   *   The status we are looking for. Use Lingotek constants.
   * @param string $languageLabel
   *   The language label of the source.
   * @param int $count
   *   The expected number of items.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   *
   * @return bool
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertSourceStatusStateCount($status, $languageLabel, $count, $message = '') {
    $statusCssClass = 'source-' . strtolower($status);
    $statusCount = $this->xpath("//span[contains(@class,'language-icon') and contains(@class, '$statusCssClass')]/a[contains(text(), '$languageLabel')]");
    return $this->assertEqual(count($statusCount), $count, $message);
  }

}
