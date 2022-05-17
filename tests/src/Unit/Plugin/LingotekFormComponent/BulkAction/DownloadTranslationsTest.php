<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekProfileInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DownloadTranslations;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for the assign profile bulk action form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DownloadTranslations
 * @group lingotek
 * @preserve GlobalState disabled
 */
class DownloadTranslationsTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DownloadTranslations
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
    $this->action = new DownloadTranslations([], 'download_translations', [
      'id' => 'download_translations',
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

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('Cannot download translations for @type %label. That @bundle_label is not enabled for translation.', [
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

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(FALSE);

    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with($this->translation->translate('Cannot download translations for @type %label. That @bundle_label is not enabled for Lingotek translation.', [
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
    $en = $this->createMock(LanguageInterface::class);
    $en->expects($this->any())
      ->method('getId')
      ->willReturn('en');
    $it = $this->createMock(LanguageInterface::class);
    $it->expects($this->any())
      ->method('getId')
      ->willReturn('it');
    $ca = $this->createMock(LanguageInterface::class);
    $ca->expects($this->any())
      ->method('getId')
      ->willReturn('ca');

    $languages = ['en' => $en, 'it' => $it, 'ca' => $ca];
    $this->languageManager->expects($this->once())
      ->method('getLanguages')
      ->willReturn($languages);

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
    $entity->expects($this->any())
      ->method('language')
      ->willReturn($en);

    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type')
      ->willReturn([
        'my_bundle' => [
          'label' => 'My Bundle',
          'translatable' => TRUE,
        ],
      ]);

    $this->languageLocaleMapper->expects($this->exactly(2))
      ->method('getLocaleForLangcode')
      ->withConsecutive(['it'], ['ca'])
      ->willReturnOnConsecutiveCalls('it-IT', 'ca-ES');

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);

    $this->lingotekContentTranslation->expects($this->exactly(2))
      ->method('checkTargetStatus')
      ->withConsecutive([$entity, 'it'], [$entity, 'ca'])
      ->willReturn(TRUE);

    $this->lingotekContentTranslation->expects($this->exactly(2))
      ->method('downloadDocument')
      ->withConsecutive([$entity, 'it-IT'], [$entity, 'ca-ES'])
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
    $en = $this->createMock(LanguageInterface::class);
    $en->expects($this->any())
      ->method('getId')
      ->willReturn('en');
    $it = $this->createMock(LanguageInterface::class);
    $it->expects($this->any())
      ->method('getId')
      ->willReturn('it');
    $ca = $this->createMock(LanguageInterface::class);
    $ca->expects($this->any())
      ->method('getId')
      ->willReturn('ca');

    $languages = ['en' => $en, 'it' => $it, 'ca' => $ca];
    $this->languageManager->expects($this->once())
      ->method('getLanguages')
      ->willReturn($languages);

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
    $entity->expects($this->any())
      ->method('language')
      ->willReturn($en);

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

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('checkTargetStatus')
      ->with($entity, 'it')
      ->willReturn(TRUE);
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
    $en = $this->createMock(LanguageInterface::class);
    $en->expects($this->any())
      ->method('getId')
      ->willReturn('en');
    $it = $this->createMock(LanguageInterface::class);
    $it->expects($this->any())
      ->method('getId')
      ->willReturn('it');
    $ca = $this->createMock(LanguageInterface::class);
    $ca->expects($this->any())
      ->method('getId')
      ->willReturn('ca');

    $languages = ['en' => $en, 'it' => $it, 'ca' => $ca];
    $this->languageManager->expects($this->once())
      ->method('getLanguages')
      ->willReturn($languages);

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
    $entity->expects($this->any())
      ->method('language')
      ->willReturn($en);

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

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type', 'my_bundle')
      ->willReturn(TRUE);
    $this->lingotekConfiguration->expects($this->once())
      ->method('getEntityProfile')
      ->with($entity, FALSE)
      ->willReturn($profile);
    $this->lingotekContentTranslation->expects($this->once())
      ->method('checkTargetStatus')
      ->with($entity, 'it')
      ->willReturn(TRUE);
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
