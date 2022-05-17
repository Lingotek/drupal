<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\Exception\LingotekProcessedWordsLimitException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekProfileInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\RequestTranslations;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the assign profile bulk action form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\RequestTranslations
 * @group lingotek
 * @preserve GlobalState disabled
 */
class RequestTranslationsTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\RequestTranslations
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
    $this->setupAction();
  }

  protected function setupAction($langcode = 'it') {
    $this->action = new RequestTranslations([], 'request_translations', [
      'id' => 'request_translations',
    ], $this->entityTypeManager, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

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
      ->with($this->translation->translate('Cannot request translations for @type %label. That @bundle_label is not enabled for translation.', [
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
      ->with($this->translation->translate('Cannot request translations for @type %label. That @bundle_label is not enabled for Lingotek translation.', [
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

    $profile = $this->createMock(LingotekProfileInterface::class);

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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
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
  public function testExecuteSingleWithApiException() {
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
    $profile = $this->createMock(LingotekProfileInterface::class);

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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willThrowException(new LingotekApiException('error calling api'));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('The request for @entity_type %title translation failed. Please try again.', [
        '@entity_type' => 'my_entity_type',
        '%title' => 'My entity',
      ]));

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithDocumentNotFoundException() {
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
    $profile = $this->createMock(LingotekProfileInterface::class);

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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willThrowException(new LingotekDocumentNotFoundException('error calling api'));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('Document @entity_type %title was not found. Please upload again.', [
        '@entity_type' => 'my_entity_type',
        '%title' => 'My entity',
      ]));

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithDocumentLockedException() {
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
    $profile = $this->createMock(LingotekProfileInterface::class);

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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willThrowException(new LingotekDocumentLockedException('old_doc_id', 'new_doc_id', 'error calling api'));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', [
        '@entity_type' => 'my_entity_type',
        '%title' => 'My entity',
      ]));

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithLingotekProcessedWordsLimitException() {
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
    $profile = $this->createMock(LingotekProfileInterface::class);

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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willThrowException(new LingotekProcessedWordsLimitException('error calling api'));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('Processed word limit exceeded. Please contact your local administrator or Lingotek Client Success (<a href=":link">@mail</a>) for assistance.', [
        ':link' => 'mailto:sales@lingotek.com',
        '@mail' => 'sales@lingotek.com',
      ]));

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithDocumentArchivedException() {
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
    $profile = $this->createMock(LingotekProfileInterface::class);

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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willThrowException(new LingotekDocumentArchivedException('error calling api'));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('Document @entity_type %title has been archived. Uploading again.', [
        '@entity_type' => 'my_entity_type',
        '%title' => 'My entity',
      ]));

    $this->expectException(LingotekDocumentArchivedException::class);

    $result = $this->action->executeSingle($entity, [], $executor, $context);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithPaymentRequiredException() {
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
    $profile = $this->createMock(LingotekProfileInterface::class);

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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willThrowException(new LingotekPaymentRequiredException('error calling api'));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('Community has been disabled. Please contact support@lingotek.com to re-enable your community.', []));

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

  /**
   * @covers ::executeSingle
   */
  public function testExecuteSingleWithNoProfile() {
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
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn(NULL);

    $this->lingotekContentTranslation->expects($this->never())
      ->method('requestTranslations');

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('The @type %label has no profile assigned so it was not processed.', [
        '@type' => 'My Bundle',
        '%label' => 'My entity',
      ]));

    $result = $this->action->executeSingle($entity, [], $executor, $context);
    $this->assertFalse($result);
  }

}
