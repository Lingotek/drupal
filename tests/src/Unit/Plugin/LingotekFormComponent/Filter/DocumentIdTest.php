<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Filter\DocumentId;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the document ID query filter form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\DocumentId
 * @group lingotek
 * @preserve GlobalState disabled
 */
class DocumentIdTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\DocumentId
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
    $this->filter = new DocumentId([], 'document_id', ['id' => 'document_id', 'title' => 'Document ID'], $this->entityTypeManager, $this->entityTypeBundleInfo, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->connection);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->filter->setStringTranslation($translation);
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    $arguments = [
      'entity_type_id' => 'an_entity_type',
    ];
    $this->assertTrue($this->filter->isApplicable($arguments));
  }

  /**
   * @covers ::getSubmittedValue
   */
  public function testGetSubmittedValue() {
    $value = ['wrapper' => ['document_id' => 'my_document_id']];
    $this->assertEquals('my_document_id', $this->filter->getSubmittedValue($value));
  }

  /**
   * @covers ::buildElement
   */
  public function testBuildElement() {
    $this->filter->setEntityTypeId('my_entity_type_id');
    $build = $this->filter->buildElement();
    $this->assertEquals([
      '#type' => 'textfield',
      '#size' => 35,
      '#title' => 'Document ID',
      '#description' => 'You can indicate multiple comma-separated values.',
      '#default_value' => '',
    ],
      $build);
  }

  /**
   * @covers ::filter
   */
  public function testFilterWithAListOfDocumentIds() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('id')
      ->willReturn('my_entity_type');
    $entity_type->expects($this->any())
      ->method('getKey')
      ->with('id')
      ->willReturn('entity_id');
    $metadata = $this->createMock(ContentEntityTypeInterface::class);
    $metadata->expects($this->any())
      ->method('getBaseTable')
      ->willReturn('metadata_content');
    $this->entityTypeManager->expects($this->at(0))
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);
    $this->entityTypeManager->expects($this->at(1))
      ->method('getDefinition')
      ->with('lingotek_content_metadata')
      ->willReturn($metadata);

    $unionQuery = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->once())
      ->method('innerJoin')
      ->with('metadata_content', 'metadata', "entity_table.entity_id= metadata.content_entity_id AND metadata.content_entity_type_id = 'my_entity_type'")
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('metadata.document_id', ['my_document_id', 'another_document_id'], 'IN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('getUnion')
      ->willReturn([
        ['query' => $unionQuery],
      ]);
    $unionQuery->expects($this->once())
      ->method('innerJoin')
      ->with('metadata_content', 'metadata', "entity_table.entity_id= metadata.content_entity_id AND metadata.content_entity_type_id = 'my_entity_type'")
      ->willReturnSelf();
    $unionQuery->expects($this->once())
      ->method('condition')
      ->with('metadata.document_id', ['my_document_id', 'another_document_id'], 'IN')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], 'my_document_id,another_document_id', $select);
  }

  /**
   * @covers ::filter
   */
  public function testFilterWithSingleDocumentId() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('id')
      ->willReturn('my_entity_type');
    $entity_type->expects($this->any())
      ->method('getKey')
      ->with('id')
      ->willReturn('entity_id');
    $metadata = $this->createMock(ContentEntityTypeInterface::class);
    $metadata->expects($this->any())
      ->method('getBaseTable')
      ->willReturn('metadata_content');
    $this->entityTypeManager->expects($this->at(0))
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);
    $this->entityTypeManager->expects($this->at(1))
      ->method('getDefinition')
      ->with('lingotek_content_metadata')
      ->willReturn($metadata);

    $unionQuery = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->once())
      ->method('innerJoin')
      ->with('metadata_content', 'metadata', "entity_table.entity_id= metadata.content_entity_id AND metadata.content_entity_type_id = 'my_entity_type'")
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('metadata.document_id', '%my_document_id%', 'LIKE')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('getUnion')
      ->willReturn([
        ['query' => $unionQuery],
      ]);
    $unionQuery->expects($this->once())
      ->method('innerJoin')
      ->with('metadata_content', 'metadata', "entity_table.entity_id= metadata.content_entity_id AND metadata.content_entity_type_id = 'my_entity_type'")
      ->willReturnSelf();
    $unionQuery->expects($this->once())
      ->method('condition')
      ->with('metadata.document_id', '%my_document_id%', 'LIKE')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], 'my_document_id', $select);
  }

}
