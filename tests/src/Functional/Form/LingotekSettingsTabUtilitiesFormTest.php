<?php

namespace Drupal\Tests\lingotek\Functional\Form;

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

    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $communities = $config->get('account.resources.community');
    $this->assertCount(2, $communities);
    $this->assertEquals([
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ], $communities);
    $projects = $config->get('account.resources.project');
    $this->assertCount(2, $projects);
    $this->assertEquals([
      'test_project' => 'test_project',
      'test_project2' => 'test_project 2',
    ], $projects);
    $vaults = $config->get('account.resources.vault');
    $this->assertCount(2, $vaults);
    $this->assertEquals([
      'test_vault' => 'test_vault',
      'test_vault2' => 'test_vault 2',
    ], $vaults);
    $workflows = $config->get('account.resources.workflow');
    $this->assertCount(2, $workflows);
    $this->assertEquals([
      'test_workflow' => 'test_workflow',
      'test_workflow2' => 'test_workflow 2',
    ], $workflows);
    $filters = $config->get('account.resources.filter');
    $this->assertCount(3, $filters);
    $this->assertEquals([
      'test_filter' => 'test_filter',
      'test_filter2' => 'test_filter 2',
      'test_filter3' => 'test_filter 3',
    ], $filters);
  }

  /**
   * Setup test resources for the test.
   */
  protected function setupResources() {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('account.resources.community', [
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ]);
    $config->set('account.resources.project', [
      'test_project' => 'test_project',
      'test_project2' => 'test_project 2',
    ]);
    $config->set('account.resources.vault', [
      'test_vault' => 'test_vault',
      'test_vault2' => 'test_vault 2',
    ]);
    $config->set('account.resources.workflow', [
      'test_workflow' => 'test_workflow',
      'test_workflow2' => 'test_workflow 2',
    ]);
    $config->set('account.resources.filter', [
      'test_filter' => 'test_filter',
      'test_filter2' => 'test_filter 2',
      'test_filter3' => 'test_filter 3',
    ]);
    $config->save();
  }

}
