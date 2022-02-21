<?php

namespace Drupal\Tests\lingotek\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\lingotek\LingotekContentTranslationEntityRevisionResolver;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\lingotek\LingotekContentTranslationEntityRevisionResolver
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekContentTranslationEntityRevisionResolverTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationEntityRevisionResolver
   */
  protected $resolver;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $this->resolver = new LingotekContentTranslationEntityRevisionResolver($this->entityTypeManager);
  }

  /**
   * @covers ::resolve
   * @dataProvider dataProviderResolveModes
   */
  public function testResolve($resolveMode, $revision_id) {
    $storage = $this->createMock(TranslatableRevisionableStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('entity_type_id')
      ->willReturn($storage);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('en');

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('entity_type_id');
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->any())
      ->method('language')
      ->willReturn($language);
    $entity->expects($this->any())
      ->method('getRevisionId')
      ->willReturn(1);

    $latestRevision = $this->createMock(ContentEntityInterface::class);
    $storage->expects($this->any())
      ->method('getLatestTranslationAffectedRevisionId')
      ->willReturn(15);
    $storage->expects($this->any())
      ->method('loadRevision')
      ->with(15)
      ->willReturn($latestRevision);
    $latestRevision->expects($this->any())
      ->method('getRevisionId')
      ->willReturn(15);

    $result = $this->resolver->resolve($entity, $resolveMode);
    $this->assertSame($revision_id, $result->getRevisionId());
  }

  public function dataProviderResolveModes() {
    yield 'same' => [LingotekContentTranslationEntityRevisionResolver::RESOLVE_SAME, 1];
    yield 'latest_revision_affected' => [LingotekContentTranslationEntityRevisionResolver::RESOLVE_LATEST_TRANSLATION_AFFECTED, 15];
  }

}
