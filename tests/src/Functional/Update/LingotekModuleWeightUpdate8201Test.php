<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for updating the module weight.
 *
 * @group lingotek
 */
class LingotekModuleWeightUpdate8201Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8201.php.gz',
    ];
  }

  /**
   * Tests that the module weight update is executed correctly.
   */
  public function testModuleWeightUpdate() {
    $this->runUpdates();

    $extension_config = $this->config('core.extension');
    $content_translation_weight = $extension_config->get('module.content_translation');
    $lingotek_weight = $extension_config->get('module.lingotek');

    $this->assertTrue($lingotek_weight > $content_translation_weight, 'Lingotek weight is higher than content_translation.');
  }

}
