<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Tests the upgrade path for setting auto_request profile setting.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekProfileAutoRequestTranslationsPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8217.php.gz',
      __DIR__ . '/../../../fixtures/update/profile-autorequest-post-update.php',
    ];
  }

  /**
   * Tests that the upgrade sets the correct value for the profile settings.
   */
  public function testUpgrade() {
    $this->runUpdates();

    $profile = LingotekProfile::load('auto_download');
    $this->assertFalse($profile->hasAutomaticRequest());
    $this->assertFalse($profile->hasAutomaticRequestForTarget('ca'));
    $this->assertFalse($profile->hasAutomaticRequestForTarget('it'));

    $profile = LingotekProfile::load('auto_upload');
    $this->assertTrue($profile->hasAutomaticRequest());
    $this->assertTrue($profile->hasAutomaticRequestForTarget('ca'));
    $this->assertTrue($profile->hasAutomaticRequestForTarget('it'));

  }

}
