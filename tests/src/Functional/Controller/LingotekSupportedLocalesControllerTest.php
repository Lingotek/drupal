<?php

namespace Drupal\Tests\lingotek\Functional\Controller;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the supported locales controller.
 *
 * @group lingotek
 */
class LingotekSupportedLocalesControllerTest extends LingotekTestBase {

  /**
   * Tests that the supported locales are rendered.
   */
  public function testSupportedLocales() {
    $this->drupalGet('/admin/lingotek/supported-locales');
    $this->assertText('German (Austria)');
    $this->assertText('German (Germany)');
    $this->assertText('Spanish (Spain)');
  }

}
