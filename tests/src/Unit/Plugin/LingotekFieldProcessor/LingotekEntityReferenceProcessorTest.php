<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekEntityReferenceProcessor;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the path processor plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekEntityReferenceProcessor
 * @group lingotek
 * @preserve GlobalState disabled
 */
class LingotekEntityReferenceProcessorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekEntityReferenceProcessor
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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekConfigTranslation = $this->createMock(LingotekConfigTranslationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->processor = new LingotekEntityReferenceProcessor([], 'entity_reference', [], $this->entityTypeManager, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->moduleHandler);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $processor = new LingotekEntityReferenceProcessor([], 'entity_reference', [], $this->entityTypeManager, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->moduleHandler);
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

  public function dataProviderAppliesToField() {
    yield 'null field' => [FALSE, NULL];
    yield 'string_text field' => [FALSE, 'string_text'];
    yield 'entity_reference field' => [TRUE, 'entity_reference'];
  }

  /**
   * @covers ::extract
   */
  public function testExtract() {
    $storageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storageDefinition->expects($this->once())
      ->method('getSetting')
      ->with('target_type')
      ->willReturn('another_entity_referenced');
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $fieldDefinition->expects($this->once())
      ->method('getFieldStorageDefinition')
      ->willReturn($storageDefinition);

    $embeddedEntity = $this->createMock(ContentEntityInterface::class);

    $intDataTargetId = $this->createMock(IntegerData::class);
    $intDataTargetId->expects($this->once())
      ->method('getValue')
      ->willReturn(1);

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItem->expects($this->once())
      ->method('get')
      ->with('target_id')
      ->willReturn($intDataTargetId);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('field_entity_reference')
      ->willReturn([$fieldItem]);

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($embeddedEntity);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('another_entity_referenced')
      ->willReturn($entityStorage);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('getSourceData')
      ->with($embeddedEntity, [])
      ->willReturn([
        'field_text' =>
          [
              [
                'value' => '<p>Llamas are very cool</p>',
              ],
          ],
        '_lingotek_metadata' =>
          [
            '_entity_type_id' => 'another_entity_referenced',
            '_entity_id' => '1',
            '_entity_revision' => '2',
          ],
      ]);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'field_entity_reference', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(1, $data['field_entity_reference']);

    $this->assertEquals([
      [
        'field_text' =>
         [
             [
               'value' => '<p>Llamas are very cool</p>',
             ],
         ],
        '_lingotek_metadata' =>
         [
           '_entity_type_id' => 'another_entity_referenced',
           '_entity_id' => '1',
           '_entity_revision' => '2',
         ],
      ],
    ], $data['field_entity_reference']);
  }

  /**
   * @covers ::store
   */
  public function testStore() {
    $storageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storageDefinition->expects($this->once())
      ->method('getSetting')
      ->with('target_type')
      ->willReturn('another_entity_referenced');
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $fieldDefinition->expects($this->once())
      ->method('getFieldStorageDefinition')
      ->willReturn($storageDefinition);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('another_entity_referenced', 'another_entity_referenced_bundle')
      ->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);

    $data = [
      [
        'field_text' =>
          [
            [
              'value' => '<p>Las llamas son muy chulas</p>',
            ],
          ],
        '_lingotek_metadata' =>
          [
            '_entity_type_id' => 'another_entity_referenced',
            '_entity_id' => '1',
            '_entity_revision' => '2',
          ],
      ],
    ];

    $embeddedEntity = $this->createMock(ContentEntityInterface::class);
    $embeddedEntity->expects($this->once())
      ->method('id')
      ->willReturn(1);
    $embeddedEntity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('another_entity_referenced');
    $embeddedEntity->expects($this->once())
      ->method('bundle')
      ->willReturn('another_entity_referenced_bundle');

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($embeddedEntity);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('another_entity_referenced')
      ->willReturn($entityStorage);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('saveTargetData')
      ->with($embeddedEntity, 'es', [
        'field_text' =>
          [
            [
              'value' => '<p>Las llamas son muy chulas</p>',
            ],
          ],
        '_lingotek_metadata' =>
          [
            '_entity_type_id' => 'another_entity_referenced',
            '_entity_id' => '1',
            '_entity_revision' => '2',
          ],
      ]);

    $translationFieldItemList = $this->createMock(EntityReferenceFieldItemListInterface::class);

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->once())
      ->method('get')
      ->with('field_entity_reference')
      ->willReturn($translationFieldItemList);
    $translationFieldItemList->expects($this->once())
      ->method('set')
      ->with(0, $embeddedEntity->id());

    $this->processor->store($translation, 'es', $entity, 'field_entity_reference', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

}
