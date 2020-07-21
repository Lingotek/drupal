<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\lingotek\Lingotek;
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
  protected function setUp(): void {
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
  public function testNodeTranslationMessageWhenBundleNotConfiguredWithLinks() {
    $this->markTestSkipped('This doesn\'t apply if we replace the management pages with views.');
  }

  /**
   * {@inheritdoc}
   */
  protected function assertSelectionIsKept(string $key) {
    // No valid selection, so permission denied message.
    $this->assertText('You are not authorized to access this page.');
  }

  /**
   * Overwritten, so untracked can be as not shown.
   * Assert that a content source has the given status.
   *
   * @param string $language
   *   The target language.
   * @param string $status
   *   The status.
   */
  protected function assertSourceStatus($language, $status) {
    if ($status === Lingotek::STATUS_UNTRACKED) {
      $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and ./a[contains(text(), '" . strtoupper($language) . "')]]");
      // If not found, maybe it didn't have a link.
      if (count($status_target) === 1) {
        $this->assertEqual(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
      }
      else {
        $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
        if (count($status_target) === 1) {
          $this->assertEqual(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
        }
        else {
          $status_target = $this->xpath("//span[contains(@class,'language-icon')]");
          $this->assertEqual(count($status_target), 0, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
        }
      }
    }
    else {
      $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and ./a[contains(text(), '" . strtoupper($language) . "')]]");
      // If not found, maybe it didn't have a link.
      if (count($status_target) === 1) {
        $this->assertEqual(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
      }
      else {
        $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
        $this->assertEqual(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
      }
    }
  }

}
