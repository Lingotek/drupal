<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Tests translating a node with multiple locales including translatable nested
 * paragraphs configured with the asymmetric widget.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeNestedTranslatableParagraphsAsymmetricTranslationTest extends LingotekNodeNestedTranslatableParagraphsTranslationTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['paragraphs_asymmetric_translation_widgets'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setFormDisplaysToAsymmetric();
  }

  protected function setFormDisplaysToAsymmetric(): void {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface[] $formDisplays */
    $formDisplays = EntityFormDisplay::loadMultiple();
    foreach ($formDisplays as $formDisplay) {
      $components = $formDisplay->getComponents();
      $toSave = FALSE;
      foreach ($components as $id => $component) {
        if (isset($component['type']) && $component['type'] === 'entity_reference_paragraphs') {
          $component['type'] = 'paragraphs_classic_asymmetric';
          $formDisplay->setComponent($id, $component);
          $toSave = TRUE;
        }
      }
      if ($toSave) {
        $formDisplay->save();
      }
    }
  }

}
