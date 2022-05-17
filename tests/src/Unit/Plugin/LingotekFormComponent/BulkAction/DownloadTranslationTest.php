<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekProfileInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DownloadTranslation;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for the assign profile bulk action form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DownloadTranslation
 * @group lingotek
 * @preserve GlobalState disabled
 */
class DownloadTranslationTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DownloadTranslation
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
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageLocaleMapper;

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
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeBundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->languageLocaleMapper = $this->createMock(LanguageLocaleMapperInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->setupAction();
  }

  protected function setupAction($langcode = 'it') {
    $this->action = new DownloadTranslation([], 'download_translation', [
      'id' => 'download_translation',
      'langcode' => $langcode,
    ], $this->entityTypeManager, $this->languageManager, $this->languageLocaleMapper, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $this->translation = $this->getStringTranslationStub();
    $this->action->setStringTranslation($this->translation);

    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->action->setMessenger($this->messenger);

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->action->setLogger($this->logger);
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
      ->method('id')
      ->willReturn(23);
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

    $entityStorage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('my_entity_type')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->once())
      ->method('load')
      ->with(23)
      ->willReturn($entity);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('Cannot download @type %label translation for @language. That @bundle_label is not enabled for translation.', [
        '@type' => 'My Bundle',
        '%label' => 'My entity',
        '@bundle_label' => 'My bundle label',
        '@language' => 'it',
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
      ->method('id')
      ->willReturn(23);
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

    $entityStorage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('my_entity_type')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->once())
      ->method('load')
      ->with(23)
      ->willReturn($entity);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(FALSE);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('Cannot download @type %label translation for @language. That @bundle_label is not enabled for Lingotek translation.', [
        '@type' => 'My Bundle',
        '%label' => 'My entity',
        '@bundle_label' => 'My bundle label',
        '@language' => 'it',
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
      ->method('id')
      ->willReturn(23);
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

    $this->languageLocaleMapper->expects($this->once())
      ->method('getLocaleForLangcode')
      ->with('it')
      ->willReturn('it-IT');

    $entityStorage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('my_entity_type')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->once())
      ->method('load')
      ->with(23)
      ->willReturn($entity);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('downloadDocument')
      ->with($entity, 'it-IT')
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
      ->method('id')
      ->willReturn(23);
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

    $this->languageLocaleMapper->expects($this->once())
      ->method('getLocaleForLangcode')
      ->with('it')
      ->willReturn('it-IT');

    $entityStorage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('my_entity_type')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->once())
      ->method('load')
      ->with(23)
      ->willReturn($entity);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('downloadDocument')
      ->with($entity, 'it-IT')
      ->willThrowException(new LingotekApiException('error calling api'));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('The download for @entity_type %title translation failed. Please try again.', [
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
      ->method('id')
      ->willReturn(23);
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
    $this->languageLocaleMapper->expects($this->once())
      ->method('getLocaleForLangcode')
      ->with('it')
      ->willReturn('it-IT');

    $entityStorage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('my_entity_type')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->once())
      ->method('load')
      ->with(23)
      ->willReturn($entity);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('downloadDocument')
      ->with($entity, 'it-IT')
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

  public function testExecuteSingleWithLingotekContentEntityStorageException() {
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
      ->method('id')
      ->willReturn(23);
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
    $this->languageLocaleMapper->expects($this->once())
      ->method('getLocaleForLangcode')
      ->with('it')
      ->willReturn('it-IT');

    $entityStorage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('my_entity_type')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->once())
      ->method('load')
      ->with(23)
      ->willReturn($entity);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('downloadDocument')
      ->with($entity, 'it-IT')
      ->willThrowException(new LingotekContentEntityStorageException($entity));

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->logger->expects($this->once())
      ->method('error')
      ->with('The download for @entity_type %title failed because of the length of one field translation (%locale) value: %table.', [
        '@entity_type' => 'my_entity_type',
        '%title' => 'My entity',
        '%locale' => 'it-IT',
        '%table' => '',
      ]);

    $this->messenger->expects($this->once())
      ->method('addError')
      ->with($this->translation->translate('The download for @entity_type %title failed because of the length of one field translation (%locale) value: %table.', [
        '@entity_type' => 'my_entity_type',
        '%title' => 'My entity',
        '%locale' => 'it-IT',
        '%table' => '',
      ]));

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
      ->method('id')
      ->willReturn(23);
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
    $this->languageLocaleMapper->expects($this->once())
      ->method('getLocaleForLangcode')
      ->with('it')
      ->willReturn('it-IT');

    $entityStorage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('my_entity_type')
      ->willReturn($entityStorage);

    $entityStorage->expects($this->once())
      ->method('load')
      ->with(23)
      ->willReturn($entity);

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn(NULL);

    $this->lingotekContentTranslation->expects($this->never())
      ->method('downloadDocument');

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
