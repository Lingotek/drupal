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
class LingotekActionsInstalledUpdate8214Test extends UpdatePathTestBase {

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
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8213.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the correct field formatter id in a view.
   */
  public function testUpgrade() {
    $actions = Action::loadMultiple();
    $this->assertCount(25, $actions);

    $this->runUpdates();

    $actions = Action::loadMultiple();
    // There should be 6 new actions: 5 steps in the complete roundtrip plus the
    // action for disassociating.
    // After lingotek_update_8216 count on 4 more.
    $this->assertCount(25 + 9 + 4, $actions);

    /**
     * 0 => 'comment_delete_action',
     * 1 => 'comment_publish_action',
     * 2 => 'comment_save_action',
     * 3 => 'comment_unpublish_action',
     * 4 => 'node_de_lingotek_check_translation_action',
     * 5 => 'node_de_lingotek_download_translation_action',
     * 6 => 'node_de_lingotek_request_translation_action',
     * 7 => 'node_delete_action',
     * 8 => 'node_en_lingotek_check_translation_action',
     * 9 => 'node_en_lingotek_download_translation_action',
     * 10 => 'node_en_lingotek_request_translation_action',
     * 11 => 'node_es_lingotek_check_translation_action',
     * 12 => 'node_es_lingotek_download_translation_action',
     * 13 => 'node_es_lingotek_request_translation_action',
     * 14 => 'node_lingotek_check_translations_action',
     * 15 => 'node_lingotek_check_upload_action',
     * 16 => 'node_lingotek_disassociate_action',
     * 17 => 'node_lingotek_download_translations_action',
     * 18 => 'node_lingotek_request_translations_action',
     * 19 => 'node_lingotek_upload_action',
     * 20 => 'node_make_sticky_action',
     * 21 => 'node_make_unsticky_action',
     * 22 => 'node_promote_action',
     * 23 => 'node_publish_action',
     * 24 => 'node_save_action',
     * 25 => 'node_unpromote_action',
     * 26 => 'node_unpublish_action',
     * 27 => 'user_add_role_action.administrator',
     * 28 => 'user_add_role_action.translation_manager',
     * 29 => 'user_block_user_action',
     * 30 => 'user_cancel_user_action',
     * 31 => 'user_remove_role_action.administrator',
     * 32 => 'user_remove_role_action.translation_manager',
     * 33 => 'user_unblock_user_action',
     */

    $expectedActions = [
      'node_es_lingotek_request_translation_action',
      'node_en_lingotek_request_translation_action',
      'node_de_lingotek_request_translation_action',
      'node_es_lingotek_check_translation_action',
      'node_en_lingotek_check_translation_action',
      'node_de_lingotek_check_translation_action',
      'node_es_lingotek_download_translation_action',
      'node_en_lingotek_download_translation_action',
      'node_de_lingotek_download_translation_action',
    ];
    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayHasKey($expectedAction, $actions, 'There is an action with id: ' . $expectedAction);
    }
  }

}
