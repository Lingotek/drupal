<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\system\Entity\Action;

/**
 * Tests the upgrade path for installing the lingotek actions if required.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekActionsInstalledUpdate8210Test extends UpdatePathTestBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->configFactory = $this->container->get('config.factory');
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8210.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the correct field formatter id in a view.
   */
  public function testUpgrade() {
    $actions = Action::loadMultiple();
    $this->assertEquals(19, count($actions));

    $this->runUpdates();

    $actions = Action::loadMultiple();
    // There should be 6 new actions: 5 steps in the complete roundtrip plus the
    // action for disassociating.
    $this->assertEquals(19 + 6, count($actions));

    $expectedActions = [
      'node_lingotek_upload_action',
      'node_lingotek_check_upload_action',
      'node_lingotek_request_translations_action',
      'node_lingotek_check_translations_action',
      'node_lingotek_download_translations_action',
      'node_lingotek_disassociate_action',
    ];
    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayHasKey($expectedAction, $actions, 'There is an action with id: ' . $expectedAction);
    }
  }

}
