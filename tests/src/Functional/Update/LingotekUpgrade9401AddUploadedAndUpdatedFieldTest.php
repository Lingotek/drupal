<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for adding 'updated date' and 'last uploaded' fields.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekUpgrade9401AddUploadedAndUpdatedFieldTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-88x.lingotek-2x20.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade for adding 'updated date' and 'last uploaded' fields.
   */
  public function testUpgrade() {

    try {
      \Drupal::database()->select('lingotek_metadata', 'lmd')->fields('lmd', ['updated_timestamp', 'uploaded_timestamp'])->execute()->fetch();
      $this->fail('Update 9401 seems to have run prior to updates');
    }
    catch (DatabaseExceptionWrapper $exception) {
      // Do nothing
    }

    $this->runUpdates();

    try {
      \Drupal::database()->select('lingotek_metadata', 'lmd')->fields('lmd', ['updated_timestamp', 'uploaded_timestamp'])->execute()->fetch();
    }
    catch (DatabaseExceptionWrapper $exception) {
      $this->fail('Update 9401 didn\'t add the expected fields: ' . $exception->getMessage());
    }
  }

}
