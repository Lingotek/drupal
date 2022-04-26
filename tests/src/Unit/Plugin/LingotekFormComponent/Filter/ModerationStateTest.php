<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Filter;

use Drupal\content_moderation\ModerationInformation;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Filter\ModerationState;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\workflows\WorkflowTypeInterface;

/**
 * Unit test for the moderation state query filter form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\ModerationState
 * @group lingotek
 * @preserve GlobalState disabled
 */
class ModerationStateTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Filter\ModerationState
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
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moderationInformation;

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
    $this->moderationInformation = $this->createMock(ModerationInformation::class);
    $this->filter = new ModerationState([], 'moderation_state', ['id' => 'moderation_state', 'title' => 'Moderation State', 'group' => 'Advanced options'], $this->entityTypeManager, $this->entityTypeBundleInfo, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->connection, $this->moduleHandler, $this->moderationInformation);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->filter->setStringTranslation($translation);
  }

  /**
   * @covers ::isApplicable
   * @dataProvider dataProviderIsApplicable
   */
  public function testIsApplicable($module_exists, $moderation_enabled, $expected) {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $arguments = [
      'entity_type_id' => 'my_entity_type_id',
    ];
    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->with('content_moderation')
      ->willReturn($module_exists);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entityType);
    $this->moderationInformation->expects($this->any())
      ->method('isModeratedEntityType')
      ->with($entityType)
      ->willReturn($moderation_enabled);

    $this->assertSame($expected, $this->filter->isApplicable($arguments));
  }

  public function dataProviderIsApplicable() {
    yield 'no content_moderation module' => [FALSE, 'my_entity_type', FALSE];
    yield 'content_moderation module, but moderation enabled for entity type' => [TRUE, FALSE, FALSE];
    yield 'content_moderation module and moderation enabled entity type' => [TRUE, TRUE, TRUE];
  }

  /**
   * @covers ::getSubmittedValue
   */
  public function testGetSubmittedValue() {
    $value = ['advanced_options' => ['moderation_state' => 'draft']];
    $this->assertEquals('draft', $this->filter->getSubmittedValue($value));
  }

  /**
   * @covers ::buildElement
   */
  public function testBuildElement() {
    $workflow = $this->createMock(WorkflowInterface::class);
    $workflowType = $this->createMock(WorkflowTypeInterface::class);
    $draftState = $this->createMock(StateInterface::class);
    $draftState->expects($this->any())
      ->method('label')
      ->willReturn('Draft');
    $publishedState = $this->createMock(StateInterface::class);
    $publishedState->expects($this->any())
      ->method('label')
      ->willReturn('Published');

    $workflow->expects($this->any())
      ->method('getTypePlugin')
      ->willReturn($workflowType);
    $workflowType->expects($this->any())
      ->method('getStates')
      ->willReturn([
        'draft' => $draftState,
        'published' => $publishedState,
      ]);
    $workflowStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('workflow')
      ->willReturn($workflowStorage);
    $workflowStorage->expects($this->once())
      ->method('load')
      ->with('editorial')
      ->willReturn($workflow);

    $this->filter->setEntityTypeId('my_entity_type_id');
    $build = $this->filter->buildElement();
    $this->assertEquals([
      '#type' => 'select',
      '#title' => 'Moderation State',
      '#default_value' => '',
      '#options' => [
        '' => 'All',
        'draft' => 'Draft',
        'published' => 'Published',
      ],
    ],
      $build);
  }

  /**
   * @covers ::filter
   */
  public function testFilter() {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $content_moderation_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('getKey')
      ->with('id')
      ->willReturn('entity_id');
    $content_moderation_type->expects($this->any())
      ->method('getDataTable')
      ->willReturn('content_moderation_state_field_data');
    $this->entityTypeManager->expects($this->at(0))
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entity_type);
    $this->entityTypeManager->expects($this->at(1))
      ->method('getDefinition')
      ->with('content_moderation_state')
      ->willReturn($content_moderation_type);

    $unionQuery = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();

    $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
    $select->expects($this->once())
      ->method('innerJoin')
      ->with('content_moderation_state_field_data', 'content_moderation_data', "entity_table.entity_id= content_moderation_data.content_entity_id")
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('condition')
      ->with('content_moderation_data.moderation_state', 'draft', '=')
      ->willReturnSelf();

    $select->expects($this->once())
      ->method('getUnion')
      ->willReturn([
        ['query' => $unionQuery],
      ]);
    $unionQuery->expects($this->once())
      ->method('innerJoin')
      ->with('content_moderation_state_field_data', 'content_moderation_data', "entity_table.entity_id= content_moderation_data.content_entity_id")
      ->willReturnSelf();
    $unionQuery->expects($this->once())
      ->method('condition')
      ->with('content_moderation_data.moderation_state', 'draft', '=')
      ->willReturnSelf();

    $this->filter->filter('my_entity_type_id', [], 'draft', $select);
  }

}
