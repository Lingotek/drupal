<?php

namespace Drupal\lingotek\Tests\Form;

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
  protected function setUp() {
    parent::setUp();

    $this->setupResources();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test the table shows the right values.
   */
  public function testTableValues() {
    $this->drupalGet('admin/lingotek/settings');

    $this->assertTableValue('status', 'Active');
    $this->assertTableValue('plan', 'No');
    $this->assertTableValue('activation', 'testUser@example.com');
    $this->assertTableValue('token', 'test_token');
    $this->assertTableValue('community', 'Test Community (test_community)');
    $this->assertTableValue('workflow', 'test_workflow (test_workflow)');
    $this->assertTableValue('project', 'test_project (test_project)');
    $this->assertTableValue('vault', 'test_vault (test_vault)');
    $this->assertTableValue('filter', 'test_filter (test_filter)');
    $this->assertTableValue('subfilter', 'test_filter (test_filter)');
    $this->assertTableValue('tms', 'https://myaccount.lingotek.com');
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
    $this->assertTableValue('token', 'test_token');
    $this->assertTableValue('community', 'Test Community 2 (test_community2)');
    $this->assertTableValue('workflow', 'test_workflow 2 (test_workflow2)');
    $this->assertTableValue('project', 'test_project 2 (test_project2)');
    $this->assertTableValue('vault', 'test_vault 2 (test_vault2)');
    $this->assertTableValue('filter', 'test_filter 2 (test_filter2)');
    $this->assertTableValue('subfilter', 'test_filter 3 (test_filter3)');
    $this->assertTableValue('tms', 'https://myaccount.lingotek.com');
    $this->assertTableValue('gmc', 'https://gmc.lingotek.com');
  }

  /**
   * Test the table shows the right values.
   */
  public function testTableValuesWithDefaultFilters() {
    $this->drupalGet('admin/lingotek/settings');

    $this->assertTableValue('filter', 'Project Default (drupal_default)');
    $this->assertTableValue('subfilter', 'Project Default (drupal_default)');
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
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertTableValue($field, $expected, $message = '', $group = 'Other') {
    $xpathValue = $this->xpath('//tr[@data-drupal-selector="edit-account-table-' . $field . '-row"]//td[2]/text()');
    $value = $xpathValue[0]->getHtml();
    return $this->assertEqual($expected, $value, $message, $group);
  }

  /**
   * Setup test resources for the test.
   */
  protected function setupResources() {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('account.resources.community', [
      'test_community' => 'Test Community',
      'test_community2' => 'Test Community 2',
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
