<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\Plugin\RelatedEntitiesDetector\HtmlLinkDetector;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit test for the html links entity detector plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\RelatedEntitiesDetector\HtmlLinkDetector
 * @group lingotek
 * @preserve GlobalState disabled
 */
class HtmlLinkDetectorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\RelatedEntitiesDetector\HtmlLinkDetector
   */
  protected $detector;

  /**
   * The mocked module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The mocked entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityRepository;

  /**
   * The mocked entity field manager
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityFieldManager;

  /**
   * The lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekConfiguration;

  /**
   * A Symfony request instance
   *
   * @var \Symfony\Component\HttpFoundation\Request|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $request;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The Drupal Path Validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $pathValidator;

  /**
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->request = $this->createMock(Request::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->pathValidator = $this->createMock(PathValidatorInterface::class);

    $this->detector = new HtmlLinkDetector([], 'html_link_detector', [], $this->entityRepository, $this->entityFieldManager, $this->lingotekConfiguration, $this->request, $this->entityTypeManager, $this->pathValidator);
    $this->entityType = $this->createMock(ContentEntityTypeInterface::class);
    $this->entityType->expects($this->any())
      ->method('hasKey')
      ->with('langcode')
      ->willReturn(TRUE);
    $this->entityType->expects($this->any())
      ->method('id')
      ->willReturn('bundle_id');
    $this->entityType->expects($this->any())
      ->method('getBundleEntityType')
      ->willReturn('entity_id');
    $this->entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('Entity');
    $this->request->expects($this->any())
      ->method('getSchemeAndHttpHost')
      ->willReturn('http://example.com');
    $this->request->expects($this->any())
      ->method('getBasePath')
      ->willReturn('');
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $detector = new HtmlLinkDetector([], 'html_link_detector', [], $this->entityRepository, $this->entityFieldManager, $this->lingotekConfiguration, $this->request, $this->entityTypeManager, $this->pathValidator);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $requestStack = $this->createMock(RequestStack::class);
    $requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($this->request);
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(6))
      ->method('get')
      ->withConsecutive(['entity.repository'], ['entity_field.manager'], ['lingotek.configuration'], ['request_stack'], ['entity_type.manager'], ['path.validator'])
      ->willReturnOnConsecutiveCalls($this->entityRepository, $this->entityFieldManager, $this->lingotekConfiguration, $requestStack, $this->entityTypeManager, $this->pathValidator);
    $detector = HtmlLinkDetector::create($container, [], 'html_link_detector', []);
    $this->assertNotNull($detector);
  }

  /**
   * @covers ::extract
   */
  public function testRunWithoutTextFields() {
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn('entity_reference');
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn([$titleFieldDefinition]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->getBundleEntityType());
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('uuid')
      ->willReturn('this-is-my-uuid');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn($this->entityType->id());
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $entities = [];
    $related = [];
    $visited = [];
    $this->assertEmpty($entities);
    $this->detector->extract($entity, $entities, $related, 1, $visited);
    $this->assertCount(1, $entities);
    $this->assertCount(1, $entities['entity_id']);
    $this->assertEquals($entities['entity_id'][1], $entity);
  }

  /**
   * @covers ::extract
   * @dataProvider dataProviderFieldTypes
   */
  public function testRunExtract($fieldType, $hasSummary) {
    $this->lingotekConfiguration->expects($this->exactly(3))
      ->method('isEnabled')
      ->withConsecutive(['the_first_type', 'first_bundle'], ['entity_id', 'second_bundle'], ['entity_id', 'third_bundle'])
      ->willReturnOnConsecutiveCalls(TRUE, TRUE, FALSE);
    $titleFieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $titleFieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn($fieldType);
    $titleFieldDefinition->expects($this->any())
      ->method('getName')
      ->willReturn('Title');

    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn(['title' => $titleFieldDefinition]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($this->entityType->getBundleEntityType());
    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->any())
      ->method('uuid')
      ->willReturn('this-is-my-uuid');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn($this->entityType->id());
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();

    $firstEntity = $this->createMock(ContentEntityInterface::class);
    $firstEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('the_first_type');
    $firstEntity->expects($this->any())
      ->method('id')
      ->willReturn(8);
    $firstEntity->expects($this->any())
      ->method('uuid')
      ->willReturn('the-first-entity-uuid');
    $firstEntity->expects($this->any())
      ->method('bundle')
      ->willReturn('first_bundle');
    $firstEntity->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $firstEntity->expects($this->exactly(2))
      ->method('getUntranslated')
      ->willReturnSelf();

    $secondEntity = $this->createMock(ContentEntityInterface::class);
    $secondEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('entity_id');
    $secondEntity->expects($this->any())
      ->method('id')
      ->willReturn(2);
    $secondEntity->expects($this->any())
      ->method('uuid')
      ->willReturn('the-second-entity-uuid');
    $secondEntity->expects($this->any())
      ->method('bundle')
      ->willReturn('second_bundle');
    $secondEntity->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $secondEntity->expects($this->exactly(2))
      ->method('getUntranslated')
      ->willReturnSelf();

    $thirdEntity = $this->createMock(ContentEntityInterface::class);
    $thirdEntity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('entity_id');
    $thirdEntity->expects($this->any())
      ->method('id')
      ->willReturn(2);
    $thirdEntity->expects($this->any())
      ->method('uuid')
      ->willReturn('the-third-entity-uuid');
    $thirdEntity->expects($this->any())
      ->method('bundle')
      ->willReturn('third_bundle');
    $thirdEntity->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);
    $thirdEntity->expects($this->never())
      ->method('getUntranslated')
      ->willReturnSelf();

    $this->pathValidator->expects($this->exactly(3))
      ->method('getUrlIfValidWithoutAccessCheck')
      ->withConsecutive(['/the_first_type/8'], ['/entity_id/2'], ['/entity_id/5'])
      ->willReturnOnConsecutiveCalls(
        Url::fromRoute("entity.the_first_type.canonical", ["the_first_type" => 8]),
        Url::fromRoute("entity.entity_id.canonical", ["entity_id" => 2]),
        Url::fromRoute("entity.entity_id.canonical", ["entity_id" => 5])
      );

    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->exactly(3))
      ->method('load')
      ->withConsecutive([8], [2], [5])
      ->willReturnOnConsecutiveCalls($firstEntity, $secondEntity, $thirdEntity);

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($entityStorage);

    $this->entityRepository->expects($this->exactly(3))
      ->method('loadEntityByUuid')
      ->withConsecutive(['the_first_type', 'the-first-entity-uuid'], ['entity_id', 'the-second-entity-uuid'], ['entity_id', 'the-third-entity-uuid'])
      ->willReturnOnConsecutiveCalls($firstEntity, $secondEntity, $thirdEntity);

    $data = [
      (object) [
        'value' => '<p>This is a text with a relative link <a href="/the_first_type/8">to a content</a> </p>',
      ],
      (object) [
        'value' => '<p>This is a text with an absolute link <a href="http://example.com/entity_id/2">to a different content</a> </p>' .
        '<a href="/entity_id/5">another link</a>',
      ],
    ];
    if ($hasSummary) {
      $data = [
        (object) [
          'value' => 'No link',
          'summary' => '<p>This is a text with a relative link <a href="/the_first_type/8">to a content</a> </p>',
        ],
        (object) [
          'value' => '<p>This is a text with a link an absolute link <a href="http://example.com/entity_id/2">to a different content</a> </p>',
          'summary' => '<a href="/entity_id/5">another link</a>',
        ],
      ];
    }
    $entity->expects($this->once())
      ->method('get')
      ->with('title')
      ->willReturn($data);

    $entities = [];
    $related = [];
    $visited = [];
    $this->assertEmpty($entities);
    $this->detector->extract($entity, $entities, $related, 1, $visited);
    // Entities from 2 different entity types.
    $this->assertCount(2, $entities);
    // Total of three entities.
    $this->assertCount(2, $entities['entity_id']);
    $this->assertCount(1, $entities['the_first_type']);
    $this->assertEquals($entities['entity_id'][1], $entity);
    $this->assertEquals($entities['entity_id'][2], $secondEntity);
    $this->assertEquals($entities['the_first_type'][8], $firstEntity);
  }

  /**
   * Data provider for testRunExtract.
   *
   * @return array
   *   [field_type, hasSummary]
   */
  public function dataProviderFieldTypes() {
    yield 'text field' => ['text', FALSE];
    yield 'long text field' => ['text_long', FALSE];
    yield 'text with summary field' => ['text_with_summary', TRUE];
  }

}
