<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the upgrade path for updating the Lingotek administration roles with
 * the manage translations permission.
 *
 * @group lingotek
 */
class LingotekManageTranslationsPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.manage-lingotek-translations-permission.php.gz',
    ];
  }

  /**
   * Tests that the Lingotek role has the new permission.
   */
  public function testLingotekManageTranslationsPermissionPostUpdate() {
    // The role doesn't have the permission.
    $role = Role::load('translation_manager');
    $this->assertFalse($role->hasPermission('manage lingotek translations'), "The Translation Manager role doesn't have the 'Manage Lingotek Translations' permission.");
    $this->assertTrue($role->hasPermission('administer lingotek'), "The Translation Manager role has the 'Administer Lingotek' permission.");

    $this->runUpdates();

    // The role now has the permission.
    $role = Role::load('translation_manager');
    $this->assertTrue($role->hasPermission('manage lingotek translations'), "The Translation Manager role has the 'Manage Lingotek Translations' permission.");
    $this->assertTrue($role->hasPermission('administer lingotek'), "The Translation Manager role has the 'Administer Lingotek' permission.");
  }

}
