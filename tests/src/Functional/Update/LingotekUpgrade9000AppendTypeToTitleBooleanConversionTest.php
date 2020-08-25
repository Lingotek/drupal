<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for updating append_type_to_title preference.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekUpgrade9000AppendTypeToTitleBooleanConversionTest extends UpdatePathTestBase {

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
   * Tests the upgrade sets the value for the append_type_to_title preference.
   */
  public function testUpgrade() {
    $this->assertSame('global_setting', $this->lingotekConfiguration->getPreference('append_type_to_title'));

    $this->runUpdates();

    $this->assertSame(TRUE, $this->lingotekConfiguration->getPreference('append_type_to_title'));
  }

}
