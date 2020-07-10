<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Tests translating a node with multiple locales including translatable nested
 * paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeNestedTranslatableParagraphsTranslationTest extends LingotekNodeNestedParagraphsTranslationTest {

  protected $paragraphsTranslatable = TRUE;

}
