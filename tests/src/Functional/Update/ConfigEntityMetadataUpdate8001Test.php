<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\lingotek\Lingotek;

/**
 * Tests the upgrade path for config entity metadata.
 *
 * @group lingotek
 */
class ConfigEntityMetadataUpdate8001Test extends UpdatePathTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['lingotek'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/config-entity-metadata-8001.php',
    ];
  }

  /**
   * Tests that field handlers are updated properly.
   */
  public function testConfigEntityMetadataUpdate8001() {
    $this->runUpdates();

    // Load the metadata.
    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata $metadata */
    $metadata = LingotekConfigMetadata::load('node_type.basic_page');

    // Check that values are the right ones.
    $this->assertEqual('8bf008cd-6e36-43a3-a325-e33ae6befe13', $metadata->getDocumentId());
    $this->assertEqual('eb223fc107f7598b96c91e6852f9e58e', $metadata->getHash());

    $source_status = $metadata->getSourceStatus();
    $target_status = $metadata->getTargetStatus();
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status['en']);
    $this->assertEqual(Lingotek::STATUS_REQUEST, $target_status['es']);
  }

}
