<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\Tests\lingotek\Functional\LingotekNodeBulkTranslationTest;

/**
 * Tests translating a node using the bulk management view.
 *
 * @group lingotek
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
  public function testNodeTranslationUsingActions() {
    $this->markTestSkipped('This cannot be executed until we have actions for individual targets.');
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
  public function testRequestTranslationWithActionWithAnError() {
    $this->markTestSkipped('This cannot be executed until we have actions for individual targets.');
  }

  /**
   * {@inheritdoc}
   */
  public function testCheckTranslationStatusWithActionWithAnError() {
    $this->markTestSkipped('This cannot be executed until we have actions for individual targets.');
  }

  /**
   * {@inheritdoc}
   */
  public function testDownloadTranslationWithActionWithAnError() {
    $this->markTestSkipped('This cannot be executed until we have actions for individual targets.');
  }

}
