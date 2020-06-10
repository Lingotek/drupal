<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\Tests\lingotek\Functional\LingotekNodeExistingBulkTranslationTest;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeExistingBulkViewsTranslationTest extends LingotekNodeExistingBulkTranslationTest {

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

  public function testNodeIsUntracked() {
    $this->markTestSkipped('This doesn\'t apply as we cannot show the untracked status if there is no value. We may want to convert the target statuses to a partially computed field at a later time.');
  }

}
