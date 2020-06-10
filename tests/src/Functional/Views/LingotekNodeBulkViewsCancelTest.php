<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\Tests\lingotek\Functional\LingotekNodeBulkCancelTest;

/**
 * Tests cancelling a node using the bulk management view.
 *
 * @group lingotek
 */
class LingotekNodeBulkViewsCancelTest extends LingotekNodeBulkCancelTest {

  use LingotekViewsTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::getContainer()
      ->get('module_installer')
      ->install(['lingotek_views_test'], TRUE);
  }

}
