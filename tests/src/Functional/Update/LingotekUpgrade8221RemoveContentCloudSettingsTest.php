<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for removing Lingotek Content Cloud related preferences.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekUpgrade8221RemoveContentCloudSettingsTest extends UpdatePathTestBase {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->lingotekConfiguration = $this->container->get('lingotek.configuration');
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-88x.lingotek-2x20.standard.php.gz',
    ];
  }

  /**
   * Tests that the upgrade removes Lingotek Content Cloud related preferences.
   */
  public function testUpgrade() {
    $this->assertFalse($this->lingotekConfiguration->getPreference('enable_content_cloud'));
    $this->assertEqual($this->lingotekConfiguration->getPreference('content_cloud_import_format'), 'article');
    $this->assertEqual($this->lingotekConfiguration->getPreference('content_cloud_import_status'), 0);

    $this->runUpdates();

    $this->assertNull($this->lingotekConfiguration->getPreference('enable_content_cloud'));
    $this->assertNull($this->lingotekConfiguration->getPreference('content_cloud_import_format'));
    $this->assertNull($this->lingotekConfiguration->getPreference('content_cloud_import_status'));
  }

}
