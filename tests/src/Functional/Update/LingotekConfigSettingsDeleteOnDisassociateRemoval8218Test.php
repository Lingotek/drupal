<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path moving Lingotek profile from settings to config metadata.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekConfigSettingsDeleteOnDisassociateRemoval8218Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8217.php.gz',
      __DIR__ . '/../../../fixtures/update/lingoteksettings-delete_tms_documents_upon_disassociation-8018.php',
    ];
  }

  /**
   * Tests that the Lingotek metadata dependencies are updated correctly.
   */
  public function testDeleteOnDisassociateRemoval() {
    // The values we want to remove.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('lingotek.settings');

    $this->assertTrue($config->get('preference.delete_tms_documents_upon_disassociation'));
    $this->assertNotNull($config->get('preference.delete_tms_documents_upon_disassociation'));

    $this->runUpdates();

    // The values were removed as expected when expected.
    $config = $config_factory->getEditable('lingotek.settings');
    $this->assertNull($config->get('preference.delete_tms_documents_upon_disassociation'));
  }

}
