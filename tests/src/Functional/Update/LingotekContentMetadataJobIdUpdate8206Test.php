<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lingotek\Entity\LingotekContentMetadata;

/**
 * Tests the upgrade path for adding the job id field to content metadata.
 *
 * @group lingotek
 */
class LingotekContentMetadataJobIdUpdate8206Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8205.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the job id field as empty.
   */
  public function testUpgrade() {
    $this->runUpdates();

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    foreach ($metadatas as $id => $metadata) {
      $this->assertSame('', $metadata->getJobId(), "Job id is empty.");
    }
  }

}
