<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\lingotek_test\Controller\FakeAuthorizationController;

/**
 * Tests config overrides in settings.php are possible .
 *
 * @group lingotek
 */
class LingotekConfigOverridesTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupResources();
  }

  public function testDefaultCommunityOverride() {
    $GLOBALS['config']['lingotek.account']['default']['community'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_community', $defaults['community'], 'Default community could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.account']['default']['community'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['community'], 'Default community could be overridden by settings.php');
  }

  public function testDefaultProjectOverride() {
    $GLOBALS['config']['lingotek.account']['default']['project'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_project', $defaults['project'], 'Default project could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.account']['default']['project'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['project'], 'Default project could be overridden by settings.php');
  }

  public function testDefaultWorkflowOverride() {
    $GLOBALS['config']['lingotek.account']['default']['workflow'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_workflow', $defaults['workflow'], 'Default workflow could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.account']['default']['workflow'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['workflow'], 'Default workflow could be overridden by settings.php');
  }

  public function testDefaultVaultOverride() {
    $GLOBALS['config']['lingotek.account']['default']['vault'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_vault', $defaults['vault'], 'Default vault could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.account']['default']['vault'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['vault'], 'Default vault could be overridden by settings.php');
  }

  public function testDefaultFilterOverride() {
    $GLOBALS['config']['lingotek.account']['default']['filter'] = 'project_default';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('drupal_default', $defaults['filter'], 'Default filter could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('project_default', $defaults['filter'], 'Default filter could be overridden by settings.php');
  }

  public function testDefaultSubfilterOverride() {
    $GLOBALS['config']['lingotek.account']['default']['subfilter'] = 'project_default';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('drupal_default', $defaults['subfilter'], 'Default subfilter could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('project_default', $defaults['subfilter'], 'Default subfilter could be overridden by settings.php');
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
