<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\Tests\lingotek\Functional\LingotekNodeBulkDisassociateTest;

/**
 * Tests disassociating a node using the bulk management view.
 *
 * @group lingotek
 */
class LingotekNodeBulkViewsDisassociateTest extends LingotekNodeBulkDisassociateTest {

  use LingotekViewsTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    \Drupal::getContainer()
      ->get('module_installer')
      ->install(['lingotek_views_test'], TRUE);
  }

}
