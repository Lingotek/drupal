<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Tests the upgrade path for changing the profile entity id.
 *
 * @group lingotek
 */
class LingotekProfileEntityIdUpdate8002Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8002.php.gz',
    ];
  }

  /**
   * Tests that the entity definition is loaded correctly.
   */
  public function testConfigEntityMetadataUpdate8001() {
    $this->runUpdates();

    // The lingotek_profile entity exists, but not the profile one.
    $entity_type = \Drupal::entityTypeManager()->getDefinition('profile', FALSE);
    $this->assertNull($entity_type);
    $entity_type = \Drupal::entityTypeManager()->getDefinition('lingotek_profile', FALSE);
    $this->assertNotNull($entity_type->id());

    // Load a profile, it should work.
    $profile = LingotekProfile::load('automatic');
    $this->assertNotNull($profile->id());
  }

}
