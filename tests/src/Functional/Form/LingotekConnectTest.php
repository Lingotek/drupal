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
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['lingotek', 'lingotek_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests connecting to Lingotek.
   */
  public function testConnectToLingotek() {
    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->assertText('The configuration options have been saved.');

    // Assert there are options for filters.
    $this->assertFieldByName('filter');
    $this->assertOptionSelected('edit-filter', 'drupal_default');
    $this->assertOption('edit-filter', 'project_default');
    $this->assertOption('edit-filter', 'drupal_default');
    $this->assertOption('edit-filter', 'test_filter');
    $this->assertOption('edit-filter', 'test_filter2');
    $this->assertOption('edit-filter', 'test_filter3');

    $this->assertFieldByName('subfilter');
    $this->assertOptionSelected('edit-subfilter', 'drupal_default');
    $this->assertOption('edit-subfilter', 'project_default');
    $this->assertOption('edit-subfilter', 'drupal_default');
    $this->assertOption('edit-subfilter', 'test_filter');
    $this->assertOption('edit-subfilter', 'test_filter2');
    $this->assertOption('edit-subfilter', 'test_filter3');

    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault',
      'filter' => 'drupal_default',
      'subfilter' => 'drupal_default',
    ], 'Save configuration');
    $this->assertText('The configuration options have been saved.');
  }

  /**
   * Tests connecting to Lingotek.
   */
  public function testConnectToLingotekWithoutFilters() {
    \Drupal::state()->set('lingotek.no_filters', TRUE);

    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->assertText('The configuration options have been saved.');

    // Assert there are no options for filters and no select.
    $this->assertNoFieldByName('filter');
    $this->assertNoFieldByName('subfilter');

    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault',
    ], 'Save configuration');
    $this->assertText('The configuration options have been saved.');
  }

}
