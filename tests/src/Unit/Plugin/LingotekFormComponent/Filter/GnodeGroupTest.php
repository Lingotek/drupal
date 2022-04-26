<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Filter\GnodeGroup;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the document ID query filter form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\GnodeGroup
 * @group lingotek
 * @preserve GlobalState disabled
 */
class GnodeGroupTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\EntityId
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
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The group relation manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $groupContentEnablerManager;

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
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->filter = new GnodeGroup([], 'group', ['id' => 'group', 'title' => 'Group'], $this->entityTypeManager, $this->entityTypeBundleInfo, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->connection, $this->moduleHandler);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->filter->setStringTranslation($translation);

    $container = new Container();
    $this->groupContentEnablerManager = $this->createMock(GroupContentEnablerManagerInterface::class);
    $container->set('plugin.manager.group_content_enabler', $this->groupContentEnablerManager);
    \Drupal::setContainer($container);

  }

  /**
   * @covers ::isApplicable
   * @dataProvider dataProviderIsApplicable
   */
  public function testIsApplicable($module_exists, $entity_type_id, $expected) {
    $arguments = [
      'entity_type_id' => $entity_type_id,
    ];
    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->with('gnode')
      ->willReturn($module_exists);

    $this->assertSame($expected, $this->filter->isApplicable($arguments));
  }

  public function dataProviderIsApplicable() {
    yield 'no gnode module' => [FALSE, 'my_entity_type', FALSE];
    yield 'gnode module, but no node entity type' => [TRUE, 'my_entity_type', FALSE];
    yield 'gnode module and node entity type' => [TRUE, 'node', TRUE];
  }

  /**
   * @covers ::getSubmittedValue
   */
  public function testGetSubmittedValue() {
    $value = ['wrapper' => ['group' => 'my_group_id']];
    $this->assertEquals('my_group_id', $this->filter->getSubmittedValue($value));
  }

  /**
   * @covers ::buildElement
   * @covers ::getAllGroups
   */
  public function testBuildElement() {
    $group1 = $this->createMock(ContentEntityInterface::class);
    $group1->expects($this->any())
      ->method('id')
      ->willReturn('group_id_1');
    $group1->expects($this->any())
      ->method('label')
      ->willReturn('Group 1');
    $group2 = $this->createMock(ContentEntityInterface::class);
    $group2->expects($this->any())
      ->method('id')
      ->willReturn('group_id_2');
    $group2->expects($this->any())
      ->method('label')
      ->willReturn('Group 2');
    $groupStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('group')
      ->willReturn($groupStorage);
    $groupStorage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn([1 => $group1, 2 => $group2]);

    $this->filter->setEntityTypeId('my_entity_type_id');
    $build = $this->filter->buildElement();
    $this->assertEquals([
      '#type' => 'select',
      '#title' => 'Group',
      '#default_value' => [],
      '#options' => [
        '' => 'All',
        '1' => 'Group 1',
        '2' => 'Group 2',
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
    $entity_type->expects($this->any())
      ->method('getKey')
      ->with('id')
      ->willReturn('entity_id');
    $entity_type->expects($this->any())
      ->method('getDataTable')
      ->willReturn('entity_data_table');
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);

    $groupType = $this->createMock(GroupTypeInterface::class);
    $groupType->expects($this->any())
      ->method('id')
      ->willReturn('group_type_1');
    $group = $this->createMock(GroupInterface::class);
    $group->expects($this->any())
      ->method('id')
      ->willReturn('group_id_1');
    $group->expects($this->any())
      ->method('label')
      ->willReturn('Group 1');
    $group->expects($this->any())
      ->method('getGroupType')
      ->willReturn($groupType);

    $groupStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('group')
      ->willReturn($groupStorage);
    $groupStorage->expects($this->once())
      ->method('load')
      ->willReturn($group);

    $this->groupContentEnablerManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([[
        'id' => 'definition_1',
        'entity_type_id' => 'node',
        'entity_bundle' => 'bundle',
      ],
    ]);

    $unionQuery = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->at(0))
      ->method('innerJoin')
      ->with('group_content_field_data', 'group_content', "entity_table.entity_id= group_content.entity_id")
      ->willReturnSelf();
    $select->expects($this->at(1))
      ->method('condition')
      ->with('group_content.gid', 'group_1', '=')
      ->willReturnSelf();
    $select->expects($this->at(2))
      ->method('condition')
      ->with('group_content.type', ['group_type_1-definition_1-bundle'], 'IN')
      ->willReturnSelf();

    $select->expects($this->once())
      ->method('getUnion')
      ->willReturn([
        ['query' => $unionQuery],
      ]);
    $unionQuery->expects($this->at(0))
      ->method('innerJoin')
      ->with('group_content_field_data', 'group_content', "entity_table.entity_id= group_content.entity_id")
      ->willReturnSelf();
    $unionQuery->expects($this->at(1))
      ->method('condition')
      ->with('group_content.gid', 'group_1', '=')
      ->willReturnSelf();
    $unionQuery->expects($this->at(2))
      ->method('condition')
      ->with('group_content.type', ['group_type_1-definition_1-bundle'], 'IN')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], 'group_1', $select);
  }

}
