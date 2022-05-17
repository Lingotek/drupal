<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\AssignProfile;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the assign profile bulk action form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\AssignProfile
 * @group lingotek
 * @preserve GlobalState disabled
 */
class AssignProfileTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\AssignProfile
   */
  protected $action;

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
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageLocaleMapper;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * The mocked translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $translation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeBundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->languageLocaleMapper = $this->createMock(LanguageLocaleMapperInterface::class);
    $this->setupAction();
  }

  protected function setupAction($profile_id = 'custom_profile') {
    $this->action = new AssignProfile([], 'change_profile', [
      'id' => 'change_profile',
      'profile_id' => $profile_id,
    ], $this->entityTypeManager, $this->languageManager, $this->languageLocaleMapper, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $this->translation = $this->getStringTranslationStub();
    $this->action->setStringTranslation($this->translation);

    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->action->setMessenger($this->messenger);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithNoTranslatableContent() {
    $context = [];
    $entityType = $this->createMock(ContentEntityTypeInterface::class);
    $entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('My entity type');
    $entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn('My bundle label');
    $entityType->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(FALSE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type');
    $entity->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entity->expects($this->any())
      ->method('label')
      ->willReturn('My entity');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
        ],
      ]);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('Cannot change profile for @type %label. That @bundle_label is not enabled for translation.', [
        '@type' => 'My Bundle',
        '%label' => 'My entity',
        '@bundle_label' => 'My bundle label',
      ]));
    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithNoTranslatableConfiguredContent() {
    $context = [];
    $entityType = $this->createMock(ContentEntityTypeInterface::class);
    $entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('My entity type');
    $entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn('My bundle label');
    $entityType->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type');
    $entity->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entity->expects($this->any())
      ->method('label')
      ->willReturn('My entity');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
          'translatable' => TRUE,
        ],
      ]);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(FALSE);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('Cannot change profile for @type %label. That @bundle_label is not enabled for Lingotek translation.', [
        '@type' => 'My Bundle',
        '%label' => 'My entity',
        '@bundle_label' => 'My bundle label',
      ]));
    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingle() {
    $context = [];
    $entityType = $this->createMock(ContentEntityTypeInterface::class);
    $entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('My entity type');
    $entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn('My bundle label');
    $entityType->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type');
    $entity->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entity->expects($this->any())
      ->method('label')
      ->willReturn('My entity');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
          'translatable' => TRUE,
        ],
      ]);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);

    $this->lingotekConfiguration->expects($this->once())
      ->method('setProfile')
      ->with($entity, 'custom_profile', TRUE)
      ->willReturn(TRUE);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->never())
      ->method('addWarning');

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertTrue($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithDisabledProfile() {
    $this->setupAction('disabled');
    $context = [];
    $entityType = $this->createMock(ContentEntityTypeInterface::class);
    $entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('My entity type');
    $entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn('My bundle label');
    $entityType->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type');
    $entity->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entity->expects($this->any())
      ->method('label')
      ->willReturn('My entity');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
          'translatable' => TRUE,
        ],
      ]);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);

    $this->lingotekConfiguration->expects($this->once())
      ->method('setProfile')
      ->with($entity, 'disabled', TRUE)
      ->willReturn(TRUE);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('setSourceStatus')
      ->with($entity, 'DISABLED')
      ->willReturn(TRUE);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('setTargetStatuses')
      ->with($entity, 'DISABLED')
      ->willReturn(TRUE);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->never())
      ->method('addWarning');
    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertTrue($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleAfterDisabledProfileWithoutDocId() {
    $context = [];
    $entityType = $this->createMock(ContentEntityTypeInterface::class);
    $entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('My entity type');
    $entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn('My bundle label');
    $entityType->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type');
    $entity->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entity->expects($this->any())
      ->method('label')
      ->willReturn('My entity');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
          'translatable' => TRUE,
        ],
      ]);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);

    $this->lingotekConfiguration->expects($this->once())
      ->method('setProfile')
      ->with($entity, 'custom_profile', TRUE)
      ->willReturn(TRUE);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('getSourceStatus')
      ->with($entity)
      ->willReturn('DISABLED');
    $this->lingotekContentTranslation->expects($this->once())
      ->method('getDocumentId')
      ->with($entity)
      ->willReturn(NULL);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('setSourceStatus')
      ->with($entity, 'CURRENT');
    $this->lingotekContentTranslation->expects($this->once())
      ->method('checkTargetStatuses')
      ->with($entity);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->never())
      ->method('addWarning');
    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertTrue($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleAfterDisabledProfileWithDocId() {
    $context = [];
    $entityType = $this->createMock(ContentEntityTypeInterface::class);
    $entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('My entity type');
    $entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn('My bundle label');
    $entityType->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type');
    $entity->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entity->expects($this->any())
      ->method('label')
      ->willReturn('My entity');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
          'translatable' => TRUE,
        ],
      ]);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);

    $this->lingotekConfiguration->expects($this->once())
      ->method('setProfile')
      ->with($entity, 'custom_profile', TRUE)
      ->willReturn(TRUE);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('getSourceStatus')
      ->with($entity)
      ->willReturn('DISABLED');
    $this->lingotekContentTranslation->expects($this->once())
      ->method('getDocumentId')
      ->with($entity)
      ->willReturn('my_document_id');
    $this->lingotekContentTranslation->expects($this->once())
      ->method('setSourceStatus')
      ->with($entity, 'CURRENT');
    $this->lingotekContentTranslation->expects($this->once())
      ->method('checkTargetStatuses')
      ->with($entity);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->never())
      ->method('addWarning');
    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertTrue($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithException() {
    $context = [];
    $entityType = $this->createMock(ContentEntityTypeInterface::class);
    $entityType->expects($this->any())
      ->method('getLabel')
      ->willReturn('My entity type');
    $entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn('My bundle label');
    $entityType->expects($this->once())
      ->method('isTranslatable')
      ->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type');
    $entity->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entity->expects($this->any())
      ->method('label')
      ->willReturn('My entity');
    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
          'translatable' => TRUE,
        ],
      ]);
    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);

    $this->lingotekConfiguration->expects($this->once())
      ->method('setProfile')
      ->with($entity, 'custom_profile', TRUE)
      ->willThrowException(new LingotekApiException('error calling api'));
    $this->lingotekContentTranslation->expects($this->never())
      ->method('getSourceStatus')
      ->with($entity)
      ->willReturn('DISABLED');
    $this->lingotekContentTranslation->expects($this->never())
      ->method('getDocumentId')
      ->with($entity)
      ->willReturn('my_document_id');
    $this->lingotekContentTranslation->expects($this->never())
      ->method('setSourceStatus')
      ->with($entity, 'CURRENT');
    $this->lingotekContentTranslation->expects($this->never())
      ->method('checkTargetStatuses')
      ->with($entity);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('The Translation Profile change for @entity_type %title failed. Please try again.', [
        '@entity_type' => 'my_entity_type',
        '%title' => 'My entity',
      ]));

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

}
