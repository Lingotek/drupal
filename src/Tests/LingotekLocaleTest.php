<?php

namespace Drupal\lingotek\Tests;

use Drupal\lingotek\LingotekLocale;
use Drupal\simpletest\WebTestBase;

/**
 * Tests LingotekLocale.
 *
 * @ToDo: This should be unit tests!
 *
 * @group lingotek
 */
class LingotekLocaleTest extends WebTestBase {

  public function testConvertDrupal2Lingotek() {
    // ToDo: Improve testing coverage.
    $this->assertIdentical('zh-hans', LingotekLocale::convertLingotek2Drupal('zh_CN'));
  }

  public function testConvertLingotek2Drupal() {
    // ToDo: Improve testing coverage.
    $this->assertIdentical('zh_CN', LingotekLocale::convertDrupal2Lingotek('zh-hans'));
  }

}
