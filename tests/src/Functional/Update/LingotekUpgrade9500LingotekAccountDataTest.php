<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for setting enable_download_interim preference.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekUpgrade9500LingotekAccountDataTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-88x.lingotek-2x20.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/9500-account-settings.php',
    ];
  }

  /**
   * Tests that the upgrade properly migrates the settings to lingotek.account config object.
   */
  public function testUpgrade() {
    $config = $this->config('lingotek.settings');
    $this->assertSame('test-9500-token', $config->get('account.access_token'));
    $this->assertSame('myloginid@drupal.org', $config->get('account.login_id'));
    $this->assertSame('https://myaccount.lingotek.com', $config->get('account.host'));
    $this->assertSame('auth/authorize.html', $config->get('account.authorize_path'));
    $this->assertSame('e39e24c7-6c69-4126-946d-cf8fbff38ef0', $config->get('account.default_client_id'));
    $this->assertSame('basic', $config->get('account.plan_type'));
    $this->assertCount(5, $config->get('account.resources.community'));
    $this->assertCount(5, $config->get('account.resources.project'));
    $this->assertCount(5, $config->get('account.resources.workflow'));
    $this->assertCount(5, $config->get('account.resources.vault'));
    $this->assertCount(5, $config->get('account.resources.filter'));
    $this->assertSame('comm-dddd-bbbb-cccc-dddd', $config->get('default.community'));
    $this->assertSame('proj-bbbb-bbbb-cccc-dddd', $config->get('default.project'));
    $this->assertSame('wfwf-eeee-bbbb-cccc-dddd', $config->get('default.workflow'));
    $this->assertSame('vault-cccc-bbbb-cccc-dddd', $config->get('default.vault'));
    $this->assertSame('fltr-eeee-bbbb-cccc-dddd', $config->get('default.filter'));
    $this->assertSame('fltr-aaaa-bbbb-cccc-dddd', $config->get('default.subfilter'));

    $this->runUpdates();

    $config = $this->config('lingotek.settings');
    $this->assertEmpty($config->get('account.access_token'));
    $this->assertEmpty($config->get('account.login_id'));
    $this->assertEmpty($config->get('account.host'));
    $this->assertEmpty($config->get('account.authorize_path'));
    $this->assertEmpty($config->get('account.default_client_id'));
    $this->assertEmpty($config->get('account.resources.community'));
    $this->assertEmpty($config->get('account.resources.project'));
    $this->assertEmpty($config->get('account.resources.workflow'));
    $this->assertEmpty($config->get('account.resources.vault'));
    $this->assertEmpty($config->get('account.resources.filter'));
    $this->assertEmpty($config->get('default.community'));
    $this->assertEmpty($config->get('default.project'));
    $this->assertEmpty($config->get('default.workflow'));
    $this->assertEmpty($config->get('default.vault'));
    $this->assertEmpty($config->get('default.filter'));
    $this->assertEmpty($config->get('default.subfilter'));

    $accountConfig = $this->config('lingotek.account');
    $this->assertSame('test-9500-token', $accountConfig->get('access_token'));
    $this->assertSame('myloginid@drupal.org', $accountConfig->get('login_id'));
    $this->assertSame('https://myaccount.lingotek.com', $accountConfig->get('host'));
    $this->assertSame('auth/authorize.html', $accountConfig->get('authorize_path'));
    $this->assertSame('e39e24c7-6c69-4126-946d-cf8fbff38ef0', $accountConfig->get('default_client_id'));
    $this->assertSame('basic', $accountConfig->get('plan_type'));
    $this->assertCount(5, $accountConfig->get('resources.community'));
    $this->assertCount(5, $accountConfig->get('resources.project'));
    $this->assertCount(5, $accountConfig->get('resources.workflow'));
    $this->assertCount(5, $accountConfig->get('resources.vault'));
    $this->assertCount(5, $accountConfig->get('resources.filter'));
    $this->assertSame('comm-dddd-bbbb-cccc-dddd', $accountConfig->get('default.community'));
    $this->assertSame('proj-bbbb-bbbb-cccc-dddd', $accountConfig->get('default.project'));
    $this->assertSame('wfwf-eeee-bbbb-cccc-dddd', $accountConfig->get('default.workflow'));
    $this->assertSame('vault-cccc-bbbb-cccc-dddd', $accountConfig->get('default.vault'));
    $this->assertSame('fltr-eeee-bbbb-cccc-dddd', $accountConfig->get('default.filter'));
    $this->assertSame('fltr-aaaa-bbbb-cccc-dddd', $accountConfig->get('default.subfilter'));
  }

}
