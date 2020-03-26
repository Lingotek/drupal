<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for setting split_download_all preference.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekSplitDownloadAllPreferenceUpdate8220Test extends UpdatePathTestBase {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->lingotekConfiguration = $this->container->get('lingotek.configuration');
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8217.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the correct field formatter id in a view.
   */
  public function testUpgrade() {
    $this->assertNull($this->lingotekConfiguration->getPreference('split_download_all'));

    $this->runUpdates();

    $this->assertFalse($this->lingotekConfiguration->getPreference('split_download_all'));
  }

}
