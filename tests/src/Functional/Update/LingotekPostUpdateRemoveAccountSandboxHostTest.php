<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade for clearing 'account.sandbox_host' and
 * 'account.use_production' settings.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekPostUpdateRemoveAccountSandboxHostTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-88x.lingotek-2x20.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade for clearing 'account.sandbox_host' and
   * 'account.use_production' settings.
   */
  public function testUpgrade() {
    $lingotek_settings = $this->config('lingotek.settings');
    $this->assertFalse($lingotek_settings->get('account.use_production'));
    $this->assertSame('https://myaccount.lingotek.com', $lingotek_settings->get('account.sandbox_host'));

    $this->runUpdates();

    $lingotek_settings = \Drupal::configFactory()->get('lingotek.settings');
    $this->assertNull($lingotek_settings->get('account.use_production'));
    $this->assertNull($lingotek_settings->get('account.sandbox_host'));
  }

}
