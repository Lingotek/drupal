<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\lingotek_test\Controller\FakeAuthorizationController;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek utilities settings form.
 *
 * @group lingotek
 */
class LingotekSettingsTabUtilitiesFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupResources();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test the table shows the right values.
   */
  public function testRefreshResources() {
    $assert_session = $this->assertSession();

    // Activate the settings tab.
    $this->drupalGet('admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], 'Refresh', [], 'lingoteksettings-tab-utilities-form');
    $assert_session->responseContains('Project, workflow, vault, and filter information have been refreshed.');

    $config = \Drupal::configFactory()->getEditable('lingotek.account');
    $communities = $config->get('resources.community');
    $this->assertCount(2, $communities);
    $this->assertEquals([
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ], $communities);
    $projects = $config->get('resources.project');
    $this->assertCount(2, $projects);
    $this->assertEquals([
      'test_project' => 'Test project',
      'test_project2' => 'Test project 2',
    ], $projects);
    $vaults = $config->get('resources.vault');
    $this->assertCount(2, $vaults);
    $this->assertEquals([
      'test_vault' => 'Test vault',
      'test_vault2' => 'Test vault 2',
    ], $vaults);
    $workflows = $config->get('resources.workflow');
    $this->assertCount(2, $workflows);
    $this->assertEquals([
      'test_workflow' => 'Test workflow',
      'test_workflow2' => 'Test workflow 2',
    ], $workflows);
    $filters = $config->get('resources.filter');
    $this->assertCount(3, $filters);
    $this->assertEquals([
      'test_filter' => 'Test filter',
      'test_filter2' => 'Test filter 2',
      'test_filter3' => 'Test filter 3',
    ], $filters);
  }

  /**
   * Setup test resources for the test.
   */
  protected function setupResources() {
    $config = \Drupal::configFactory()->getEditable('lingotek.account');
    $config->set('resources.community', [
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ]);
    $config->set('resources.project', [
      'test_project' => 'Test project',
      'test_project2' => 'Test project 2',
    ]);
    $config->set('resources.vault', [
      'test_vault' => 'Test vault',
      'test_vault2' => 'Test vault 2',
    ]);
    $config->set('resources.workflow', [
      'test_workflow' => 'Test workflow',
      'test_workflow2' => 'Test workflow 2',
    ]);
    $config->set('resources.filter', [
      'test_filter' => 'Test filter',
      'test_filter2' => 'Test filter 2',
      'test_filter3' => 'Test filter 3',
    ]);
    $config->set('access_token', FakeAuthorizationController::ACCESS_TOKEN);

    $config->set('default.community', 'test_community');
    $config->set('default.workflow', 'test_workflow');
    $config->set('default.project', 'test_project');
    $config->set('default.vault', 'test_vault');
    $config->save();
  }

}
