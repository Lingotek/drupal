<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\FieldStorageDefinition;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedEntityReferencesDetector;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit test for the nested entitiy detector plugin
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedEntityReferencesDetector
 * @group lingotek
 * @preserve GlobalState disabled
 */
class NestedEntityReferencesDetectorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedEntityReferencesDetector
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
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
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
    $this->detector = new NestedEntityReferencesDetector([], 'nested_entity_detector', [], $this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration);
    $this->entityType = $this->createMock(ContentEntityTypeInterface::class);
    $this->entityType->expects($this->any())
      ->method('hasKey')
      ->with('langcode')
      ->willReturn(TRUE);
    $this->entityType->expects($this->any())
      ->method('id')
      ->willReturn('entity_id');
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
    $detector = new NestedEntityReferencesDetector([], 'nested_entity_detector', [], $this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration);
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
    $detector = NestedEntityReferencesDetector::create($container, [], 'nested_entity_detector', []);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithoutNestedEntityReferenceFields() {
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->any())
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
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('bundle');
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->extract($entity, $entities, $related, 1, $visited);

    // Assert the entity is included.
    $this->assertCount(1, $entities);
    $this->assertEquals($entities['entity_id'][1], $entity);

    // Assert nothing is included as related.
    $this->assertEmpty($related);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithLingotekEnabledNestedEntityReferenceField() {
    $this->lingotekConfiguration->expects($this->once())
      ->method('isFieldLingotekEnabled')
      ->with('entity_id', 'bundle', 'entity_reference_field')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('another_entity', 'another_bundle')
      ->willReturn(TRUE);
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('text');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $target_entity_type = $this->createMock(ContentEntityType::class);
    $embedded_entity_reference = $this->createmock(ContentEntityInterface::class);
    $embedded_entity_reference->expects($this->any())
      ->method('referencedEntities')
      ->willReturn([$embedded_entity_reference]);
    $embedded_entity_reference->expects($this->any())
      ->method('bundle')
      ->willReturn('another_bundle');
    $embedded_entity_reference->expects($this->any())
      ->method('id')
      ->willreturn(2);
    $embedded_entity_reference->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $embedded_entity_reference->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $embedded_entity_reference->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('another_entity');

    $nestedEntityReferenceFieldStorageDefinition = $this->createMock(FieldStorageDefinition::class);
    $nestedEntityReferenceFieldStorageDefinition->expects($this->any())
      ->method('getSetting')
      ->with('target_type')
      ->willReturn('another_entity');

    $nestedEntityReferenceFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('entity_reference');
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Nested Reference');
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getFieldStorageDefinition')
      ->willReturn($nestedEntityReferenceFieldStorageDefinition);

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('another_entity')
      ->willReturn($target_entity_type);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('entity_id')
      ->willReturn([
        'title' => $titleFieldDefinition,
        'entity_reference_field' => $nestedEntityReferenceFieldDefinition,
      ]);

    $itemList = $this->createMock(EntityInterface::class);
    $itemList->expects($this->once())
      ->method('referencedEntities')
      ->willReturn([$embedded_entity_reference]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('bundle');
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->any())
      ->method('get')
      ->with('entity_reference_field')
      ->willReturn($itemList);

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->extract($entity, $entities, $related, 2, $visited);

    // Assert the entity is included.
    $this->assertCount(1, $entities);
    $this->assertEquals($entities['entity_id'][1], $entity);

    // Assert the cohesion are included as related.
    $this->assertCount(1, $related);
    $this->assertEquals($related['another_entity'][2], $embedded_entity_reference);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithNonTranslatableNestedEntityReferenceFields() {
    $this->lingotekConfiguration->expects($this->never())
      ->method('isFieldLingotekEnabled')
      ->with('entity_id', 'bundle', 'entity_reference_field')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->never())
      ->method('isEnabled')
      ->with('another_entity', 'another_bundle')
      ->willReturn(TRUE);

    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('text');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $target_entity_type = $this->createMock(ContentEntityType::class);
    $embedded_entity_reference = $this->createmock(ContentEntityInterface::class);
    $embedded_entity_reference->expects($this->any())
      ->method('referencedEntities')
      ->willReturn([$embedded_entity_reference]);
    $embedded_entity_reference->expects($this->any())
      ->method('bundle')
      ->willReturn('another_bundle');
    $embedded_entity_reference->expects($this->any())
      ->method('id')
      ->willreturn(2);
    $embedded_entity_reference->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(FALSE);
    $embedded_entity_reference->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('another_entity');
    $embedded_entity_reference->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();

    $nestedEntityReferenceFieldStorageDefinition = $this->createMock(FieldStorageDefinition::class);
    $nestedEntityReferenceFieldStorageDefinition->expects($this->any())
      ->method('getSetting')
      ->with('target_type')
      ->willReturn('another_entity');

    $nestedEntityReferenceFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('entity_reference');
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Nested Reference');
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getFieldStorageDefinition')
      ->willReturn($nestedEntityReferenceFieldStorageDefinition);

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('another_entity')
      ->willReturn($target_entity_type);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn([
        'title' => $titleFieldDefinition,
        'entity_reference_field' => $nestedEntityReferenceFieldDefinition,
      ]);

    $itemList = $this->createMock(EntityInterface::class);
    $itemList->expects($this->once())
      ->method('referencedEntities')
      ->willReturn([$embedded_entity_reference]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('bundle');
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->any())
      ->method('get')
      ->with('entity_reference_field')
      ->willReturn($itemList);

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->extract($entity, $entities, $related, 2, $visited);
    // Assert the entity is included, but not the non-translatable entity reference elements.
    $this->assertCount(1, $entities);
    $this->assertEquals($entities['entity_id'][1], $entity);

    // Assert the entity references are not included in the list.
    $this->assertCount(0, $related);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithLingotekDisabledNestedEntityReferenceFields() {
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('another_entity', 'another_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isFieldLingotekEnabled')
      ->with('entity_id', 'bundle', 'entity_reference_field')
      ->willReturn(FALSE);

    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('text');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $target_entity_type = $this->createMock(ContentEntityType::class);
    $embedded_entity_reference_revisions = $this->createmock(ContentEntityInterface::class);
    $embedded_entity_reference_revisions->expects($this->any())
      ->method('referencedEntities')
      ->willReturn([$embedded_entity_reference_revisions]);
    $embedded_entity_reference_revisions->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('another_entity');
    $embedded_entity_reference_revisions->expects($this->any())
      ->method('bundle')
      ->willReturn('another_bundle');
    $embedded_entity_reference_revisions->expects($this->any())
      ->method('id')
      ->willReturn(2);
    $embedded_entity_reference_revisions->expects($this->any())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $embedded_entity_reference_revisions->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();

    $nestedEntityReferenceFieldStorageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $nestedEntityReferenceFieldStorageDefinition->expects($this->any())
      ->method('getSetting')
      ->with('target_type')
      ->willReturn('another_entity');

    $nestedEntityReferenceFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('entity_reference');
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Nested Reference');
    $nestedEntityReferenceFieldDefinition->expects($this->any())
      ->method('getFieldStorageDefinition')
      ->willReturn($nestedEntityReferenceFieldStorageDefinition);

    $this->entityTypeManager->expects($this->exactly(1))
      ->method('getDefinition')
      ->with('another_entity')
      ->willReturn($target_entity_type);

    $this->entityFieldManager->expects($this->exactly(2))
      ->method('getFieldDefinitions')
      ->withConsecutive(['entity_id'], ['another_entity'])
      ->willReturnOnConsecutiveCalls([
        'title' => $titleFieldDefinition,
        'entity_reference_field' => $nestedEntityReferenceFieldDefinition,
      ], []);

    $itemList = $this->createMock(EntityInterface::class);
    $itemList->expects($this->once())
      ->method('referencedEntities')
      ->willReturn([$embedded_entity_reference_revisions]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('bundle');
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->any())
      ->method('get')
      ->with('entity_reference_field')
      ->willReturn($itemList);

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->extract($entity, $entities, $related, 2, $visited);

    // Assert the entity is included, but not the non-translatable entity references.
    $this->assertCount(2, $entities);
    $this->assertEquals($entities['entity_id'][1], $entity);
    $this->assertEquals($entities['another_entity'][2], $embedded_entity_reference_revisions);

    // Assert the entity references are not included as related.
    $this->assertCount(0, $related);
  }

}
