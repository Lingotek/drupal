<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Tests config overrides in settings.php are possible .
 *
 * @group lingotek
 */
class LingotekConfigOverridesTest extends LingotekTestBase {

  public function testDefaultCommunityOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['community'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_community', $defaults['community'], 'Default community could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['community'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['community'], 'Default community could be overridden by settings.php');
  }

  public function testDefaultProjectOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['project'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_project', $defaults['project'], 'Default project could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['project'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['project'], 'Default project could be overridden by settings.php');
  }

  public function testDefaultWorkflowOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['workflow'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_workflow', $defaults['workflow'], 'Default workflow could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['workflow'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['workflow'], 'Default workflow could be overridden by settings.php');
  }

  public function testDefaultVaultOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['vault'] = 'abc';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('test_vault', $defaults['vault'], 'Default vault could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $GLOBALS['config']['lingotek.settings']['default']['vault'] = 'def';
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('def', $defaults['vault'], 'Default vault could be overridden by settings.php');
  }

  public function testDefaultFilterOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['filter'] = 'project_default';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('drupal_default', $defaults['filter'], 'Default filter could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('project_default', $defaults['filter'], 'Default filter could be overridden by settings.php');
  }

  public function testDefaultSubfilterOverride() {
    $GLOBALS['config']['lingotek.settings']['default']['subfilter'] = 'project_default';

    // Container was not rebuilt yet.
    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('drupal_default', $defaults['subfilter'], 'Default subfilter could be overridden by settings.php');

    // Editing settings.php forces us to rebuild the container.
    $this->rebuildContainer();

    $defaults = \Drupal::service('lingotek')->getDefaults();
    $this->assertIdentical('project_default', $defaults['subfilter'], 'Default subfilter could be overridden by settings.php');
  }

}
