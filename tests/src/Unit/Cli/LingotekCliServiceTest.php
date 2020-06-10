<?php

namespace Drupal\Tests\lingotek\Unit\Cli;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageInterface;
use Drupal\lingotek\Cli\LingotekCliService;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversDefaultClass \Drupal\lingotek\Cli\LingotekCliService
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekCliServiceTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $translationService;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $languageLocaleMapper;

  /**
   * The output.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $output;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The service under test.
   *
   * @var \Drupal\lingotek\Cli\LingotekCliService
   */
  protected $cliService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->getMockBuilder(EntityTypeManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->languageLocaleMapper = $this->createMock(LanguageLocaleMapperInterface::class);
    $this->translationService = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->output = $this->createMock(OutputInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->cliService = new LingotekCliService($this->entityTypeManager, $this->translationService, $this->languageLocaleMapper);
    $this->cliService->setupOutput($this->output);
    $this->cliService->setLogger($this->logger);
    $this->cliService->setStringTranslation($this->getStringTranslationStub());
  }

  public function testUploadInvalidEntityTypeId() {
    $this->logger->expects($this->once())
      ->method('error');
    $upload = $this->cliService->upload('xxxx', 1, NULL);
    $this->assertEquals(LingotekCliService::COMMAND_ERROR_ENTITY_TYPE_ID, $upload);
  }

  public function testUploadInvalidEntityId() {
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('xxxx')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('xxxx')
      ->willReturn($entityStorage);
    $this->logger->expects($this->once())
      ->method('error');
    $upload = $this->cliService->upload('xxxx', 1, NULL);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_NOT_FOUND, $upload);
  }

  public function testUpload() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->once())
      ->method('uploadDocument')
      ->with($entity)
      ->willReturn('my-lingotek-uid');
    $this->output->expects($this->once())
      ->method('writeln')
      ->with('my-lingotek-uid');
    $upload = $this->cliService->upload('node', 1, NULL);
    $this->assertEquals($this->cliService::COMMAND_SUCCEDED, $upload);
  }

  public function testUploadWithJobId() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->once())
      ->method('uploadDocument')
      ->with($entity, 'test')
      ->willReturn('my-lingotek-uid');
    $this->output->expects($this->once())
      ->method('writeln')
      ->with('my-lingotek-uid');
    $upload = $this->cliService->upload('node', 1, 'test');
    $this->assertEquals($this->cliService::COMMAND_SUCCEDED, $upload);
  }

  public function testCheckUploadInvalidEntityTypeId() {
    $this->logger->expects($this->once())
      ->method('error');
    $upload = $this->cliService->checkUpload('xxxx', 1);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_TYPE_ID, $upload);
  }

  public function testCheckUploadInvalidEntityId() {
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('xxxx')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('xxxx')
      ->willReturn($entityStorage);
    $this->logger->expects($this->once())
      ->method('error');
    $upload = $this->cliService->checkUpload('xxxx', 1);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_NOT_FOUND, $upload);
  }

  public function testCheckUpload() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->once())
      ->method('checkSourceStatus')
      ->with($entity);
    $this->translationService->expects($this->once())
      ->method('getSourceStatus')
      ->willReturn('CURRENT');
    $this->output->expects($this->once())
      ->method('writeln')
      ->with('CURRENT');
    $upload = $this->cliService->checkUpload('node', 1);
    $this->assertEquals($this->cliService::COMMAND_SUCCEDED, $upload);
  }

  public function testRequestTranslationsInvalidEntityTypeId() {
    $this->logger->expects($this->once())
      ->method('error');
    $upload = $this->cliService->requestTranslations('xxxx', 1);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_TYPE_ID, $upload);
  }

  public function testRequestTranslationsInvalidEntityId() {
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('xxxx')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('xxxx')
      ->willReturn($entityStorage);
    $this->logger->expects($this->once())
      ->method('error');
    $result = $this->cliService->requestTranslations('xxxx', 1, ['all']);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_NOT_FOUND, $result);
  }

  public function testRequestTranslations() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willReturn(['es', 'ca', 'de']);
    $result = $this->cliService->requestTranslations('node', 1);
    $this->assertEquals([
      'es' => ['langcode' => 'es'],
      'ca' => ['langcode' => 'ca'],
      'de' => ['langcode' => 'de'],
    ], $result);
  }

  public function testRequestTranslationsAll() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->once())
      ->method('requestTranslations')
      ->with($entity)
      ->willReturn(['es', 'ca', 'de']);
    $result = $this->cliService->requestTranslations('node', 1, ['all']);
    $this->assertEquals([
      'es' => ['langcode' => 'es'],
      'ca' => ['langcode' => 'ca'],
      'de' => ['langcode' => 'de'],
    ], $result);
  }

  public function testRequestTranslationsSome() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->will($this->returnValueMap([
        ['es', 'es_ES'],
        ['de', 'de_DE'],
    ]));
    $this->translationService->expects($this->at(0))
      ->method('addTarget')
      ->with($entity, 'es_ES')
      ->willReturn(TRUE);
    $this->translationService->expects($this->at(1))
      ->method('addTarget')
      ->with($entity, 'de_DE')
      ->willReturn(TRUE);
    $result = $this->cliService->requestTranslations('node', 1, ['es', 'de']);
    $this->assertEquals([
      'es' => ['langcode' => 'es'],
      'de' => ['langcode' => 'de'],
    ], $result);
  }

  public function testRequestTranslationsUnexistingLanguage() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->will($this->returnValueMap([
        ['es', 'es_ES'],
        ['de', 'de_DE'],
      ]));
    $this->translationService->expects($this->once())
      ->method('addTarget')
      ->with($entity, 'es_ES')
      ->willReturn(TRUE);
    $result = $this->cliService->requestTranslations('node', 1, ['es', 'hu']);
    $this->assertEquals([
      'es' => ['langcode' => 'es'],
    ], $result);
  }

  public function testCheckTranslationsStatusesInvalidEntityTypeId() {
    $this->logger->expects($this->once())
      ->method('error');
    $upload = $this->cliService->checkTranslationsStatuses('xxxx', 1);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_TYPE_ID, $upload);
  }

  public function testCheckTranslationsStatusesInvalidEntityId() {
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('xxxx')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('xxxx')
      ->willReturn($entityStorage);
    $this->logger->expects($this->once())
      ->method('error');
    $result = $this->cliService->checkTranslationsStatuses('xxxx', 1, ['all']);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_NOT_FOUND, $result);
  }

  public function testCheckTranslationsStatuses() {
    /** @var \Drupal\Core\Language\LanguageInterface|\PHPUnit_Framework_MockObject_MockObject $language */
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('en');
    /** @var \Drupal\Core\Entity\EntityInterface|\PHPUnit_Framework_MockObject_MockObject $entity */
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->at(0))
      ->method('checkTargetStatuses')
      ->with($entity);
    $this->translationService->expects($this->at(1))
      ->method('getTargetStatuses')
      ->with($entity)
      ->willReturn([
        'en' => 'CURRENT',
        'es' => 'READY',
        'ca' => 'PENDING',
        'de' => 'ERROR',
      ]);
    $result = $this->cliService->checkTranslationsStatuses('node', 1);
    $this->assertEquals([
      'es' => ['langcode' => 'es', 'status' => 'READY'],
      'ca' => ['langcode' => 'ca', 'status' => 'PENDING'],
      'de' => ['langcode' => 'de', 'status' => 'ERROR'],
    ], $result);
  }

  public function testCheckTranslationsStatusesAll() {
    /** @var \Drupal\Core\Language\LanguageInterface|\PHPUnit_Framework_MockObject_MockObject $language */
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('en');
    /** @var \Drupal\Core\Entity\EntityInterface|\PHPUnit_Framework_MockObject_MockObject $entity */
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->at(0))
      ->method('checkTargetStatuses')
      ->with($entity);
    $this->translationService->expects($this->at(1))
      ->method('getTargetStatuses')
      ->with($entity)
      ->willReturn([
        'en' => 'CURRENT',
        'es' => 'READY',
        'ca' => 'PENDING',
        'de' => 'ERROR',
      ]);
    $result = $this->cliService->checkTranslationsStatuses('node', 1, ['all']);
    $this->assertEquals([
      'es' => ['langcode' => 'es', 'status' => 'READY'],
      'ca' => ['langcode' => 'ca', 'status' => 'PENDING'],
      'de' => ['langcode' => 'de', 'status' => 'ERROR'],
    ], $result);
  }

  public function testCheckTranslationsStatusesSome() {
    /** @var \Drupal\Core\Language\LanguageInterface|\PHPUnit_Framework_MockObject_MockObject $language */
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('en');
    /** @var \Drupal\Core\Entity\EntityInterface|\PHPUnit_Framework_MockObject_MockObject $entity */
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->at(0))
      ->method('checkTargetStatuses')
      ->with($entity);
    $this->translationService->expects($this->at(1))
      ->method('getTargetStatuses')
      ->with($entity)
      ->willReturn([
        'en' => 'CURRENT',
        'es' => 'READY',
        'ca' => 'PENDING',
        'de' => 'ERROR',
      ]);
    $result = $this->cliService->checkTranslationsStatuses('node', 1, ['es', 'de']);
    $this->assertEquals([
      'es' => ['langcode' => 'es', 'status' => 'READY'],
      'de' => ['langcode' => 'de', 'status' => 'ERROR'],
    ], $result);
  }

  public function testCheckTranslationsStatusesUnexistingLanguage() {
    /** @var \Drupal\Core\Language\LanguageInterface|\PHPUnit_Framework_MockObject_MockObject $language */
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('en');
    /** @var \Drupal\Core\Entity\EntityInterface|\PHPUnit_Framework_MockObject_MockObject $entity */
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->at(0))
      ->method('checkTargetStatuses')
      ->with($entity);
    $this->translationService->expects($this->at(1))
      ->method('getTargetStatuses')
      ->with($entity)
      ->willReturn([
        'en' => 'CURRENT',
        'es' => 'READY',
        'ca' => 'PENDING',
        'de' => 'ERROR',
      ]);
    $result = $this->cliService->checkTranslationsStatuses('node', 1, ['es', 'hu']);
    $this->assertEquals([
      'es' => ['langcode' => 'es', 'status' => 'READY'],
    ], $result);
  }

  public function testDownloadTranslationsInvalidEntityTypeId() {
    $this->logger->expects($this->once())
      ->method('error');
    $upload = $this->cliService->downloadTranslations('xxxx', 1);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_TYPE_ID, $upload);
  }

  public function testDownloadTranslationsInvalidEntityId() {
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('xxxx')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('xxxx')
      ->willReturn($entityStorage);
    $this->logger->expects($this->once())
      ->method('error');
    $result = $this->cliService->downloadTranslations('xxxx', 1, ['all']);
    $this->assertEquals($this->cliService::COMMAND_ERROR_ENTITY_NOT_FOUND, $result);
  }

  public function testDownloadTranslations() {
    $this->assertTrue(TRUE);

  }

  public function testDownloadTranslationsAll() {
    /** @var \Drupal\Core\Entity\EntityInterface|\PHPUnit_Framework_MockObject_MockObject $entity */
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->translationService->expects($this->at(0))
      ->method('downloadDocuments')
      ->with($entity);
    $result = $this->cliService->downloadTranslations('node', 1, ['all']);
    $this->assertEquals($this->cliService::COMMAND_SUCCEDED, $result);
  }

  public function testDownloadTranslationsSome() {
    /** @var \Drupal\Core\Entity\EntityInterface|\PHPUnit_Framework_MockObject_MockObject $entity */
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->will($this->returnValueMap([
        ['es', 'es_ES'],
        ['de', 'de_DE'],
      ]));
    $this->translationService->expects($this->at(0))
      ->method('downloadDocument')
      ->with($entity, 'es_ES')
      ->willReturn(TRUE);
    $this->translationService->expects($this->at(1))
      ->method('downloadDocument')
      ->with($entity, 'de_DE')
      ->willReturn(TRUE);
    $result = $this->cliService->downloadTranslations('node', 1, ['es', 'de']);
    $this->assertEquals($this->cliService::COMMAND_SUCCEDED, $result);
  }

  public function testDownloadTranslationsUnexistingLanguage() {
    /** @var \Drupal\Core\Entity\EntityInterface|\PHPUnit_Framework_MockObject_MockObject $entity */
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityStorage = $this->createMock(EntityStorageInterface::class);
    $entityStorage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->once())
      ->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($entityStorage);
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->will($this->returnValueMap([
        ['es', 'es_ES'],
        ['de', 'de_DE'],
      ]));
    $this->translationService->expects($this->once())
      ->method('downloadDocument')
      ->with($entity, 'es_ES')
      ->willReturn(TRUE);
    $result = $this->cliService->downloadTranslations('node', 1, ['es', 'hu']);
    $this->assertEquals($this->cliService::COMMAND_SUCCEDED, $result);
  }

}
