<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Lingotek dashboard.
 *
 * @group lingotek
 */
class LingotekAccountTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['lingotek', 'lingotek_test'];

  /**
   * Tests that the dashboard cannot be accessed without a valid user.
   */
  public function testAccountCanConnect() {
    \Drupal::state()->set('must_remain_disconnected', TRUE);

    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
    // Try to navigate to the Dashboard page, and assert we are redirected.
    $this->drupalGet('admin/lingotek/setup/account');
    // Fake the connection to an account in Lingotek.
    $this->clickLink('Connect Lingotek Account');
    // Our fake backend generates a token, returns to the site, completes the
    // handshake and return some fake data.
    $this->assertText('Your account settings have been saved.');
    // Then we can select the defaults for the different fields.
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->assertText('The configuration options have been saved.');
    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
    ], 'Save configuration');
    $this->assertText('The configuration options have been saved.');
    // We are done with the defaults, we should be redirected to the dashboard.
    $this->assertText('Dashboard');
    $this->assertUrl('admin/lingotek');
  }

  /**
   * Tests that the dashboard cannot be accessed without a valid user.
   */
  public function testDashboardIsNotAvailableBeforeConnecting() {
    \Drupal::state()->set('must_remain_disconnected', TRUE);

    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Try to navigate to the Dashboard page, and assert we are redirected.
    $this->drupalGet('admin/lingotek');
    $this->assertUrl('admin/lingotek/setup/account');
    $assert_session->linkExists('Connect Lingotek Account');
  }

  public function testHandshakePage() {
    \Drupal::state()->set('must_remain_disconnected', TRUE);

    $assert_session = $this->assertSession();

    // We avoid the redirect so we can see where the user will land for some
    // seconds.
    \Drupal::state()->set('authorize_no_redirect', TRUE);

    // Login as admin.
    $this->drupalLogin($this->rootUser);
    // Try to navigate to the Dashboard page, and assert we are redirected.
    $this->drupalGet('admin/lingotek/setup/account');

    // Fake the connection to an account in Lingotek.
    $this->clickLink('Connect Lingotek Account');
    // Our fake backend generates a token, returns to the site and waits for the
    // redirect.
    $assert_session->addressEquals('admin/lingotek/setup/account/handshake#access_token=test_token');
  }

}
