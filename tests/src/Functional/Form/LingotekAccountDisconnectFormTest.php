<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek account disconnection form.
 *
 * @group lingotek
 */
class LingotekAccountDisconnectFormTest extends LingotekTestBase {

  /**
   * Test that we can disconnect.
   */
  public function testAccountDisconnect() {
    // We try to disconnect from an already connected account.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], t('Disconnect'), [], 'lingoteksettings-tab-account-form');

    \Drupal::state()->set('must_remain_disconnected', TRUE);

    // We need to confirm disconnection.
    $this->drupalPostForm(NULL, [], t('Disconnect'));

    // We have been redirected to the account connection form.
    $this->assertUrl('/admin/lingotek/setup/account');

    // We don't have an account anymore.
    $lingotek_config = \Drupal::config('lingotek.settings');
    $this->assertNull($lingotek_config->get('account.access_token'));
    $this->assertNull($lingotek_config->get('account.login_id'));
    $this->assertNull($lingotek_config->get('account.callback_url'));

    // We connect to Lingotek again and it should work. We should not need to
    // set the defaults, as they are already set.
    $this->connectToLingotek();

    // We have an account again.
    $lingotek_config = \Drupal::config('lingotek.settings');
    $this->assertNotNull($lingotek_config->get('account.access_token'));
    $this->assertNotNull($lingotek_config->get('account.login_id'));
    $this->assertNotNull($lingotek_config->get('account.callback_url'));
  }

}
