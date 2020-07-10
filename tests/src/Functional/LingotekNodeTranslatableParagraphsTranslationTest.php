<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Tests translating a node with multiple locales including translatable paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeTranslatableParagraphsTranslationTest extends LingotekNodeParagraphsTranslationTest {

  protected $paragraphsTranslatable = TRUE;

}
