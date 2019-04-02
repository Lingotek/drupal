<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\Tests\lingotek\Functional\LingotekNodeBulkTranslationTest;

/**
 * Tests translating a node using the bulk management view.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeBulkViewsTranslationTest extends LingotekNodeBulkTranslationTest {

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

  /**
   * {@inheritdoc}
   */
  public function testAddContentLinkPresent() {
    $this->markTestSkipped('This doesn\'t apply if we replace the management pages with views. Or if you do, it is your decision to add the content creation link.');
  }

  /**
   * {@inheritdoc}
   */
  protected function assertSelectionIsKept(string $key) {
    // No valid selection, so permission denied message.
    $this->assertText('You are not authorized to access this page.');
  }

}
