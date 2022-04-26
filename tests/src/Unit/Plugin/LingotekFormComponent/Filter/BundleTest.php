<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Filter\Bundle;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the bundle query filter form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\Bundle
 * @group lingotek
 * @preserve GlobalState disabled
 */
class BundleTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\DefaultQuery
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
    $this->filter = new Bundle([], 'bundle', ['id' => 'bundle'], $this->entityTypeManager, $this->entityTypeBundleInfo, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->connection);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->filter->setStringTranslation($translation);
  }

  /**
   * @covers ::isApplicable
   * @dataProvider dataProviderIsApplicable
   */
  public function testIsApplicable($entity_type_id, $entity_type, $expected) {
    $arguments = [
      'entity_type_id' => $entity_type_id,
    ];
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with($entity_type_id)
      ->willReturn($entity_type);

    $this->assertSame($expected, $this->filter->isApplicable($arguments));
  }

  public function dataProviderIsApplicable() {
    $entityTypeNoBundles = $this->createMock(ContentEntityTypeInterface::class);
    $entityTypeNoBundles->expects($this->any())
      ->method('get')
      ->with('bundle_entity_type')
      ->willReturn('bundle');

    $entityTypeWithBundles = $this->createMock(ContentEntityTypeInterface::class);
    $entityTypeWithBundles->expects($this->any())
      ->method('get')
      ->with('bundle_entity_type')
      ->willReturn('my_bundle_type');

    yield 'without bundles' => ['no_bundles', $entityTypeNoBundles, FALSE];
    yield 'with bundles' => ['with_bundles', $entityTypeWithBundles, TRUE];
  }

  /**
   * @covers ::getSubmittedValue
   */
  public function testGetSubmittedValue() {
    $value = ['wrapper' => ['bundle' => 'my_bundle_value']];
    $this->assertEquals('my_bundle_value', $this->filter->getSubmittedValue($value));
  }

  /**
   * @covers ::buildElement
   * @covers ::getAllBundles
   */
  public function testBuildElement() {
    $this->filter->setEntityTypeId('my_entity_type_id');
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->expects($this->once())
      ->method('getBundleLabel')
      ->willReturn('Bundle Label');
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entityType);
    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type_id')
      ->willReturn([
        'bundle_1' => [
          'label' => 'Bundle 1',
          'translatable' => TRUE,
        ],
        'bundle_2' => [
          'label' => 'Bundle 2',
          'translatable' => TRUE,
        ],
      ]);

    $build = $this->filter->buildElement();
    $this->assertEquals([
      '#type' => 'select',
      '#title' => 'Bundle Label',
      '#default_value' => [],
      '#options' => [
        '' => 'All',
        'bundle_1' => 'Bundle 1',
        'bundle_2' => 'Bundle 2',
      ],
      '#multiple' => TRUE,
    ],
      $build);
  }

  /**
   * @covers ::filter
   */
  public function testFilterWithNoBundles() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('get')
      ->with('bundle_entity_type')
      ->willReturn('bundle');
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    // Assert that condition is called filtering by the undefined language.
    $select->expects($this->never())
      ->method('condition')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], ['value'], $select);
  }

  /**
   * @covers ::filter
   */
  public function testFilter() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('get')
      ->with('bundle_entity_type')
      ->willReturn('my_bundle');
    $entity_type->expects($this->any())
      ->method('getKey')
      ->with('bundle')
      ->willReturn('my_bundle');
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $unionQuery = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    // Assert that condition is called filtering by the undefined language.
    $select->expects($this->any())
      ->method('condition')
      ->with('entity_table.my_bundle', ['value'], 'IN')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('getUnion')
      ->willReturn([
        ['query' => $unionQuery],
      ]);
    $unionQuery->expects($this->once())
      ->method('condition')
      ->with('entity_table.my_bundle', ['value'], 'IN')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], ['value'], $select);
  }

}
