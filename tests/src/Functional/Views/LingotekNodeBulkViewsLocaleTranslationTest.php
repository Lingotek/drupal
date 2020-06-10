<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\Tests\lingotek\Functional\LingotekNodeBulkLocaleTranslationTest;

/**
 * Tests translating a node into locales using the bulk management views.
 *
 * @group lingotek
 */
class LingotekNodeBulkViewsLocaleTranslationTest extends LingotekNodeBulkLocaleTranslationTest {

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
