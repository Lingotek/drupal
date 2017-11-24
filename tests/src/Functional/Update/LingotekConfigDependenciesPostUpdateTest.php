<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lingotek\Entity\LingotekConfigMetadata;

/**
 * Tests the upgrade path for updating the Lingotek metadata config dependencies.
 *
 * @group lingotek
 */
class LingotekConfigDependenciesPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.8x19.php.gz',
      __DIR__ . '/../../../fixtures/update/metadata-config-dependencies-post-update.php',
    ];
  }

  /**
   * Tests that the Lingotek metadata dependencies are updated correctly.
   */
  public function testLingotekMetadataConfigDependenciesPostUpdate() {
    // The dependencies are wrong.
    $type_metadata = LingotekConfigMetadata::load('node_type.article');
    $type_dependencies = $type_metadata->getDependencies();
    $this->assertEqual('node_type.article', $type_dependencies['config'][0]);

    $field_metadata = LingotekConfigMetadata::load('field_config.node.article.body');
    $field_dependencies = $field_metadata->getDependencies();
    $this->assertEqual('field_config.node.article.body', $field_dependencies['config'][0]);

    $this->runUpdates();

    // The dependencies are calculated correctly.
    $type_metadata = LingotekConfigMetadata::load('node_type.article');
    $type_dependencies = $type_metadata->getDependencies();
    $this->assertEqual('node.type.article', $type_dependencies['config'][0]);

    $field_metadata = LingotekConfigMetadata::load('field_config.node.article.body');
    $field_dependencies = $field_metadata->getDependencies();
    $this->assertEqual('field.field.node.article.body', $field_dependencies['config'][0]);
  }

}
