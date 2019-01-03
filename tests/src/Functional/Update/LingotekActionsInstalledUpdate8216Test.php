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
class LingotekActionsInstalledUpdate8216Test extends UpdatePathTestBase {

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
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8216.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the correct field formatter id in a view.
   */
  public function testUpgrade() {
    $actions = Action::loadMultiple();
    $this->assertCount(34, $actions);

    $this->runUpdates();

    $actions = Action::loadMultiple();
    // There should be 4 new actions: 1 for delete translations plus the
    // actions for deleting a concrete translation per language.
    $this->assertCount(34 + 4, $actions);

    $expectedActions = [
      'node_lingotek_delete_translations_action',
      'node_es_lingotek_delete_translation_action',
      'node_en_lingotek_delete_translation_action',
      'node_de_lingotek_delete_translation_action',
    ];
    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayHasKey($expectedAction, $actions, 'There is an action with id: ' . $expectedAction);
    }
  }

}
