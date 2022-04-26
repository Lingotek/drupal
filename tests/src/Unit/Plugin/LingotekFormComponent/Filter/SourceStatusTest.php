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
use Drupal\lingotek\Plugin\LingotekFormComponent\Filter\SourceStatus;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the source status query filter form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\SourceStatus
 * @group lingotek
 * @preserve GlobalState disabled
 */
class SourceStatusTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\Profile
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
    $this->filter = new SourceStatus([], 'source_status', ['id' => 'source_status', 'title' => 'Source status'], $this->entityTypeManager, $this->entityTypeBundleInfo, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->connection);

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
    $value = ['wrapper' => ['source_status' => 'CURRENT']];
    $this->assertEquals('CURRENT', $this->filter->getSubmittedValue($value));
  }

  /**
   * @covers ::buildElement
   */
  public function testBuildElement() {
    $this->filter->setEntityTypeId('my_entity_type_id');
    $build = $this->filter->buildElement();
    $this->assertEquals([
      '#type' => 'select',
      '#title' => 'Source status',
      '#default_value' => '',
      '#options' => [
        '' => 'All',
        'CURRENT' => 'Current',
        'EDITED' => 'Edited',
        'UPLOAD_NEEDED' => 'Upload Needed',
        'IMPORTING' => 'Importing',
        'CANCELLED' => 'Cancelled',
        'ERROR' => 'Error',
      ],
    ],
      $build);
  }

  /**
   * @covers ::filter
   */
  public function testFilter() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('id')
      ->willReturn('my_entity_type_id');
    $entity_type->expects($this->any())
      ->method('getKey')
      ->withConsecutive(['id'], ['langcode'], ['bundle'])
      ->willReturnOnConsecutiveCalls('entity_id', 'langcode', 'bundle');
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

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->at(0))
      ->method('innerJoin')
      ->with('metadata_content', 'metadata_source', "entity_table.entity_id= metadata_source.content_entity_id AND metadata_source.content_entity_type_id = 'my_entity_type_id'")
      ->willReturnSelf();
    $select->expects($this->at(1))
      ->method('innerJoin')
      ->with('lingotek_content_metadata__translation_status', 'translation_status', "metadata_source.id = translation_status.entity_id AND translation_status.translation_status_language = entity_table.langcode")
      ->willReturnSelf();
    $select->expects($this->at(2))
      ->method('condition')
      ->with('translation_status.translation_status_value', 'CURRENT', '=')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], 'CURRENT', $select);
  }

  /**
   * @covers ::filter
   */
  public function testFilterNeedsUpload() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('id')
      ->willReturn('my_entity_type_id');
    $entity_type->expects($this->any())
      ->method('getKey')
      ->withConsecutive(['id'], ['langcode'], ['bundle'])
      ->willReturnOnConsecutiveCalls('entity_id', 'langcode', 'bundle');
    $entity_type->expects($this->any())
      ->method('getBaseTable')
      ->willReturn('entity_datatable');
    $metadata = $this->createMock(ContentEntityTypeInterface::class);
    $metadata->expects($this->any())
      ->method('getBaseTable')
      ->willReturn('metadata_content');
    $metadata->expects($this->any())
      ->method('getKey')
      ->withConsecutive(['id'], ['langcode'], ['bundle'])
      ->willReturnOnConsecutiveCalls('metadata_id', 'langcode', 'bundle');
    $this->entityTypeManager->expects($this->at(0))
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);
    $this->entityTypeManager->expects($this->at(1))
      ->method('getDefinition')
      ->with('lingotek_content_metadata')
      ->willReturn($metadata);

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->at(0))
      ->method('innerJoin')
      ->with('metadata_content', 'metadata_source', "entity_table.entity_id= metadata_source.content_entity_id AND metadata_source.content_entity_type_id = 'my_entity_type_id'")
      ->willReturnSelf();
    $select->expects($this->at(1))
      ->method('innerJoin')
      ->with('lingotek_content_metadata__translation_status', 'translation_status', "metadata_source.id = translation_status.entity_id AND translation_status.translation_status_language = entity_table.langcode")
      ->willReturnSelf();
    $select->expects($this->at(2))
      ->method('condition')
      ->with('translation_status.translation_status_value', ['EDITED', 'REQUEST', 'CANCELLED', 'ERROR'], 'IN')
      ->willReturnSelf();

    $no_metadata_query = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $this->connection->expects($this->at(0))
      ->method('select')
      ->with('metadata_content', 'mt')
      ->willReturn($no_metadata_query);
    $no_metadata_query->expects($this->at(0))
      ->method('fields')
      ->with('mt', ['metadata_id'])
      ->willReturnSelf();
    $no_metadata_query->expects($this->at(1))
      ->method('where')
      ->with('entity_table.entity_id = mt.content_entity_id')
      ->willReturnSelf();

    $union1 = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $this->connection->expects($this->at(1))
      ->method('select')
      ->with('entity_datatable', 'entity_table')
      ->willReturn($union1);
    $union1->expects($this->at(0))
      ->method('fields')
      ->with('entity_table', ['entity_id'])
      ->willReturnSelf();
    $union1->expects($this->at(1))
      ->method('condition')
      ->with('entity_table.langcode', 'und', '!=')
      ->willReturnSelf();
    $union1->expects($this->at(2))
      ->method('notExists')
      ->with($no_metadata_query)
      ->willReturnSelf();

    $no_statuses_query = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $this->connection->expects($this->at(2))
      ->method('select')
      ->with('lingotek_content_metadata__translation_status', 'tst')
      ->willReturn($no_statuses_query);
    $no_statuses_query->expects($this->at(0))
      ->method('fields')
      ->with('tst', ['entity_id'])
      ->willReturnSelf();
    $no_statuses_query->expects($this->at(1))
      ->method('where')
      ->with('mt2.metadata_id = tst.entity_id')
      ->willReturnSelf();

    $union2 = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $this->connection->expects($this->at(3))
      ->method('select')
      ->with('entity_datatable', 'entity_table')
      ->willReturn($union2);
    $union2->expects($this->at(0))
      ->method('fields')
      ->with('entity_table', ['entity_id'])
      ->willReturnSelf();
    $union2->expects($this->at(1))
      ->method('innerJoin')
      ->with('metadata_content', 'mt2', "entity_table.entity_id= mt2.content_entity_id AND mt2.content_entity_type_id = 'my_entity_type_id'")
      ->willReturnSelf();
    $union2->expects($this->at(2))
      ->method('condition')
      ->with('entity_table.langcode', 'und', '!=')
      ->willReturnSelf();
    $union2->expects($this->at(3))
      ->method('notExists')
      ->with($no_metadata_query)
      ->willReturnSelf();

    $select->expects($this->at(3))
      ->method('union')
      ->with($union1);
    $select->expects($this->at(4))
      ->method('union')
      ->with($union2);

    $this->filter->filter('my_entity_type_id', [], 'UPLOAD_NEEDED', $select);
  }

}
