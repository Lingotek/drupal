<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFieldProcessor;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekLayoutBuilderATProcessor;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for the path processor plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekLayoutBuilderATProcessor
 * @group lingotek
 * @preserve GlobalState disabled
 */
class LingotekLayoutBuilderATProcessorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekLayoutBuilderATProcessor
   */
  protected $processor;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekConfiguration;

  /**
   * The Lingotek configuration translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekConfigTranslation;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekContentTranslation;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityRepository;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $blockManager;

  /**
   * The typed config handler.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $typedConfig;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $this->blockManager = $this->createMock(BlockManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekConfigTranslation = $this->createMock(LingotekConfigTranslationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->typedConfig = $this->createMock(TypedConfigManagerInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->processor = new LingotekLayoutBuilderATProcessor([], 'layout_builder_at', [], $this->entityTypeManager, $this->entityRepository, $this->blockManager, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->typedConfig, $this->moduleHandler, $this->logger);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $processor = new LingotekLayoutBuilderATProcessor([], 'layout_builder_at', [], $this->entityTypeManager, $this->entityRepository, $this->blockManager, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->typedConfig, $this->moduleHandler, $this->logger);
    $this->assertNotNull($processor);
  }

  /**
   * @covers ::appliesToField
   * @dataProvider dataProviderAppliesToField
   */
  public function testAppliesToField($expected, $field_type) {
    $entity = $this->createMock(ContentEntityInterface::class);
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $fieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn($field_type);
    $result = $this->processor->appliesToField($fieldDefinition, $entity);
    $this->assertSame($expected, $result);
  }

  /**
   * @dataProvider dataProviderAppliesToField
   */
  public function dataProviderAppliesToField() {
    yield 'null field' => [FALSE, NULL];
    yield 'string_text field' => [FALSE, 'string_text'];
    yield 'layout_translation field' => [FALSE, 'layout_translation'];
    yield 'layout_section field' => [TRUE, 'layout_section'];
  }

  /**
   * @covers ::extract
   */
  public function testExtractEmptyLayout() {
    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('layout_builder_at')
      ->willReturn(TRUE);

    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $section = $this->createMock(Section::class);
    $section->expects($this->once())
      ->method('getComponents')
      ->willReturn([]);
    $fieldItem = $this->createMock(FieldItemListInterface::class);
    $fieldItem->expects($this->once())
      ->method('getValue')
      ->willReturn(['section' => $section]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('layout_builder__layout')
      ->willReturn([$fieldItem]);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'layout_builder__layout', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(1, $data['layout_builder__layout']);

    $this->assertEquals([
      'components' => [],
    ], $data['layout_builder__layout']);
    // Nothing was added to the visited value.
    $this->assertEquals([], $visited);
  }

  /**
   * @covers ::extract
   */
  public function testExtract() {
    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('layout_builder_at')
      ->willReturn(TRUE);

    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $component1Config = [
      'id' => 'inline_block:basic',
      'label' => 'This is a basic custom block',
      'provider' => 'layout_builder',
      'label_display' => 'visible',
      'view_mode' => 'full',
      'block_revision_id' => '6',
      'block_serialized' => NULL,
    ];
    $component1 = $this->createMock(SectionComponent::class);
    $component1->expects($this->once())
      ->method('getPluginId')
      ->willReturn('inline_block:basic');
    $component1->expects($this->once())
      ->method('get')
      ->with('configuration')
      ->willReturn($component1Config);

    $blockInstance1 = $this->createMock(InlineBlock::class);
    $blockInstance1->expects($this->once())
      ->method('getPluginDefinition')
      ->willReturn(['id' => 'inline_block']);
    $blockInstance1->expects($this->once())
      ->method('getConfiguration')
      ->willReturn($component1Config);

    $this->blockManager->expects($this->at(0))
      ->method('createInstance')
      ->with('inline_block:basic', $component1Config)
      ->willReturn($blockInstance1);

    $component2Config = [
      'id' => 'field_block:node:article:field_tags',
      'label' => '',
      'provider' => 'layout_builder',
      'label_display' => '0',
      'formatter' => [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => TRUE,
        ],
        'third_party_settings' => [],
      ],
      'context_mapping' => [
        'entity' => 'layout_builder.entity',
      ],
    ];
    $component2 = $this->createMock(SectionComponent::class);
    $component2->expects($this->once())
      ->method('getPluginId')
      ->willReturn('field_block:node:article:field_tags');
    $component2->expects($this->once())
      ->method('get')
      ->with('configuration')
      ->willReturn($component2Config);
    $blockInstance2 = $this->createMock(FieldBlock::class);
    $blockInstance2->expects($this->once())
      ->method('getPluginDefinition')
      ->willReturn(['id' => 'field_block']);
    $blockInstance2->expects($this->once())
      ->method('getConfiguration')
      ->willReturn($component2Config);

    $this->blockManager->expects($this->at(1))
      ->method('createInstance')
      ->with('field_block:node:article:field_tags', $component2Config)
      ->willReturn($blockInstance2);

    $blockSettingsDefinition = [
      'label' => 'Block settings',
      'class' => 'Drupal\\Core\\Config\\Schema\\Mapping',
      'definition_class' => '\\Drupal\\Core\\TypedData\\MapDataDefinition',
      'form_element_class' => '\\Drupal\\config_translation\\FormElement\\ListElement',
      'unwrap_for_canonical_representation' => TRUE,
      'mapping' => [
        'id' => [
          'type' => 'string',
          'label' => 'ID',
        ],
        'label' => [
          'type' => 'label',
          'label' => 'Description',
        ],
        'label_display' => [
          'type' => 'string',
          'label' => 'Display title',
        ],
        'status' => [
          'type' => 'boolean',
          'label' => 'Status',
        ],
        'info' => [
          'type' => 'label',
          'label' => 'Admin info',
        ],
        'view_mode' => [
          'type' => 'string',
          'label' => 'View mode',
        ],
        'provider' => [
          'type' => 'string',
          'label' => 'Provider',
        ],
        'context_mapping' => [
          'type' => 'sequence',
          'label' => 'Context assignments',
          'sequence' => [
            'type' => 'string',
          ],
        ],
      ],
      'type' => 'block.settings.*',
    ];
    $this->typedConfig->expects($this->exactly(2))
      ->method('getDefinition')
      ->withConsecutive(['block.settings.inline_block'], ['block.settings.field_block'])
      ->willReturn($blockSettingsDefinition);
    $dataDefinition = $this->createMock(MapDataDefinition::class);
    $this->typedConfig->expects($this->exactly(2))
      ->method('buildDataDefinition')
      ->withConsecutive([$blockSettingsDefinition, $component1Config], [$blockSettingsDefinition, $component2Config])
      ->willReturn($dataDefinition);
    $schema = $this->createMock(Mapping::class);
    $this->typedConfig->expects($this->exactly(2))
      ->method('create')
      ->withConsecutive([$dataDefinition, $component1Config], [$dataDefinition, $component2Config])
      ->willReturn($schema);
    $this->lingotekConfigTranslation->expects($this->exactly(2))
      ->method('getTranslatableProperties')
      ->with($schema)
      ->willReturn(['label']);

    $section = $this->createMock(Section::class);
    $section->expects($this->once())
      ->method('getComponents')
      ->willReturn([
        'first-very-long-uuid' => $component1,
        'second-very-long-uuid' => $component2,
      ]);

    $fieldItem = $this->createMock(FieldItemListInterface::class);
    $fieldItem->expects($this->once())
      ->method('getValue')
      ->willReturn(
        [
          'section' => $section,
        ],
      );

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('layout_builder__layout')
      ->willReturn([$fieldItem]);

    $blockEntity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('loadRevision')
      ->with(6)
      ->willReturn($blockEntity);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('block_content')
      ->willReturn($entityStorage);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('getSourceData')
      ->with($blockEntity, [])
      ->willReturn([
        'info' => [[
          'value' => 'This is a basic custom block',
        ],
        ],
        'body' => [[
          'value' => '<p>This is a basic custom block body.</p>',
        ],
        ],
        '_lingotek_metadata' => [
          '_entity_type_id' => 'block_content',
          '_entity_id' => '6',
          '_entity_revision' => '6',
        ],
      ]);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'layout_builder__layout', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(2, $data['layout_builder__layout']);

    $this->assertEquals([
        'components' => [
            'first-very-long-uuid' => [
                'label' => 'This is a basic custom block',
              ],
            'second-very-long-uuid' => [
                'label' => '',
              ],
          ],
        'entities' => [
          'block_content' => [
            6 => [
              'info' => [[
                  'value' => 'This is a basic custom block',
                ],
              ],
              'body' => [[
                  'value' => '<p>This is a basic custom block body.</p>',
                ],
              ],
              '_lingotek_metadata' => [
                '_entity_type_id' => 'block_content',
                '_entity_id' => '6',
                '_entity_revision' => '6',
              ],
            ],
          ],
        ],
    ], $data['layout_builder__layout']);
  }

  /**
   * @covers ::store
   */
  public function testStore() {
    $entity = $this->createMock(ContentEntityInterface::class);

    $component1Config = [
      'id' => 'inline_block:basic',
      'label' => 'This is a basic custom block',
      'provider' => 'layout_builder',
      'label_display' => 'visible',
      'view_mode' => 'full',
      'block_revision_id' => '6',
      'block_serialized' => NULL,
    ];
    $component1 = $this->createMock(SectionComponent::class);
    $component1->expects($this->once())
      ->method('get')
      ->with('configuration')
      ->willReturn($component1Config);

    $component2Config = [
      'id' => 'field_block:node:article:field_tags',
      'label' => '',
      'provider' => 'layout_builder',
      'label_display' => '0',
      'formatter' => [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => TRUE,
        ],
        'third_party_settings' => [],
      ],
      'context_mapping' => [
        'entity' => 'layout_builder.entity',
      ],
    ];
    $component2 = $this->createMock(SectionComponent::class);
    $component2->expects($this->once())
      ->method('get')
      ->with('configuration')
      ->willReturn($component2Config);

    $section = $this->createMock(Section::class);
    $section->expects($this->once())
      ->method('getComponents')
      ->willReturn([
        'first-very-long-uuid' => $component1,
        'second-very-long-uuid' => $component2,
      ]);

    $translation = $this->createMock(ContentEntityInterface::class);

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('layout_builder__layout')
      ->willReturn([$fieldItem]);
    $fieldItem->expects($this->once())
      ->method('__get')
      ->with('section')
      ->willReturn($section);

    $data = [
      'components' => [
        'first-very-long-uuid' => [
          'label' => 'This is a basic custom block ES',
        ],
        'second-very-long-uuid' => [
          'label' => 'ES',
        ],
      ],
      'entities' => [
        'block_content' => [
          6 => [
            'info' => [[
              'value' => 'This is a basic custom block ES',
            ],
            ],
            'body' => [[
              'value' => '<p>This is a basic custom block body ES.</p>',
            ],
            ],
            '_lingotek_metadata' => [
              '_entity_type_id' => 'block_content',
              '_entity_id' => '6',
              '_entity_revision' => '6',
            ],
          ],
        ],
      ],
    ];
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $blockEntity = $this->createMock(ContentEntityInterface::class);
    $blockEntity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('block_content');
    $blockEntity->expects($this->once())
      ->method('bundle')
      ->willReturn('basic');
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->exactly(2))
      ->method('loadRevision')
      ->with(6)
      ->willReturn($blockEntity);
    $blockEntity->expects($this->once())
      ->method('id')
      ->willReturn(6);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(6)
      ->willReturn($blockEntity);
    $this->entityTypeManager->expects($this->exactly(2))
      ->method('getStorage')
      ->with('block_content')
      ->willReturn($entityStorage);

    $translationFieldItemList = $this->createMock(FieldItemListInterface::class);
    $translationFieldItemList->expects($this->once())
      ->method('set')
      ->with(0, [
        'section' => $section,
      ]);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('block_content', 'basic')
      ->willReturn(TRUE);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('saveTargetData')
      ->with($blockEntity, 'es', [
        'info' => [[
          'value' => 'This is a basic custom block ES',
        ],
        ],
        'body' => [[
          'value' => '<p>This is a basic custom block body ES.</p>',
        ],
        ],
        '_lingotek_metadata' => [
          '_entity_type_id' => 'block_content',
          '_entity_id' => '6',
          '_entity_revision' => '6',
        ],
      ]);

    $translation->expects($this->once())
      ->method('get')
      ->with('layout_builder__layout')
      ->willReturn($translationFieldItemList);

    $this->processor->store($translation, 'es', $entity, 'layout_builder__layout', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

}
