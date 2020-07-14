<?php

namespace Drupal\Tests\lingotek\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

abstract class LingotekFunctionalJavascriptTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $minkDefaultDriverClass = DrupalSelenium2Driver::class;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['lingotek', 'lingotek_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
    $this->connectToLingotek();
  }

  /**
   * Connects to Lingotek.
   */
  protected function connectToLingotek() {
    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault',
    ], 'Save configuration');
  }

  /**
   * Create a new text field.
   *
   * @param string $name
   *   The name of the new field (all lowercase).
   * @param string $type_name
   *   The bundle that this field will be added to.
   * @param string $entity_type_id
   *   The entity type that this field will be added to. Defaults to 'node'.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The field config.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTextField($name, $type_name, $entity_type_id = 'node', array $storage_settings = [], array $field_settings = [], array $widget_settings = []) {
    $fieldStorage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => $name,
      'entity_type' => $entity_type_id,
      'type' => 'text',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ]);
    $fieldStorage->save();
    $field_config = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_name' => $name,
      'label' => $name,
      'entity_type' => $entity_type_id,
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ]);
    $field_config->save();

    $entity_form_display = EntityFormDisplay::load($entity_type_id . '.' . $type_name . '.' . 'default');
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $type_name,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $entity_form_display->setComponent($name, [
      'type' => 'text_textfield',
      'settings' => $widget_settings,
    ])
      ->save();
    $display = EntityViewDisplay::load($entity_type_id . '.' . $type_name . '.' . 'default');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $type_name,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $display->setComponent($name)
      ->save();

    return $field_config;
  }

  /**
   * Create a new image field.
   *
   * @param string $name
   *   The name of the new field (all lowercase).
   * @param string $type_name
   *   The bundle that this field will be added to.
   * @param string $entity_type_id
   *   The entity type that this field will be added to. Defaults to 'node'.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The field config.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createImageField($name, $type_name, $entity_type_id = 'node', array $storage_settings = [], array $field_settings = [], array $widget_settings = []) {
    $fieldStorage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => $name,
      'entity_type' => $entity_type_id,
      'type' => 'image',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ]);
    $fieldStorage->save();
    $field_config = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_name' => $name,
      'label' => $name,
      'entity_type' => $entity_type_id,
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ]);
    $field_config->save();

    $entity_form_display = EntityFormDisplay::load($entity_type_id . '.' . $type_name . '.' . 'default');
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $type_name,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $entity_form_display->setComponent($name, [
      'type' => 'image_image',
      'settings' => $widget_settings,
    ])
      ->save();
    $display = EntityViewDisplay::load($entity_type_id . '.' . $type_name . '.' . 'default');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $type_name,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $display->setComponent($name)
      ->save();

    return $field_config;
  }

  /**
   * Save Lingotek content translation settings.
   *
   * Example:
   * @code
   *   $this->saveLingotekContentTranslationSettings([
   *     'node' => [
   *       'article' => [
   *         'profiles' => 'automatic',
   *         'fields' => [
   *           'title' => 1,
   *           'body' => 1,
   *         ],
   *         'moderation' => [
   *           'upload_status' => 'draft',
   *           'download_transition' => 'request_review',
   *         ],
   *       ],
   *    ],
   *     'paragraph' => [
   *       'image_text' => [
   *         'fields' => [
   *           'field_image_demo' => ['title', 'alt'],
   *           'field_text_demo' => 1,
   *         ],
   *       ],
   *     ],
   *   ]);
   * @endcode
   *
   * @param array $settings
   *   The settings we want to save.
   */
  protected function saveLingotekContentTranslationSettings($settings) {
    $this->drupalGet('/admin/lingotek/settings');

    $page = $this->getSession()->getPage();
    $contentTabDetails = $page->find('css', '#edit-parent-details');
    $contentTabDetails->click();

    foreach ($settings as $entity_type => $entity_type_settings) {
      $entityTabDetails = $page->find('css', '#edit-entity-' . str_replace('_', '-', $entity_type));
      $entityTabDetails->click();

      foreach ($entity_type_settings as $bundle_id => $bundle_settings) {
        /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
        $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle_id);

        $fieldEnabled = $page->find('css', '#edit-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . '-enabled');
        $fieldEnabled->click();
        $this->assertSession()->assertWaitOnAjaxRequest();
        if (isset($bundle_settings['profiles']) && $bundle_settings['profiles'] !== NULL) {
          $page->selectFieldOption('edit-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . '-profiles', $bundle_settings['profiles']);
        }
        $fieldsCheckboxes = $page->findAll('css', '#container-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . ' input[type="checkbox"]');
        $propertiesCheckboxes = $page->findAll('css', 'input[type="checkbox"] .field-property-checkbox');
        /** @var \Behat\Mink\Element\NodeElement $fieldCheckbox */
        foreach ($fieldsCheckboxes as $fieldCheckbox) {
          if ($fieldCheckbox->isChecked()) {
            $fieldCheckbox->click();
          }
        }
        foreach ($propertiesCheckboxes as $propertyCheckbox) {
          if ($propertyCheckbox->isChecked()) {
            $propertyCheckbox->click();
          }
        }
        foreach ($bundle_settings['fields'] as $field_id => $field_properties) {
          $field_definition = $field_definitions[$field_id];
          $field_type_definition = \Drupal::service('plugin.manager.field.field_type')->getDefinition($field_definition->getType());

          $fieldElementId = 'edit-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . '-fields-' . str_replace('_', '-', $field_id);
          $fieldCheckbox = $page->find('css', '#' . $fieldElementId);
          $fieldCheckbox->click();

          if (is_array($field_properties)) {
            $column_groups = $field_type_definition['column_groups'];
            $properties = [];
            foreach ($column_groups as $property_id => $property) {
              if (isset($property['translatable']) && $property['translatable']) {
                $property_definitions = $field_type_definition['class']::propertyDefinitions($field_definition->getFieldStorageDefinition());
                if (isset($property_definitions[$property_id])) {
                  $properties[$property_id] = $property_id;
                }
              }
            }

            // Disable all properties.
            foreach ($properties as $field_property) {
              $propertyElementId = 'edit-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . '-fields-' . str_replace('_', '-', $field_id) . 'properties-' . str_replace('_', '-', $field_property);
              $propertyCheckbox = $page->find('css', '#' . $propertyElementId);
              if ($propertyCheckbox->isChecked()) {
                $propertyCheckbox->click();
              }
            }

            foreach ($field_properties as $field_property) {
              $propertyElementId = 'edit-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . '-fields-' . str_replace('_', '-', $field_id) . 'properties-' . str_replace('_', '-', $field_property);
              $propertyCheckbox = $page->find('css', '#' . $propertyElementId);
              if (!$propertyCheckbox->isChecked()) {
                $propertyCheckbox->click();
              }
            }
          }
        }
        if (isset($bundle_settings['moderation']) && $bundle_settings['moderation'] !== NULL) {
          $page->selectFieldOption('edit-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . '-moderation-upload-status', $bundle_settings['moderation']['upload_status']);
          $page->selectFieldOption('edit-' . str_replace('_', '-', $entity_type) . '-' . str_replace('_', '-', $bundle_id) . '-moderation-download-transition', $bundle_settings['moderation']['download_transition']);
        }
      }
    }
    $this->drupalPostForm(NULL, [], 'Save', [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Save Lingotek translation settings for node types.
   *
   * Example:
   * @code
   *      $this->saveLingotekContentTranslationSettingsForNodeTypes(
   *        ['article', 'page'], manual);
   * @endcode
   *
   * @param array $node_types
   *   The node types we want to enable.
   * @param string $profile
   *   The profile id we want to use.
   */
  protected function saveLingotekContentTranslationSettingsForNodeTypes($node_types = ['article'], $profile = 'automatic') {
    $settings = [];
    foreach ($node_types as $node_type) {
      $settings['node'][$node_type] = [
        'profiles' => $profile,
        'fields' => [
          'title' => 1,
          'body' => 1,
        ],
      ];
    }
    $this->saveLingotekContentTranslationSettings($settings);
  }

  protected function stop() {
    $this->assertSession()->waitForElementVisible('css', '.test-wait', 100000000000000000000000000);
  }

}
