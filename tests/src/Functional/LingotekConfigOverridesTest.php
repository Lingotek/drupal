<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Tests config overrides in settings.php are possible .
 *
 * @group lingotek
 * @group legacy
 */
class LingotekConfigOverridesTest extends LingotekTestBase {

  public function testDefaultCommunityOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['community'] = 'abc';

    // Container was not rebuilt yet.
    $community = \Drupal::service('lingotek')->get('default.community');
    $this->assertIdentical('test_community', $community, 'Default community could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['community'] = 'def';
    $this->rebuildContainer();

    $community = \Drupal::service('lingotek')->get('default.community');
    $this->assertIdentical('def', $community, 'Default community could be overridden by settings.php');
  }

  public function testDefaultProjectOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['project'] = 'abc';

    // Container was not rebuilt yet.
    $project = \Drupal::service('lingotek')->get('default.project');
    $this->assertIdentical('test_project', $project, 'Default project could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['project'] = 'def';
    $this->rebuildContainer();

    $project = \Drupal::service('lingotek')->get('default.project');
    $this->assertIdentical('def', $project, 'Default project could be overridden by settings.php');
  }

  public function testDefaultWorkflowOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['workflow'] = 'abc';

    // Container was not rebuilt yet.
    $workflow = \Drupal::service('lingotek')->get('default.workflow');
    $this->assertIdentical('test_workflow', $workflow, 'Default workflow could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['workflow'] = 'def';
    $this->rebuildContainer();

    $workflow = \Drupal::service('lingotek')->get('default.workflow');
    $this->assertIdentical('def', $workflow, 'Default workflow could be overridden by settings.php');
  }

  public function testDefaultVaultOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['vault'] = 'abc';

    // Container was not rebuilt yet.
    $vault = \Drupal::service('lingotek')->get('default.vault');
    $this->assertIdentical('test_vault', $vault, 'Default vault could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['vault'] = 'def';
    $this->rebuildContainer();

    $vault = \Drupal::service('lingotek')->get('default.vault');
    $this->assertIdentical('def', $vault, 'Default vault could be overridden by settings.php');
  }

  public function testDefaultFilterOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['filter'] = 'project_default';

    // Container was not rebuilt yet.
    $filter = \Drupal::service('lingotek')->get('default.filter');
    $this->assertIdentical('drupal_default', $filter, 'Default filter could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $this->rebuildContainer();

    $filter = \Drupal::service('lingotek')->get('default.filter');
    $this->assertIdentical('project_default', $filter, 'Default filter could be overridden by settings.php');
  }

  public function testDefaultSubfilterOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['subfilter'] = 'project_default';

    // Container was not rebuilt yet.
    $subfilter = \Drupal::service('lingotek')->get('default.subfilter');
    $this->assertIdentical('drupal_default', $subfilter, 'Default subfilter could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $this->rebuildContainer();

    $subfilter = \Drupal::service('lingotek')->get('default.subfilter');
    $this->assertIdentical('project_default', $subfilter, 'Default subfilter could be overridden by settings.php');
  }

}
