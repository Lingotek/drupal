<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\lingotek\LingotekLocale;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests LingotekLocale.
 *
 * @ToDo: This should be unit tests!
 *
 * @group lingotek
 */
class LingotekLocaleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testConvertDrupal2Lingotek() {
    // ToDo: Improve testing coverage.
    $this->assertIdentical('zh-hans', LingotekLocale::convertLingotek2Drupal('zh_CN'));
  }

  public function testConvertLingotek2Drupal() {
    // ToDo: Improve testing coverage.
    $this->assertIdentical('zh_CN', LingotekLocale::convertDrupal2Lingotek('zh-hans'));
  }

  public function testGenerateLingotek2Drupal() {
    $language = LingotekLocale::generateLingotek2Drupal('es_ES');
    $this->assertEqual('es', $language);

    $language = LingotekLocale::generateLingotek2Drupal('de-AT');
    $this->assertEqual('de', $language);

    $language = LingotekLocale::generateLingotek2Drupal('ar');
    $this->assertEqual('ar', $language);
  }

}
