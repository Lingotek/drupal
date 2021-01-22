<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\Plugin\RelatedEntitiesDetector\FieldLinkDetector;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit test for the html links entity detector plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\RelatedEntitiesDetector\FieldLinkDetector
 * @group lingotek
 * @preserve GlobalState disabled
 */
class FieldLinkDetectorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\RelatedEntitiesDetector\FieldLinkDetector
   */
  protected $detector;

  /**
   * The mocked module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked entity field manager
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityFieldManager;

  /**
   * The lingotek configuartion service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekConfiguration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);

    $this->detector = new FieldLinkDetector([], 'field_link_detector', [], $this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration);
    $this->entityType = $this->createMock(ContentEntityTypeInterface::class);
    $this->entityType->expects($this->any())
      ->method('hasKey')
      ->with('langcode')
      ->willReturn(TRUE);
    $this->entityType->expects($this->any())
      ->method('id')
      ->willReturn('bundle_id');
    $this->entityType->expects($this->any())
      ->method('getBundleEntityType')
      ->willReturn('entity_id');
    $this->entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('Entity');
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $detector = new FieldLinkDetector([], 'field_link_detector', [], $this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(3))
      ->method('get')
      ->withConsecutive(['entity_type.manager'], ['entity_field.manager'], ['lingotek.configuration'])
      ->willReturnOnConsecutiveCalls($this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration);
    $detector = FieldLinkDetector::create($container, [], 'field_link_detector', []);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithoutLinkFields() {
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn('text');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn([$titleFieldDefinition]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->getBundleEntityType());
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('uuid')
      ->willReturn('this-is-my-uuid');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn($this->entityType->id());
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $entities = [];
    $related = [];
    $visited = [];
    $this->assertEmpty($entities);
    $this->detector->extract($entity, $entities, $related, 1, $visited);
    $this->assertCount(1, $entities);
    $this->assertCount(1, $entities['entity_id']);
    $this->assertEquals($entities['entity_id'][1], $entity);
  }

  /**
   * @covers ::extract
   */
  public function testRunExtract() {
    $this->lingotekConfiguration->expects($this->exactly(3))
      ->method('isEnabled')
      ->withConsecutive(['the_first_type', 'first_bundle'], ['entity_id', 'second_bundle'], ['entity_id', 'third_bundle'])
      ->willReturnOnConsecutiveCalls(TRUE, TRUE, FALSE);
    $linkFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $linkFieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn('link');
    $linkFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Link Field');

    $target_entity_type = $this->createMock(ContentEntityType::class);
    $this->entityTypeManager->expects($this->exactly(3))
      ->method('getDefinition')
      ->withConsecutive(['the_first_type'], ['entity_id'])
      ->willReturn($target_entity_type);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn(['field_link' => $linkFieldDefinition]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->getBundleEntityType());
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('uuid')
      ->willReturn('this-is-my-uuid');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn($this->entityType->id());
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $firstEntity = $this->createMock(ContentEntityInterface::class);
    $firstEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('the_first_type');
    $firstEntity->expects($this->any())
      ->method('id')
      ->willReturn(8);
    $firstEntity->expects($this->any())
      ->method('uuid')
      ->willReturn('the-first-entity-uuid');
    $firstEntity->expects($this->any())
      ->method('bundle')
      ->willReturn('first_bundle');
    $firstEntity->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $firstEntity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $secondEntity = $this->createMock(ContentEntityInterface::class);
    $secondEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('entity_id');
    $secondEntity->expects($this->any())
      ->method('id')
      ->willReturn(2);
    $secondEntity->expects($this->any())
      ->method('uuid')
      ->willReturn('the-second-entity-uuid');
    $secondEntity->expects($this->any())
      ->method('bundle')
      ->willReturn('second_bundle');
    $secondEntity->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $secondEntity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $thirdEntity = $this->createMock(ContentEntityInterface::class);
    $thirdEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('entity_id');
    $thirdEntity->expects($this->any())
      ->method('id')
      ->willReturn(5);
    $thirdEntity->expects($this->any())
      ->method('uuid')
      ->willReturn('the-third-entity-uuid');
    $thirdEntity->expects($this->any())
      ->method('bundle')
      ->willReturn('third_bundle');
    $thirdEntity->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $thirdEntity->expects($this->never())
      ->method('getUntranslated')
      ->willReturnSelf();

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->exactly(6))
      ->method('load')
      ->withConsecutive([8], [8], [2], [2], [5], [5])
      ->willReturnOnConsecutiveCalls($firstEntity, $firstEntity, $secondEntity, $secondEntity, $thirdEntity, $thirdEntity);

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($entityStorage);

    $linkItem = $this->createMock(LinkItemInterface::class);
    $linkItem->expects($this->exactly(4))
      ->method('getUrl')
      ->willReturnOnConsecutiveCalls(
        Url::fromRoute("entity.the_first_type.canonical", ["the_first_type" => 8]),
        Url::fromUri('http://example.com'),
        Url::fromRoute("entity.entity_id.canonical", ["entity_id" => 2]),
        Url::fromRoute("entity.entity_id.canonical", ["entity_id" => 5])
      );

    $data = [
      $linkItem,
      $linkItem,
      $linkItem,
      $linkItem,
    ];

    $entity->expects($this->once())
      ->method('get')
      ->with('field_link')
      ->willReturn($data);

    $entities = [];
    $related = [];
    $visited = [];
    $this->assertEmpty($entities);
    $this->detector->extract($entity, $entities, $related, 1, $visited);
    // Entities from 2 different entity types.
    $this->assertCount(2, $entities);
    // Total of three entities.
    $this->assertCount(2, $entities['entity_id']);
    $this->assertCount(1, $entities['the_first_type']);
    $this->assertEquals($entities['entity_id'][1], $entity);
    $this->assertEquals($entities['entity_id'][2], $secondEntity);
    $this->assertEquals($entities['the_first_type'][8], $firstEntity);
  }

}
