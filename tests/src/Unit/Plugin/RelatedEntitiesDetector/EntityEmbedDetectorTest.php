<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\Plugin\RelatedEntitiesDetector\EntityEmbedDetector;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit test for the entity_embed entity detector plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\RelatedEntitiesDetector\EntityEmbedDetector
 * @group lingotek
 * @preserve GlobalState disabled
 */
class EntityEmbedDetectorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\RelatedEntitiesDetector\EntityEmbedDetector
   */
  protected $detector;

  /**
   * The mocked module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The mocked entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityRepository;

  /**
   * The mocked entity field manager
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityFieldManager;

  /**
   * The lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->detector = new EntityEmbedDetector([], 'entity_embed_detector', [], $this->entityRepository, $this->entityFieldManager, $this->lingotekConfiguration);
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
    $detector = new EntityEmbedDetector([], 'entity_embed_detector', [], $this->entityRepository, $this->entityFieldManager, $this->lingotekConfiguration);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(3))
      ->method('get')
      ->withConsecutive(['entity.repository'], ['entity_field.manager'], ['lingotek.configuration'])
      ->willReturnOnConsecutiveCalls($this->entityRepository, $this->entityFieldManager, $this->lingotekConfiguration);
    $detector = EntityEmbedDetector::create($container, [], 'entity_embed_detector', []);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithoutTextFields() {
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn('entity_reference');
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
   * @dataProvider dataProviderFieldTypes
   */
  public function testRunExtract($fieldType, $hasSummary) {
    $this->lingotekConfiguration->expects($this->exactly(3))
      ->method('isEnabled')
      ->withConsecutive(['the_first_type', 'first_bundle'], ['entity_id', 'second_bundle'], ['third_entity_type', 'third_bundle'])
      ->willReturnOnConsecutiveCalls(TRUE, TRUE, FALSE);
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn($fieldType);
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn(['title' => $titleFieldDefinition]);

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
    $firstEntity->expects($this->exactly(2))
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
    $secondEntity->expects($this->exactly(2))
      ->method('getUntranslated')
      ->willReturnSelf();

    $thirdEntity = $this->createMock(ContentEntityInterface::class);
    $thirdEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('third_entity_type');
    $thirdEntity->expects($this->any())
      ->method('id')
      ->willReturn(2);
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

    $this->entityRepository->expects($this->exactly(3))
      ->method('loadEntityByUuid')
      ->withConsecutive(['the_first_type', 'the-first-entity-uuid'], ['entity_id', 'the-second-entity-uuid'], ['third_entity_type', 'the-third-entity-uuid'])
      ->willReturnOnConsecutiveCalls($firstEntity, $secondEntity, $thirdEntity);

    $data = [
      (object) [
        'value' => '<p>This is a text with an entity embed <drupal-entity data-embed-button="node" data-entity-embed-display="view_mode:node.card"  data-entity-type="the_first_type" data-entity-uuid="the-first-entity-uuid" data-langcode="en"></drupal-entity> </p>',
      ],
      (object) [
        'value' => '<p>This is a text with an entity embed <drupal-entity data-embed-button="node" data-entity-embed-display="view_mode:node.card"  data-entity-type="entity_id" data-entity-uuid="the-second-entity-uuid" data-langcode="en"></drupal-entity> </p>' .
        '<drupal-entity data-embed-button="node" data-entity-embed-display="view_mode:node.card"  data-entity-type="third_entity_type" data-entity-uuid="the-third-entity-uuid" data-langcode="en"></drupal-entity>',
      ],
    ];
    if ($hasSummary) {
      $data = [
        (object) [
          'value' => 'No link',
          'summary' => '<p>This is a text with an entity embed <drupal-entity data-embed-button="node" data-entity-embed-display="view_mode:node.card"  data-entity-type="the_first_type" data-entity-uuid="the-first-entity-uuid" data-langcode="en"></drupal-entity> </p>',
        ],
        (object) [
          'value' => '<p>This is a text with an entity embed <drupal-entity data-embed-button="node" data-entity-embed-display="view_mode:node.card"  data-entity-type="entity_id" data-entity-uuid="the-second-entity-uuid" data-langcode="en"></drupal-entity> </p>',
          'summary' => '<drupal-entity data-embed-button="node" data-entity-embed-display="view_mode:node.card"  data-entity-type="third_entity_type" data-entity-uuid="the-third-entity-uuid" data-langcode="en"></drupal-entity>',
        ],
      ];
    }
    $entity->expects($this->once())
      ->method('get')
      ->with('title')
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

  /**
   * Data provider for testRunExtract.
   *
   * @return array
   *   [field_type, hasSummary]
   */
  public function dataProviderFieldTypes() {
    yield ['text', FALSE];
    yield ['text_long', FALSE];
    yield ['text_with_summary', TRUE];
  }

}
