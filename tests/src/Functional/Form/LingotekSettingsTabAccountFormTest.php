<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\lingotek_test\Controller\FakeAuthorizationController;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek account settings form.
 *
 * @group lingotek
 */
class LingotekSettingsTabAccountFormTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'image'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupResources();
  }

  /**
   * Test the table shows the right values.
   */
  public function testTableValues() {
    $this->drupalGet('admin/lingotek/settings');

    $this->assertTableValue('status', 'Active');
    $this->assertTableValue('plan', 'No');
    $this->assertTableValue('activation', 'testUser@example.com');
    $this->assertTableValue('token', FakeAuthorizationController::ACCESS_TOKEN);
    $this->assertTableValue('community', 'Test community (test_community)');
    $this->assertTableValue('workflow', 'Test workflow (test_workflow)');
    $this->assertTableValue('project', 'Test project (test_project)');
    $this->assertTableValue('vault', 'Test vault (test_vault)');
    $this->assertTableValue('filter', 'Drupal Default (drupal_default)');
    $this->assertTableValue('subfilter', 'Drupal Default (drupal_default)');
    $this->assertTableValue('tms', \Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->getBasePath());
    $this->assertTableValue('gmc', 'https://gmc.lingotek.com');

    $this->clickLink('Edit defaults');

    $edit = [
      'community' => 'test_community2',
      'workflow' => 'test_workflow2',
      'project' => 'test_project2',
      'vault' => 'test_vault2',
      'filter' => 'test_filter2',
      'subfilter' => 'test_filter3',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $this->assertTableValue('status', 'Active');
    $this->assertTableValue('plan', 'No');
    $this->assertTableValue('activation', 'testUser@example.com');
    $this->assertTableValue('token', FakeAuthorizationController::ACCESS_TOKEN);
    $this->assertTableValue('community', 'Test community 2 (test_community2)');
    $this->assertTableValue('workflow', 'Test workflow 2 (test_workflow2)');
    $this->assertTableValue('project', 'Test project 2 (test_project2)');
    $this->assertTableValue('vault', 'Test vault 2 (test_vault2)');
    $this->assertTableValue('filter', 'Test filter 2 (test_filter2)');
    $this->assertTableValue('subfilter', 'Test filter 3 (test_filter3)');
    $this->assertTableValue('tms', \Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->getBasePath());
    $this->assertTableValue('gmc', 'https://gmc.lingotek.com');
  }

  /**
 * Test the table has and displays the project default option for workflows
 */
  public function testTableWithDefaultProjectWorkflow() {
    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink('Edit defaults');
    $edit = ['workflow' => 'project_default'];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');
    $this->assertTableValue('workflow', 'Project Default (project_default)');
  }

  /**
   * Test the table shows the right values.
   */
  public function testTableValuesWithDefaultFilters() {
    $this->drupalGet('admin/lingotek/settings');

    $this->assertTableValue('filter', 'Drupal Default (drupal_default)');
    $this->assertTableValue('subfilter', 'Drupal Default (drupal_default)');
  }

  /**
   * Check to see if two values are equal.
   *
   * @param $field
   *   The field value to check.
   * @param $expected
   *   The expected value to check.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertTableValue($field, $expected, $message = '') {
    $xpathValue = $this->xpath('//tr[@data-drupal-selector="edit-account-table-' . $field . '-row"]//td[2]');
    $value = $xpathValue[0]->getText();
    return $this->assertEquals($expected, $value, $message);
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
      'test_project' => 'Test project',
      'test_project2' => 'Test project 2',
    ]);
    $config->set('account.resources.vault', [
      'test_vault' => 'Test vault',
      'test_vault2' => 'Test vault 2',
    ]);
    $config->set('account.resources.workflow', [
      'test_workflow' => 'Test workflow',
      'test_workflow2' => 'Test workflow 2',
    ]);
    $config->set('account.resources.filter', [
      'test_filter' => 'Test filter',
      'test_filter2' => 'Test filter 2',
      'test_filter3' => 'Test filter 3',
    ]);
    $config->save();
  }

}
