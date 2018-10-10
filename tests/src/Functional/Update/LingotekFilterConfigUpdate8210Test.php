<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Tests the upgrade path for updating the filter defaults.
 *
 * @group lingotek
 */
class LingotekFilterConfigUpdate8210Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8205.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the different filters.
   *
   * The filters to setup are default filter, account filters, and the different
   * existing profiles.
   */
  public function testUpgrade() {
    $this->runUpdates();

    $profiles = LingotekProfile::loadMultiple();
    foreach ($profiles as $id => $profile) {
      $this->assertIdentical('drupal_default', $profile->getFilter(), "Profile $id default filter is the expected one.");
      $this->assertIdentical('drupal_default', $profile->getSubFilter(), "Profile $id default filter is the expected one.");
    }

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('lingotek.settings');
    $default_filter = $config->get('default.filter');
    $this->assertIdentical('drupal_default', $default_filter, 'Default filter is the expected one.');

    $filters = $config->get('account.resources.filter');
    $this->assertIdentical([], $filters, 'Account filters is empty.');
  }

}
