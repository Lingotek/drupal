<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\FieldStorageDefinition;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedEntityReferencesDetector;
use Drupal\Tests\UnitTestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit test for the nested entitiy detector plugin
 *
 * @covers DefaultClass \Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedEntitiesDetector
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

  public function testConstruct() {
    $detector = new NestedEntityReferencesDetector([], 'nested_entity_detector', [], $this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration);
    $this->assertNotNull($detector);
  }

  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(3))
      ->method('get')
      ->withConsecutive(['entity_type.manager'], ['entity_field.manager'], ['lingotek.configuration'])
      ->willReturnOnConsecutiveCalls($this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration);
    $detector = NestedEntityReferencesDetector::create($container, [], 'nested_entity_detector', []);
    $this->assertNotNull($detector);
  }

  public function testRunWithoutNestedEntityReferenceFields() {
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
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
      ->method('bundle')
      ->willReturn($this->entityType->id());

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->run($entity, $entities, $related, 1, $visited);
    $this->assertNotEmpty($entities);
  }

  public function testRunWithLingotekEnabledNestedEntityReferenceField() {
    $this->lingotekConfiguration->expects($this->once())
      ->method('isFieldLingotekEnabled')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->willReturn(TRUE);
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
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
      ->willReturn($this->entityType->id());
    $embedded_entity_reference->expects($this->any())
      ->method('id')
      ->willreturn(2);
    $embedded_entity_reference->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $embedded_entity_reference->expects($this->any())
      ->method('getUntranslated')
      ->willReturn('embbedded entity reference content');
    $embedded_entity_reference->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->id());

    $nestedEntityReferenceFieldStorageDefinition = $this->createMock(FieldStorageDefinition::class);
    $nestedEntityReferenceFieldStorageDefinition->expects($this->any())
      ->method('getSetting')
      ->willReturn('target_type');

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
      ->willReturn($target_entity_type);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn([
        'title' => $titleFieldDefinition,
        'entity_reference' => $nestedEntityReferenceFieldDefinition,
      ]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturn(['title' => 'entity content']);
    $entity->entity_reference = $embedded_entity_reference;

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->run($entity, $entities, $related, 2, $visited);
    $this->assertNotEmpty($entities);
    $this->assertNotEmpty($related);
  }

  public function testRunWithNonTranslatableNestedEntityReferenceFields() {
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
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
      ->willReturn($this->entityType->id());
    $embedded_entity_reference->expects($this->any())
      ->method('id')
      ->willreturn(2);
    $embedded_entity_reference->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(FALSE);
    $embedded_entity_reference->expects($this->any())
      ->method('getUntranslated')
      ->willReturn('embbedded entity reference content');
    $embedded_entity_reference->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->id());

    $nestedEntityReferenceFieldStorageDefinition = $this->createMock(FieldStorageDefinition::class);
    $nestedEntityReferenceFieldStorageDefinition->expects($this->any())
      ->method('getSetting')
      ->willReturn('target_type');

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
      ->willReturn($target_entity_type);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn([
        'title' => $titleFieldDefinition,
        'entity_reference' => $nestedEntityReferenceFieldDefinition,
      ]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn($this->entityType->id());
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturn(['title' => 'entity content']);
    $entity->entity_reference = $embedded_entity_reference;

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->run($entity, $entities, $related, 2, $visited);
    $this->assertNotEmpty($entities);
    $this->assertLessThan(2, count($entities));
  }

}
