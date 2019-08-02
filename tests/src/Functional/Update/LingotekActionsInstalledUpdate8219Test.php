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
class LingotekActionsInstalledUpdate8219Test extends UpdatePathTestBase {

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
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8217.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the correct field formatter id in a view.
   */
  public function testUpgrade() {
    $notExpectedActions = [
      'node_lingotek_disassociate_action',
    ];
    $expectedActions = [
      'node_lingotek_cancel_action',
      'node_es_lingotek_cancel_translation_action',
      'node_en_lingotek_cancel_translation_action',
      'node_de_lingotek_cancel_translation_action',
    ];

    $actions = Action::loadMultiple();
    $this->assertCount(38, $actions);

    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayNotHasKey($expectedAction, $actions, 'There is not an action with id: ' . $expectedAction);
    }
    foreach ($notExpectedActions as $notExpectedAction) {
      $this->assertArrayHasKey($notExpectedAction, $actions, 'There is an action with id: ' . $notExpectedAction);
    }

    $this->runUpdates();

    $actions = Action::loadMultiple();
    // There should be no new action: 1 for cancel was added, but the
    // action for disassociating was removed.
    // And then then three cancel translation (one per language)
    $this->assertCount(38 + 3, $actions);

    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayHasKey($expectedAction, $actions, 'There is an action with id: ' . $expectedAction);
    }
    foreach ($notExpectedActions as $notExpectedAction) {
      $this->assertArrayNotHasKey($notExpectedAction, $actions, 'There is not an action with id: ' . $notExpectedAction);
    }
  }

}
