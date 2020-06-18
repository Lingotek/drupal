<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Tests the upgrade path for setting custom target save-to vault setting.
 *
 * @group lingotek
 */
class LingotekProfileTargetSaveToVaultPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-88x.lingotek-2x20.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/profile-target-save-to-vault-post-update.php',
    ];
  }

  /**
   * Tests that the upgrade sets the correct value for custom profile settings
   */
  public function testUpgrade() {
    $this->runUpdates();
    $profile = LingotekProfile::load('custom_profile');
    $languages = \Drupal::languageManager()->getLanguages();
    foreach ($languages as $language) {
      $lancode = $language->getId();
      $vault = $profile->getVaultForTarget($lancode);
      $vault_is_set = $vault !== NULL ? TRUE : FALSE;
      $this->assertTrue($vault_is_set);
      $this->assertEquals('default', $vault);
    }
  }

}
