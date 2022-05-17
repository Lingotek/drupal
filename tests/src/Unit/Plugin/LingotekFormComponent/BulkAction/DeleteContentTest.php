<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DeleteContent;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the assign job id bulk action form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DeleteContent
 * @group lingotek
 * @preserve GlobalState disabled
 */
class DeleteContentTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DeleteContent
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
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $tempStoreFactory;

  /**
   * The user currently logged in.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

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
    $this->tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->currentUser->expects($this->any())
      ->method('id')
      ->willReturn(23);

    $this->action = new DeleteContent([], 'delete_content', ['id' => 'delete_content'], $this->entityTypeManager, $this->languageManager, $this->languageLocaleMapper, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo, $this->tempStoreFactory, $this->currentUser);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->action->setStringTranslation($translation);
    $this->action->setEntityTypeId('my_entity');
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicableWithNoEntityType() {
    $this->assertFalse($this->action->isApplicable());
  }

  /**
   * @covers ::isApplicable
   * @dataProvider dataProviderIsApplicable
   */
  public function testIsApplicable($canDelete, $expected) {
    $arguments = ['entity_type_id' => 'my_entity_type_id'];
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->expects($this->once())
      ->method('hasLinkTemplate')
      ->with('delete-multiple-form')
      ->willReturn($canDelete);
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($entityType);
    $this->assertSame($expected, $this->action->isApplicable($arguments));
  }

  public function dataProviderIsApplicable() {
    yield 'no delete link' => [FALSE, FALSE];
    yield 'has delete link' => [TRUE, TRUE];
  }

  /**
   * @covers ::execute
   */
  public function testExecuteWithNoSelection() {
    $entities = [];
    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $tempStore = $this->createMock(PrivateTempStore::class);
    $this->tempStoreFactory->expects($this->once())
      ->method('get')
      ->with('entity_delete_multiple_confirm')
      ->willReturn($tempStore);
    $tempStore->expects($this->once())
      ->method('set')
      ->with('23:my_entity', []);

    $result = $this->action->execute($entities, [], $executor);

    $this->assertTrue($result);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->willReturn('epa');
    $entity1 = $this->createMock(ContentEntityInterface::class);
    $entity1->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity1->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entity1->expects($this->once())
      ->method('id')
      ->willReturn(33);

    $entity2 = $this->createMock(ContentEntityInterface::class);
    $entity2->expects($this->once())
      ->method('getUntranslated')
      ->willReturnSelf();
    $entity2->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entity2->expects($this->once())
      ->method('id')
      ->willReturn(7);
    $entities = [$entity1, $entity2];
    $executor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

    $tempStore = $this->createMock(PrivateTempStore::class);
    $this->tempStoreFactory->expects($this->once())
      ->method('get')
      ->with('entity_delete_multiple_confirm')
      ->willReturn($tempStore);
    $tempStore->expects($this->once())
      ->method('set')
      ->with('23:my_entity', [
        33 => ['epa' => 'epa'],
        7 => ['epa' => 'epa'],
      ]);

    $result = $this->action->execute($entities, [], $executor);

    $this->assertTrue($result);
  }

}
