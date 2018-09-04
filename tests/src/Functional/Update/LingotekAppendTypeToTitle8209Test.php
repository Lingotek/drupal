<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path after changing default behavior of appending type to document name in TMS.
 *
 * @group lingotek
 */
class LingotekAppendTypeToTitle8209Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8210.php.gz',
    ];
  }

  /**
   * Tests that the setting to append content type is set to TRUE after updates.
   */
  public function testAppendTypeToTitle() {
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $this->assertNull($lingotek_config->getPreference('append_type_to_title'));

    $this->runUpdates();

    $this->assertTrue($lingotek_config->getPreference('append_type_to_title'));
  }

}
