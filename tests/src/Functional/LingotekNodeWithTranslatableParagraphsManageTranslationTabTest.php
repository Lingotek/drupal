<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Tests translating a node with translatable paragraphs using the bulk management form.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeWithTranslatableParagraphsManageTranslationTabTest extends LingotekNodeWithParagraphsManageTranslationTabTest {

  protected $paragraphsTranslatable = TRUE;

}
