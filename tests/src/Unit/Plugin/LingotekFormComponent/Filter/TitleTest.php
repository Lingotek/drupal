<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Filter\Title;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the title query filter form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\Title
 * @group lingotek
 * @preserve GlobalState disabled
 */
class TitleTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\Title
   */
  protected $filter;

  /**
   * The connection object on which to run queries.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $connection;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked entity type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked entity_type.bundle.info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeBundleInfo;

  /**
   * The mocked Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekConfiguration;

  /**
   * The mocked Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekContentTranslation;

  /**
   * The mock entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->connection = $this->getMockBuilder(Connection::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->entityType = $this->createMock(ContentEntityTypeInterface::class);
    $this->entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn(new TranslatableMarkup("My Bundle Label"));
    $this->entityType->expects($this->any())
      ->method('getKey')
      ->withConsecutive(['id'], ['langcode'], ['bundle'])
      ->willReturnOnConsecutiveCalls('id', 'langcode', 'bundle');

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeBundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->filter = new Title([], 'title', ['id' => 'title', 'title' => 'Title'], $this->entityTypeManager, $this->entityTypeBundleInfo, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->connection, $this->entityFieldManager);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->filter->setStringTranslation($translation);
  }

  /**
   * @covers ::isApplicable
   * @dataProvider dataProviderIsApplicable
   */
  public function testIsApplicable($entity_type_id, $hasLabel, $expected) {
    $arguments = [];
    if ($entity_type_id) {
      $entityType = $this->createMock(EntityTypeInterface::class);
      $entityType->expects($this->once())
        ->method('hasKey')
        ->with('label')
        ->willReturn($hasLabel);
      $arguments = [
        'entity_type_id' => $entity_type_id,
      ];
      $this->entityTypeManager->expects($this->any())
        ->method('getDefinition')
        ->with($entity_type_id)
        ->willReturn($entityType);
    }
    $this->assertSame($expected, $this->filter->isApplicable($arguments));
  }

  public function dataProviderIsApplicable() {
    yield 'no entity type id' => [NULL, FALSE, FALSE];
    yield 'has not label' => ['my_entity_type_id', FALSE, FALSE];
    yield 'has label' => ['my_entity_type_id', TRUE, TRUE];
  }

  /**
   * @covers ::getSubmittedValue
   */
  public function testGetSubmittedValue() {
    $value = ['wrapper' => ['title' => 'a test label']];
    $this->assertEquals('a test label', $this->filter->getSubmittedValue($value));
  }

  /**
   * @covers ::buildElement
   */
  public function testBuildElement() {
    $this->filter->setEntityTypeId('my_entity_type_id');
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('id')
      ->willReturn('my_entity_type');
    $entity_type->expects($this->any())
      ->method('getKey')
      ->with('label')
      ->willReturn('title');
    $entity_type->expects($this->any())
      ->method('get')
      ->willReturn('entity_data_table');
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);

    $titleDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleDefinition->expects($this->once())
      ->method('getLabel')
      ->willReturn('Title');
    $this->entityFieldManager->expects($this->once())
      ->method('getBaseFieldDefinitions')
      ->with('my_entity_type_id')
      ->willReturn(['title' => $titleDefinition]);

    $build = $this->filter->buildElement();
    $this->assertEquals([
      '#type' => 'textfield',
      '#title' => 'Title',
      '#default_value' => '',
      '#size' => 35,
    ], $build);
  }

  /**
   * @covers ::filter
   */
  public function testFilter() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('id')
      ->willReturn('my_entity_type');
    $entity_type->expects($this->any())
      ->method('getKey')
      ->withConsecutive(['id'], ['label'])
      ->willReturnOnConsecutiveCalls('entity_id', 'title');
    $entity_type->expects($this->any())
      ->method('getDataTable')
      ->willReturn('entity_data_table');
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);

    $unionQuery = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->once())
      ->method('innerJoin')
      ->with('entity_data_table', 'entity_data', "entity_table.entity_id= entity_data.entity_id")
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('entity_data.title', '%This is an example title%', 'LIKE')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('getUnion')
      ->willReturn([
        ['query' => $unionQuery],
      ]);
    $unionQuery->expects($this->once())
      ->method('innerJoin')
      ->with('entity_data_table', 'entity_data', "entity_table.entity_id= entity_data.entity_id")
      ->willReturnSelf();
    $unionQuery->expects($this->once())
      ->method('condition')
      ->with('entity_data.title', '%This is an example title%', 'LIKE')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], 'This is an example title', $select);
  }

}
