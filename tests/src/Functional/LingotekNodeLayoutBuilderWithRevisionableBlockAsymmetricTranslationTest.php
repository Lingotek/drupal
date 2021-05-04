<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests translating a node with layout builder AT and revisionable blocks.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeLayoutBuilderWithRevisionableBlockAsymmetricTranslationTest extends LingotekNodeLayoutBuilderAsymmetricTranslationTest {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'content_moderation',
    'node',
    'layout_builder',
    'layout_builder_at',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\block_content\Entity\BlockContentType $bundle */
    $bundle = BlockContentType::load('custom_content_block');
    $bundle->set('revision', TRUE);
    $bundle->save();
  }

}
