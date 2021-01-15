<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedLayoutBuilderEntitiesDetector;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit test for the nested layout builder entities detector plugin
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedLayoutBuilderEntitiesDetector
 * @group lingotek
 * @preserve GlobalState disabled
 */
class NestedLayoutBuilderEntitiesDetectorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\RelatedEntitiesDetector\NestedLayoutBuilderEntitiesDetector
   */
  protected $detector;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked entity field manager.
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
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The mocked entity type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->detector = new NestedLayoutBuilderEntitiesDetector([], 'nested_layout_builder_entities_detector', [], $this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration, $this->moduleHandler);
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

    $blockManager = $this->getMockBuilder(BlockManager::class)->disableOriginalConstructor()->getMock();
    $blockManager->expects($this->any())
      ->method('getDefinition')
      ->with('inline_block')
      ->willReturn(['id' => 'inline_block']);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.block', $blockManager);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $detector = new NestedLayoutBuilderEntitiesDetector([], 'nested_layout_builder_entities_detector', [], $this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration, $this->moduleHandler);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(4))
      ->method('get')
      ->withConsecutive(['entity_type.manager'], ['entity_field.manager'], ['lingotek.configuration'], ['module_handler'])
      ->willReturnOnConsecutiveCalls($this->entityTypeManager, $this->entityFieldManager, $this->lingotekConfiguration, $this->moduleHandler);
    $detector = NestedLayoutBuilderEntitiesDetector::create($container, [], 'nested_layout_builder_entities_detector', []);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithoutNestedLayoutBuilderEntities() {
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

    // Assert the entity is included
    $this->assertCount(1, $entities);
    $this->assertEquals($entities['entity_id'][1], $entity);

    // Assert Nothing is included as related.
    $this->assertEmpty($related);
  }

  /**
   * @covers ::extract
   * @dataProvider dataProviderTranslationModules
   */
  public function testRunWithLingotekEnabledNestedLayoutBuilderEntities($module_data, $field_name, $field_type) {
    $this->lingotekConfiguration->expects($this->once())
      ->method('isFieldLingotekEnabled')
      ->with('entity_id', 'bundle', $field_name)
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('block_content', 'basic')
      ->willReturn(TRUE);

    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->withConsecutive(['layout_builder_at'], ['layout_builder_st'])
      ->willReturnOnConsecutiveCalls($module_data['layout_builder_at'], $module_data['layout_builder_st']);

    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('text');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $target_entity_type = $this->createMock(ContentEntityType::class);
    $target_entity_type->expects($this->any())
      ->method('getKey')
      ->with('revision')
      ->willReturn('revision_id');

    $embedded_block = $this->createmock(ContentEntityInterface::class);
    $embedded_block->expects($this->any())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $embedded_block->expects($this->any())
      ->method('bundle')
      ->willReturn('basic');
    $embedded_block->expects($this->any())
      ->method('id')
      ->willReturn(2);
    $embedded_block->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('block_content');
    $embedded_block->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();

    $nestedLayoutFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $nestedLayoutFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn($field_type);
    $nestedLayoutFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Nested Block');

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->any())
      ->method('condition')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('execute')
      ->willReturn([1]);

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);
    $entityStorage->expects($this->any())
      ->method('getEntityType')
      ->willReturn($target_entity_type);
    $entityStorage->expects($this->any())
      ->method('load')
      ->with(1)
      ->willReturn($embedded_block);

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('block_content')
      ->willReturn($entityStorage);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('block_content')
      ->willReturn($target_entity_type);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('entity_id')
      ->willReturn([
        'title' => $titleFieldDefinition,
        $field_name => $nestedLayoutFieldDefinition,
      ]);

    $componentArray = [
      'configuration' => [
        'block_revision_id' => 1,
      ],
    ];

    $component = $this->createMock(SectionComponent::class);
    $component->expects($this->any())
      ->method('getPluginId')
      ->willReturn('inline_block');
    $component->expects($this->any())
      ->method('toArray')
      ->willReturn($componentArray);
    $componentItemList = [
      $component,
    ];

    $sectionObject = $this->createMock(Section::class);
    $sectionObject->expects($this->any())
      ->method('getComponents')
      ->willReturn($componentItemList);

    $sectionItemList = [
      [
        'section' => $sectionObject,
      ],
    ];
    $layoutField = $this->createMock(LayoutSectionItemList::class);
    $layoutField->expects($this->any())
      ->method('getValue')
      ->willReturn($sectionItemList);

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
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->any())
      ->method('get')
      ->with(OverridesSectionStorage::FIELD_NAME)
      ->willReturn($layoutField);

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->extract($entity, $entities, $related, 2, $visited);

    // Assert the entity is included.
    $this->assertCount(1, $entities);
    $this->assertEquals($entities['entity_id'][1], $entity);

    // Assert the layout block is included as related.
    $this->assertCount(1, $related);
    $this->assertEquals($related['block_content'][2], $embedded_block);
  }

  /**
   * @covers ::extract
   * @dataProvider dataProviderTranslationModules
   */
  public function testRunWithNonTranslatableNestedLayoutBuilderEntities($module_data, $field_name, $field_type) {
    $this->lingotekConfiguration->expects($this->never())
      ->method('isFieldLingotekEnabled')
      ->with('entity_id', 'basic', $field_name)
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->never())
      ->method('isEnabled')
      ->with('block_content', 'basic')
      ->willReturn(TRUE);

    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->withConsecutive(['layout_builder_at'], ['layout_builder_st'])
      ->willReturnOnConsecutiveCalls($module_data['layout_builder_at'], $module_data['layout_builder_st']);

    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn('text');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $target_entity_type = $this->createMock(ContentEntityType::class);
    $embedded_block = $this->createMock(ContentEntityInterface::class);
    $embedded_block->expects($this->any())
      ->method('isTranslatable')
      ->willReturn(FALSE);
    $embedded_block->expects($this->any())
      ->method('bundle')
      ->willReturn('basic');
    $embedded_block->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $embedded_block->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('block_content');
    $embedded_block->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();

    $nestedLayoutFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $nestedLayoutFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn($field_type);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->any())
      ->method('condition')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('execute')
      ->willReturn([1]);

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->any())
      ->method('load')
      ->with(1)
      ->willReturn($embedded_block);
    $entityStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);
    $entityStorage->expects($this->any())
      ->method('getEntityType')
      ->willReturn($target_entity_type);

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('block_content')
      ->willReturn($target_entity_type);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('block_content')
      ->willReturn($entityStorage);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn([
        'title' => $titleFieldDefinition,
        $field_name => $nestedLayoutFieldDefinition,
      ]);

    $componentArray = [
      'configuration' => [
        'block_revision_id' => 1,
      ],
    ];

    $component = $this->createMock(SectionComponent::class);
    $component->expects($this->any())
      ->method('getPluginId')
      ->willReturn('inline_block');
    $component->expects($this->any())
      ->method('toArray')
      ->willReturn($componentArray);
    $componentItemList = [
      $component,
    ];

    $sectionObject = $this->createMock(Section::class);
    $sectionObject->expects($this->any())
      ->method('getComponents')
      ->willReturn($componentItemList);

    $sectionItemList = [
      [
        'section' => $sectionObject,
      ],
    ];
    $layoutField = $this->createMock(LayoutSectionItemList::class);
    $layoutField->expects($this->any())
      ->method('getValue')
      ->willReturn($sectionItemList);

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
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->any())
      ->method('get')
      ->with(OverridesSectionStorage::FIELD_NAME)
      ->willReturn($layoutField);

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->extract($entity, $entities, $related, 2, $visited);

    // Assert the entity is included, but not the non-translatable entity reference elements.
    $this->assertCount(1, $entities);

    // Assert the entity references are not included in the list.
    $this->assertEquals($entities['entity_id'][1], $entity);
    $this->assertCount(0, $related);
  }

  /**
   * @covers ::extract
   * @dataProvider dataProviderTranslationModules
   */
  public function testRunWithLingotekDisabledNestedLayoutBuilderEntities($module_data, $field_name, $field_type) {
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('block_content', 'basic')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isFieldLingotekEnabled')
      ->with('entity_id', 'bundle', $field_name)
      ->willReturn(FALSE);

    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->withConsecutive(['layout_builder_at'], ['layout_builder_st'])
      ->willReturnOnConsecutiveCalls($module_data['layout_builder_at'], $module_data['layout_builder_st']);

    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn('text');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $target_entity_type = $this->createMock(ContentEntityType::class);
    $target_entity_type->expects($this->any())
      ->method('getKey')
      ->with('revision')
      ->willReturn('revision_id');

    $embedded_block = $this->createMock(ContentEntityInterface::class);
    $embedded_block->expects($this->any())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $embedded_block->expects($this->any())
      ->method('bundle')
      ->willReturn('basic');
    $embedded_block->expects($this->any())
      ->method('id')
      ->willReturn(2);
    $embedded_block->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('block_content');
    $embedded_block->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();

    $nestedLayoutFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $nestedLayoutFieldDefinition->expects($this->any())
      ->method('getType')
      ->willReturn($field_type);
    $nestedLayoutFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Nested Block');

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->any())
      ->method('condition')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('execute')
      ->willReturn([1]);

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->any())
      ->method('load')
      ->with(1)
      ->willReturn($embedded_block);
    $entityStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);
    $entityStorage->expects($this->any())
      ->method('getEntityType')
      ->willReturn($target_entity_type);

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('block_content')
      ->willReturn($target_entity_type);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('block_content')
      ->willReturn($entityStorage);

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->withConsecutive(['entity_id'], ['block_content'])
      ->willReturn([
        'title' => $titleFieldDefinition,
        $field_name => $nestedLayoutFieldDefinition,
      ], []);

    $componentArray = [
      'configuration' => [
        'block_revision_id' => 1,
      ],
    ];

    $component = $this->createMock(SectionComponent::class);
    $component->expects($this->any())
      ->method('getPluginId')
      ->willReturn('inline_block');
    $component->expects($this->any())
      ->method('toArray')
      ->willReturn($componentArray);
    $componentItemList = [
      $component,
    ];

    $sectionObject = $this->createMock(Section::class);
    $sectionObject->expects($this->any())
      ->method('getComponents')
      ->willReturn($componentItemList);

    $sectionItemList = [
      [
        'section' => $sectionObject,
      ],
    ];
    $layoutField = $this->createMock(LayoutSectionItemList::class);
    $layoutField->expects($this->any())
      ->method('getValue')
      ->willReturn($sectionItemList);

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
    $entity->expects($this->any())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->any())
      ->method('get')
      ->with(OverridesSectionStorage::FIELD_NAME)
      ->willReturn($layoutField);

    $entities = [];
    $related = [];
    $visited = [];
    $this->detector->extract($entity, $entities, $related, 2, $visited);

    // Assert the entity is included, but not the non-translatable layout blocks.
    $this->assertCount(2, $entities);
    $this->assertEquals($entities['entity_id'][1], $entity);
    $this->assertEquals($entities['block_content'][2], $embedded_block);

    // Assert the layout blocks are not included as related.
    $this->assertCount(0, $related);
  }

  /**
   * Data provider for testRunWithLingotekEnabledNestedLayoutBuilderEntities,
   * testRunWithLingotekDisabledNestedLayoutBuilderEntities, testRunWithNonTranslatableNestedLayoutBuilderEntities
   *
   * @return array
   *   [module_data, field_type]
   */
  public function dataProviderTranslationModules() {
    // Use layout_section field for Layout Builder Asymmetric Translation module
    yield [
      'module_data' => [
        'layout_builder_at' => TRUE,
        'layout_builder_st' => FALSE,
      ],
      'field_name' => 'layout_builder__layout',
      'field_type' => 'layout_section',
    ];
    // Use layout_translation field for Layout Builder Symmetric Translations module
    yield [
      'module_data' => [
        'layout_builder_at' => FALSE,
        'layout_builder_st' => TRUE,
      ],
      'field_name' => 'layout_builder__translation',
      'field_type' => 'layout_translation',
    ];
  }

}
