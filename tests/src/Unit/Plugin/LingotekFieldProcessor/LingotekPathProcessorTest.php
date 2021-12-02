<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekPathProcessor;
use Drupal\path_alias\PathAliasInterface;
use Drupal\path_alias\PathAliasStorage;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for the path processor plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekPathProcessor
 * @group lingotek
 * @preserve GlobalState disabled
 */
class LingotekPathProcessorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekPathProcessor
   */
  protected $processor;

  /**
   * The mocked entity type manager.
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
   * Logger service.
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
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->processor = new LingotekPathProcessor([], 'path', [], $this->entityTypeManager, $this->moduleHandler, $this->logger);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $processor = new LingotekPathProcessor([], 'path', [], $this->entityTypeManager, $this->moduleHandler, $this->logger);
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
    yield 'path field' => [TRUE, 'path'];
  }

  /**
   * @covers ::extract
   */
  public function testExtractWithNoPath() {
    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entity->expects($this->once())
      ->method('id')
      ->willReturn(3);
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'path', $fieldDefinition, $data, $visited);
    $this->assertArrayNotHasKey('path', $data);

    $this->assertEquals([], $visited);
  }

  /**
   * @covers ::extract
   */
  public function testExtract() {
    $path = $this->createMock(PathAliasInterface::class);
    $path->expects($this->once())
      ->method('getAlias')
      ->willReturn('/my-node-alias');

    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([$path]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entity->expects($this->once())
      ->method('id')
      ->willReturn(3);
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'path', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(1, $data['path']);

    $this->assertEquals([
      'alias' => '/my-node-alias',
    ], $data['path'][0]);
    // Nothing was added to the visited value.
    $this->assertEquals([], $visited);
  }

  /**
   * @covers ::store
   */
  public function testStore() {
    $path = $this->createMock(PathAliasInterface::class);

    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([$path]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->once())
      ->method('offsetGet')
      ->with(0)
      ->willReturn($fieldItem);
    $fieldItem->expects($this->once())
      ->method('set')
      ->with('alias', '/my-node-alias-ES');

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->once())
      ->method('get')
      ->with('path')
      ->willReturn($fieldItemList);

    $data = [
      [
        'alias' => '/my-node-alias-ES',
      ],
    ];
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('pathauto')
      ->willReturn(FALSE);
    $this->processor->store($translation, 'es', $entity, 'path', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::store
   */
  public function testStoreWithPathauto() {
    $path = $this->createMock(PathAliasInterface::class);

    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([$path]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->exactly(2))
      ->method('offsetGet')
      ->with(0)
      ->willReturn($fieldItem);
    $fieldItem->expects($this->exactly(2))
      ->method('set')
      ->withConsecutive(['alias', '/my-node-alias-ES'], ['pathauto', FALSE]);

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->exactly(2))
      ->method('get')
      ->with('path')
      ->willReturn($fieldItemList);

    $data = [
      [
        'alias' => '/my-node-alias-ES',
      ],
    ];
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('pathauto')
      ->willReturn(TRUE);
    $this->processor->store($translation, 'es', $entity, 'path', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::store
   */
  public function testStoreInvalidAlias() {
    $path = $this->createMock(PathAliasInterface::class);

    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([$path]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $translation = $this->createMock(ContentEntityInterface::class);

    $data = [
      [
        'alias' => 'internal://\/invalid-alias',
      ],
    ];
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('pathauto')
      ->willReturn(FALSE);

    $this->logger->expects($this->once())
      ->method('warning');

    $this->processor->store($translation, 'es', $entity, 'path', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::store
   */
  public function testStoreInvalidAliasWithPathauto() {
    $path = $this->createMock(PathAliasInterface::class);

    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([$path]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->exactly(2))
      ->method('offsetGet')
      ->with(0)
      ->willReturn($fieldItem);
    $fieldItem->expects($this->exactly(2))
      ->method('set')
      ->withConsecutive(['alias', ''], ['pathauto', TRUE]);

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->exactly(2))
      ->method('get')
      ->with('path')
      ->willReturn($fieldItemList);

    $data = [
      [
        'alias' => 'internal://\/invalid-alias',
      ],
    ];
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('pathauto')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('warning');

    $this->processor->store($translation, 'es', $entity, 'path', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::store
   */
  public function testStoreNoAlias() {
    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->once())
      ->method('offsetGet')
      ->with(0)
      ->willReturn($fieldItem);
    $fieldItem->expects($this->once())
      ->method('set')
      ->with('alias', '/node/3');

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->once())
      ->method('get')
      ->with('path')
      ->willReturn($fieldItemList);

    $data = [
      [
        'alias' => 'internal://\/invalid-alias',
      ],
    ];
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $this->moduleHandler->expects($this->exactly(2))
      ->method('moduleExists')
      ->with('pathauto')
      ->willReturn(FALSE);

    $this->logger->expects($this->once())
      ->method('warning');

    $this->processor->store($translation, 'es', $entity, 'path', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::store
   */
  public function testStoreNoAliasButPathauto() {
    $pathAliasStorage = $this->createMock(PathAliasStorage::class);
    $pathAliasStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'path' => '/node/3',
        'langcode' => 'de',
      ])
      ->willReturn([]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($pathAliasStorage);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('de');

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);

    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('getInternalPath')
      ->willReturn('node/3');
    $entity->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->exactly(2))
      ->method('offsetGet')
      ->with(0)
      ->willReturn($fieldItem);
    $fieldItem->expects($this->exactly(2))
      ->method('set')
      ->withConsecutive(['alias', ''], ['pathauto', TRUE]);

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->exactly(2))
      ->method('get')
      ->with('path')
      ->willReturn($fieldItemList);

    $data = [
      [
        'alias' => 'internal://\/invalid-alias',
      ],
    ];
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('pathauto')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('warning');

    $this->processor->store($translation, 'es', $entity, 'path', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

}
