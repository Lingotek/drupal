<?php

/**
 * @file
 * Contains \Drupal\lingotek\Tests\LingotekConfigImportTest.
 */

namespace Drupal\lingotek\Tests;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\simpletest\KernelTestBase;

/**
 * Tests content translation updates performed during config import.
 *
 * @group lingotek
 */
class LingotekConfigImportTest extends KernelTestBase {

  /**
   * Config Importer object used for testing.
   *
   * @var \Drupal\Core\Config\ConfigImporter
   */
  protected $configImporter;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('config_test', 'system', 'user', 'entity_test', 'language', 'locale', 'content_translation', 'config_translation', 'lingotek');

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_mul');
  }

  /**
   * Tests config import updates.
   */
  public function testConfigImportUpdates() {
    // Create a content entity and some config that depends on it.
    $content_entity = EntityTestMul::create([]);
    $content_entity->save();
    $storage = $this->container->get('entity.manager')->getStorage('config_test');
    // Test dependencies between modules.
    $entity1 = $storage->create(
      array(
        'id' => 'entity1',
        'dependencies' => array(
          'enforced' => array(
            'content' => array($content_entity->getConfigDependencyName())
          )
        )
      )
    );
    $entity1->save();

    // Copy all configuration to staging.
    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));

    // Set up the lingotek configuration in staging.
    $entity_type_id = 'entity_test_mul';
    $config_name = 'lingotek.settings';
    $config_id = $entity_type_id . '.' . $entity_type_id;
    $storage = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');

    // Create new config entity for content settings.
    \Drupal::service('content_translation.manager')->setEnabled($entity_type_id, $entity_type_id, TRUE);

    // Verify the configuration to create does not exist yet.
    $this->assertIdentical($storage->exists($config_name), FALSE, $config_name . ' not found.');

    // Create new config entity for content language translation.
    $data = array(
      'uuid' => 'a019d89b-c4d9-4ed4-b859-894e4e2e93cf',
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => array(
        'module' => array('content_translation')
      ),
      'id' => $config_id,
      'target_entity_type_id' => 'entity_test_mul',
      'target_bundle' => 'entity_test_mul',
      'default_langcode' => 'site_default',
      'language_alterable' => FALSE,
      'third_party_settings' => array(
        'content_translation' => array('enabled' => TRUE),
      ),
    );
    $sync->write('language.content_settings.' . $config_id, $data);

    // Create new config for lingotek settings.
    $data = array(
      'translate' => array(
        'entity' => array(
          $entity_type_id => array(
            $entity_type_id => array(
              'enabled' => TRUE,
              'field' => array(
                'name' => TRUE,
                'field_test_text' => TRUE,
              ),
              'profile' => 'automatic',
            ),
          ),
        ),
      ),
    );
    $sync->write($config_name, $data);
    $this->assertIdentical($sync->exists($config_name), TRUE, $config_name . ' found.');

    // Import.
    $this->configImporter()->import();

    // Verify the values appeared.
    $config = $this->config($config_name);
    $this->assertIdentical($config->get('translate.entity.entity_test_mul.entity_test_mul.field.field_test_text'), TRUE);

    /** @var LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $this->assertIdentical($lingotek_config->isEnabled($entity_type_id), TRUE);

    // Verify that updates were performed.
    $entity_type = $this->container->get('entity.manager')->getDefinition($entity_type_id);
    $table = $entity_type->getDataTable();
    $db_schema = $this->container->get('database')->schema();
    $result = $db_schema->fieldExists($table, 'lingotek_document_id');
    $this->assertTrue($result, 'Lingotek updates were successfully performed during config import.');
  }

}
