<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Filter\SourceLanguage;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the source language query filter form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\SourceLanguage
 * @group lingotek
 * @preserve GlobalState disabled
 */
class SourceLanguageTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\SourceLanguage
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
    $this->filter = new SourceLanguage([], 'source_language', ['id' => 'source_language', 'title' => 'Source Language'], $this->entityTypeManager, $this->entityTypeBundleInfo, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->connection);

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
    $value = ['wrapper' => ['source_language' => 'en']];
    $this->assertEquals('en', $this->filter->getSubmittedValue($value));
  }

  /**
   * @covers ::buildElement
   * @covers ::getAllLanguages
   */
  public function testBuildElement() {
    $english = $this->createMock(LanguageInterface::class);
    $english->expects($this->once())
      ->method('getName')
      ->willReturn('English');
    $andaluh = $this->createMock(LanguageInterface::class);
    $andaluh->expects($this->once())
      ->method('getName')
      ->willReturn('Andalûh');

    $this->filter->setEntityTypeId('my_entity_type_id');
    $this->languageManager->expects($this->once())
      ->method('getLanguages')
      ->willReturn([
        'en' => $english,
        'epa' => $andaluh,
      ]);
    $build = $this->filter->buildElement();
    $this->assertEquals([
      '#type' => 'select',
      '#title' => 'Source Language',
      '#default_value' => '',
      '#options' => [
        '' => 'All languages',
        'en' => 'English',
        'epa' => 'Andalûh',
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
      ->willReturn('my_entity_type');
    $entity_type->expects($this->at(0))
      ->method('getKey')
      ->with('id')
      ->willReturn('entity_id');
    $entity_type->expects($this->at(1))
      ->method('getKey')
      ->with('langcode')
      ->willReturn('langcode');
    $entity_type->expects($this->any())
      ->method('getDataTable')
      ->willReturn('entity_data_table');
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);

    $unionQuery = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->at(0))
      ->method('innerJoin')
      ->with('entity_data_table', 'entity_data', "entity_table.entity_id= entity_data.entity_id")
      ->willReturnSelf();
    $select->expects($this->at(1))
      ->method('condition')
      ->with('entity_table.langcode', ['epa'], '=')
      ->willReturnSelf();
    $select->expects($this->at(2))
      ->method('condition')
      ->with('entity_data.default_langcode', 1, '=')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('getUnion')
      ->willReturn([
        ['query' => $unionQuery],
      ]);
    $unionQuery->expects($this->at(0))
      ->method('innerJoin')
      ->with('entity_data_table', 'entity_data', "entity_table.entity_id= entity_data.entity_id")
      ->willReturnSelf();
    $unionQuery->expects($this->at(1))
      ->method('condition')
      ->with('entity_table.langcode', ['epa'], '=')
      ->willReturnSelf();
    $unionQuery->expects($this->at(2))
      ->method('condition')
      ->with('entity_data.default_langcode', 1, '=')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], ['epa'], $select);
  }

}
