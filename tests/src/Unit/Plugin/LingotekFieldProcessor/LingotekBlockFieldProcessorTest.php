<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFieldProcessor;

use Drupal\block_content\Plugin\Block\BlockContentBlock;
use Drupal\block_field\BlockFieldItemInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekBlockFieldProcessor;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for the path processor plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekBlockFieldProcessor
 * @group lingotek
 * @preserve GlobalState disabled
 */
class LingotekBlockFieldProcessorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekBlockFieldProcessor
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

    $this->processor = new LingotekBlockFieldProcessor([], 'block_field', [], $this->entityTypeManager, $this->entityRepository, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->typedConfig, $this->logger);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $processor = new LingotekBlockFieldProcessor([], 'block_field', [], $this->entityTypeManager, $this->entityRepository, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->typedConfig, $this->logger);
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
    yield 'block_field field' => [TRUE, 'block_field'];
  }

  /**
   * @covers ::extract
   */
  public function testExtract() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $blockConfig = [
      'id' => 'block_content:basic',
      'label' => 'This is a basic custom block',
    ];

    $blockInstance = $this->createMock(BlockContentBlock::class);
    $blockInstance->expects($this->once())
      ->method('getPluginDefinition')
      ->willReturn(['id' => 'block_content']);
    $blockInstance->expects($this->once())
      ->method('getConfiguration')
      ->willReturn($blockConfig);
    $blockInstance->expects($this->once())
      ->method('getDerivativeId')
      ->willReturn('this-is-the-block-uuid');

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
    $this->typedConfig->expects($this->once())
      ->method('getDefinition')
      ->with('block.settings.block_content')
      ->willReturn($blockSettingsDefinition);
    $dataDefinition = $this->createMock(MapDataDefinition::class);
    $this->typedConfig->expects($this->once())
      ->method('buildDataDefinition')
      ->with($blockSettingsDefinition, $blockConfig)
      ->willReturn($dataDefinition);
    $schema = $this->createMock(Mapping::class);
    $this->typedConfig->expects($this->once())
      ->method('create')
      ->with($dataDefinition, $blockConfig)
      ->willReturn($schema);
    $this->lingotekConfigTranslation->expects($this->once())
      ->method('getTranslatableProperties')
      ->with($schema)
      ->willReturn(['label']);

    $fieldItem = $this->createMock(BlockFieldItemInterface::class);
    $fieldItem->expects($this->once())
      ->method('get')
      ->with('plugin_id')
      ->willReturnSelf();
    $fieldItem->expects($this->once())
      ->method('getValue')
      ->willReturn('block_content');
    $fieldItem->expects($this->once())
      ->method('getBlock')
      ->willReturn($blockInstance);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('field_block')
      ->willReturn([$fieldItem]);

    $blockEntity = $this->createMock(ContentEntityInterface::class);
    $this->entityRepository->expects($this->once())
      ->method('loadEntityByUuid')
      ->with('block_content', 'this-is-the-block-uuid')
      ->willReturn($blockEntity);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('getSourceData')
      ->with($blockEntity, [])
      ->willReturn([
        'info' => [
          [
            'value' => 'This is a basic custom block',
          ],
        ],
        'body' => [
          [
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
    $this->processor->extract($entity, 'field_block', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(1, $data['field_block']);

    $this->assertEquals([
       [
         'label' => 'This is a basic custom block',
         'entity' => [
           'info' => [
             [
               'value' => 'This is a basic custom block',
             ],
           ],
           'body' => [
             [
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
    ], $data['field_block']);
  }

  /**
   * @covers ::store
   */
  public function testStore() {
    $blockConfig = [
      'id' => 'block_content:basic',
      'label' => 'This is a basic custom block',
    ];

    $blockInstance = $this->createMock(BlockContentBlock::class);
    $blockInstance->expects($this->once())
      ->method('getConfiguration')
      ->willReturn($blockConfig);

    $fieldItem = $this->createMock(BlockFieldItemInterface::class);
    $fieldItem->expects($this->once())
      ->method('getBlock')
      ->willReturn($blockInstance);

    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->once())
      ->method('get')
      ->with(0)
      ->willReturn($fieldItem);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('field_block')
      ->willReturn($fieldItemList);

    $data = [
      [
        'label' => 'This is a basic custom block ES',
        'entity' => [
          'info' => [
            [
            'value' => 'This is a basic custom block ES',
            ],
          ],
          'body' => [
            [
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
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(6)
      ->willReturn($blockEntity);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('block_content')
      ->willReturn($entityStorage);

    $translationFieldItemList = $this->createMock(FieldItemListInterface::class);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('block_content', 'basic')
      ->willReturn(TRUE);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('saveTargetData')
      ->with($blockEntity, 'es', [
        'info' => [
          [
            'value' => 'This is a basic custom block ES',
          ],
        ],
        'body' => [
          [
            'value' => '<p>This is a basic custom block body ES.</p>',
          ],
        ],
        '_lingotek_metadata' => [
          '_entity_type_id' => 'block_content',
          '_entity_id' => '6',
          '_entity_revision' => '6',
        ],
      ]);

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->once())
      ->method('get')
      ->with('field_block')
      ->willReturn($translationFieldItemList);

    $this->processor->store($translation, 'es', $entity, 'field_block', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

}
