<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\BrowserTestBase;

/**
 * Class for testing connecting to Lingotek.
 *
 * @group lingotek
 */
class LingotekConnectTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests connecting to Lingotek.
   */
  public function testConnectToLingotek() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->assertText('The configuration options have been saved.');

    // Assert there are options for workflows.
    $this->assertFieldByName('workflow');
    $option_field = $assert_session->optionExists('edit-workflow', '- Select -');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-workflow', 'test_workflow');
    $assert_session->optionExists('edit-workflow', 'test_workflow2');

    // Assert there are options for filters.
    $this->assertFieldByName('filter');
    $option_field = $assert_session->optionExists('edit-filter', 'drupal_default');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-filter', 'project_default');
    $assert_session->optionExists('edit-filter', 'test_filter');
    $assert_session->optionExists('edit-filter', 'test_filter2');
    $assert_session->optionExists('edit-filter', 'test_filter3');

    $this->assertFieldByName('subfilter');
    $option_field = $assert_session->optionExists('edit-subfilter', 'drupal_default');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-subfilter', 'project_default');
    $assert_session->optionExists('edit-subfilter', 'test_filter');
    $assert_session->optionExists('edit-subfilter', 'test_filter2');
    $assert_session->optionExists('edit-subfilter', 'test_filter3');

    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
      'filter' => 'drupal_default',
      'subfilter' => 'drupal_default',
    ], 'Save configuration');
    $this->assertText('The configuration options have been saved.');
  }

  /**
   * Tests connecting to Lingotek.
   */
  public function testConnectToLingotekWithoutFilters() {
    $assert_session = $this->assertSession();
    \Drupal::state()->set('lingotek.no_filters', TRUE);

    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->assertText('The configuration options have been saved.');

    // Assert there are options for workflows.
    $this->assertFieldByName('workflow');
    $option_field = $assert_session->optionExists('edit-workflow', '- Select -');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-workflow', 'test_workflow');
    $assert_session->optionExists('edit-workflow', 'test_workflow2');

    // Assert there are no options for filters and no select.
    $this->assertNoFieldByName('filter');
    $this->assertNoFieldByName('subfilter');

    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'workflow' => 'test_workflow',
      'vault' => 'test_vault',
    ], 'Save configuration');
    $this->assertText('The configuration options have been saved.');
  }

}
