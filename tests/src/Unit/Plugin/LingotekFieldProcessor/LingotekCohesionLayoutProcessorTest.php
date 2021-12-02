<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFieldProcessor;

use Drupal\cohesion\LayoutCanvas\ElementModel;
use Drupal\cohesion\LayoutCanvas\LayoutCanvas;
use Drupal\cohesion_elements\Entity\Component;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekCohesionLayoutProcessor;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for the path processor plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekCohesionLayoutProcessor
 * @group lingotek
 * @preserve GlobalState disabled
 */
class LingotekCohesionLayoutProcessorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekCohesionLayoutProcessor
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
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $keyValueStore;

  /**
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $keyValueFactory;

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
    $this->blockManager = $this->createMock(BlockManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekConfigTranslation = $this->createMock(LingotekConfigTranslationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->keyValueStore = $this->createMock(KeyValueStoreInterface::class);
    $this->keyValueFactory = $this->createMock(KeyValueFactoryInterface::class);
    $this->keyValueFactory->expects($this->any())
      ->method('get')
      ->with('cohesion.assets.form_elements')
      ->willReturn($this->keyValueStore);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->processor = new LingotekCohesionLayoutProcessor([], 'cohesion_layout', [], $this->entityTypeManager, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->keyValueFactory, $this->logger);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $processor = new LingotekCohesionLayoutProcessor([], 'cohesion_layout', [], $this->entityTypeManager, $this->lingotekConfiguration, $this->lingotekConfigTranslation, $this->lingotekContentTranslation, $this->keyValueFactory, $this->logger);
    $this->assertNotNull($processor);
  }

  /**
   * @covers ::appliesToField
   * @dataProvider dataProviderAppliesToField
   */
  public function testAppliesToField($expected, $field_type, $field_name, $hostEntityTypeId) {
    $entity = $this->createMock(ContentEntityInterface::class);
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $fieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn($field_type);
    $fieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn($field_name);

    $entityType = $this->createMock(EntityInterface::class);
    $entityType->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($hostEntityTypeId);
    $fieldItemList = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $fieldItemList->expects($this->any())
      ->method('getEntity')
      ->willReturn($entityType);

    $entity->expects($this->any())
      ->method('get')
      ->with($field_name)
      ->willReturn($fieldItemList);

    $result = $this->processor->appliesToField($fieldDefinition, $entity);
    $this->assertSame($expected, $result);
  }

  /**
   * @dataProvider dataProviderAppliesToField
   */
  public function dataProviderAppliesToField() {
    yield 'null field' => [FALSE, NULL, NULL, NULL];
    yield 'string_text field' => [FALSE, 'string_text', NULL, NULL];
    yield 'string_long field' => [FALSE, 'string_long', 'field_name', 'whatever_entity'];
    yield 'string_long field named json_values from different entity' => [FALSE, 'string_long', 'json_values', 'whatever_entity'];
    yield 'cohesion layout json_values field' => [TRUE, 'string_long', 'json_values', 'cohesion_layout'];
  }

  /**
   * @covers ::extract
   * @covers ::extractCohesionComponentValues
   */
  public function testExtract() {
    $textComponentModel = $this->createMock(ElementModel::class);
    $textComponentModel->expects($this->at(0))
      ->method('getProperty')
      ->with('settings')
      ->willReturn((object) ['translate' => TRUE, 'type' => 'cohWysiwyg', 'schema' => (object) ['type' => 'object']]);
    $textComponentModel->expects($this->at(2))
      ->method('getProperty')
      ->with('uid')
      ->willReturn('6b671446-cb09-46cb-b84a-7366da00be36');

    $formElement = ['translate' => TRUE];
    $this->keyValueStore->expects($this->once())
      ->method('get')
      ->with('6b671446-cb09-46cb-b84a-7366da00be36')
      ->willReturn($formElement);

    $textComponentModel->expects($this->once())
      ->method('getElement')
      ->willReturnSelf();

    $elementModels = [
      '6b671446-cb09-46cb-b84a-7366da00be36' => $textComponentModel,
    ];
    $textComponentCanvas = $this->createMock(LayoutCanvas::class);
    $textComponent = $this->createMock(Component::class);
    $textComponent->expects($this->once())
      ->method('getLayoutCanvasInstance')
      ->willReturn($textComponentCanvas);
    $textComponentCanvas->expects($this->once())
      ->method('iterateModels')
      ->with('component_form')
      ->willReturn($elementModels);

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('cohesion_component')
      ->willReturn($entityStorage);
    $entityStorage->expects($this->at(0))
      ->method('load')
      ->with('3fedc674')
      ->willReturn($textComponent);

    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $json_values = <<<'JSON'
{"canvas":[{"uid":"3fedc674","type":"component","title":"Text","enabled":true,"category":"category-3","componentId":"3fedc674","componentType":"container","uuid":"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8","parentUid":"root","isContainer":0,"children":[]}],"mapper":{},"model":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{"settings":{"title":"Text: Llamas are very cool"},"6b671446-cb09-46cb-b84a-7366da00be36":{"text":"<p>Llamas are very cool</p>","textFormat":"cohesion"},"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d":{"name":"White","uid":"white","value":{"hex":"#ffffff","rgba":"rgba(255, 255, 255, 1)"},"wysiwyg":true,"class":".coh-color-white","variable":"$coh-color-white","inuse":true,"link":true},"e6f07bf5-1bfa-4fef-8baa-62abb3016788":"coh-style-max-width---narrow","165f1de9-336c-42cc-bed2-28ef036ec7e3":"coh-style-padding-bottom---large","4c27d36c-a473-47ec-8d43-3b9696d45d74":""}},"previewModel":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{}},"variableFields":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":[]},"meta":{"fieldHistory":[]}}
JSON;
    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItem->expects($this->once())
      ->method('__get')
      ->with('value')
      ->willReturn($json_values);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('layout_canvas')
      ->willReturn($fieldItem);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'layout_canvas', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(1, $data['layout_canvas']);

    $this->assertEquals([
      'ac9583af-74f9-419d-9f8a-68f6ca0ef5e8' => [
        '6b671446-cb09-46cb-b84a-7366da00be36' => '<p>Llamas are very cool</p>',
      ],
    ], $data['layout_canvas']);
  }

  /**
   * @covers ::store
   * @covers ::setCohesionComponentValues
   */
  public function testStore() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    // Note: This is not really testing the correct values being stored,
    // as internally there is a lot of object creation based in the existing
    // values, and we store the created layout and cannot mock up that,
    // so we are serialized the original again. But better than nothing,
    // we ensure that something is happening.
    $existingData = <<<'JSON'
{"canvas":[{"uid":"3fedc674","type":"component","title":"Text","enabled":true,"category":"category-3","componentId":"3fedc674","componentType":"container","uuid":"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8","parentUid":"root","isContainer":0,"children":[]}],"mapper":{},"model":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{"settings":{"title":"Text: Las llamas son muy chulas"},"6b671446-cb09-46cb-b84a-7366da00be36":{"text":"<p>Las llamas son muy chulas</p>","textFormat":"cohesion"},"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d":{"name":"White","uid":"white","value":{"hex":"#ffffff","rgba":"rgba(255, 255, 255, 1)"},"wysiwyg":true,"class":".coh-color-white","variable":"$coh-color-white","inuse":true,"link":true},"e6f07bf5-1bfa-4fef-8baa-62abb3016788":"coh-style-max-width---narrow","165f1de9-336c-42cc-bed2-28ef036ec7e3":"coh-style-padding-bottom---large","4c27d36c-a473-47ec-8d43-3b9696d45d74":""}},"previewModel":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{}},"variableFields":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":[]},"meta":{"fieldHistory":[]}}
JSON;

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItem->expects($this->once())
      ->method('__get')
      ->with('value')
      ->willReturn($existingData);

    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->once())
      ->method('get')
      ->with(0)
      ->willReturn($fieldItem);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('layout_canvas')
      ->willReturn($fieldItemList);

    $data = [
      'ac9583af-74f9-419d-9f8a-68f6ca0ef5e8' => [
        '6b671446-cb09-46cb-b84a-7366da00be36' => '&lt;p&gt;Las llamas son muy chulas&lt;/p&gt;',
      ],
    ];

    $textComponentModel = $this->createMock(ElementModel::class);
    $textComponentModel->expects($this->once())
      ->method('getProperty')
      ->with('settings')
      ->willReturn((object) ['translate' => TRUE, 'type' => 'cohWysiwyg', 'schema' => (object) ['type' => 'object']]);

    $elementModels = [
      '6b671446-cb09-46cb-b84a-7366da00be36' => $textComponentModel,
    ];
    $textComponentCanvas = $this->createMock(LayoutCanvas::class);

    $textComponent = $this->createMock(Component::class);
    $textComponent->expects($this->once())
      ->method('getLayoutCanvasInstance')
      ->willReturn($textComponentCanvas);
    $textComponentCanvas->expects($this->once())
      ->method('iterateModels')
      ->with('component_form')
      ->willReturn($elementModels);

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with('3fedc674')
      ->willReturn($textComponent);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('cohesion_component')
      ->willReturn($entityStorage);

    $translatedValue = <<<'JSON'
{"canvas":[{"uid":"3fedc674","type":"component","title":"Text","enabled":true,"category":"category-3","componentId":"3fedc674","componentType":"container","uuid":"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8","parentUid":"root","isContainer":0,"children":[]}],"mapper":{},"model":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{"settings":{"title":"Text: Las llamas son muy chulas"},"6b671446-cb09-46cb-b84a-7366da00be36":{"text":"&lt;p&gt;Las llamas son muy chulas&lt;\/p&gt;","textFormat":"cohesion"},"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d":{"name":"White","uid":"white","value":{"hex":"#ffffff","rgba":"rgba(255, 255, 255, 1)"},"wysiwyg":true,"class":".coh-color-white","variable":"$coh-color-white","inuse":true,"link":true},"e6f07bf5-1bfa-4fef-8baa-62abb3016788":"coh-style-max-width---narrow","165f1de9-336c-42cc-bed2-28ef036ec7e3":"coh-style-padding-bottom---large","4c27d36c-a473-47ec-8d43-3b9696d45d74":""}},"previewModel":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{}},"variableFields":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":[]},"meta":{"fieldHistory":[]}}
JSON;

    $translationFieldItem = $this->createMock(FieldItemInterface::class);
    $translationFieldItem->expects($this->once())
      ->method('set')
      ->with('value', $translatedValue);

    $translationFieldItemList = $this->createMock(FieldItemListInterface::class);
    $translationFieldItemList->expects($this->once())
      ->method('get')
      ->with(0)
      ->willReturn($translationFieldItem);

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->once())
      ->method('get')
      ->with('layout_canvas')
      ->willReturn($translationFieldItemList);

    $this->processor->store($translation, 'es', $entity, 'layout_canvas', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

}
