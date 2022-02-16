<?php

namespace Drupal\Tests\lingotek\Unit\Form {

  use Drupal\content_translation\ContentTranslationManagerInterface;
  use Drupal\Core\Database\Connection;
  use Drupal\Core\Database\Query\PagerSelectExtender;
  use Drupal\Core\Database\StatementInterface;
  use Drupal\Core\DependencyInjection\ContainerBuilder;
  use Drupal\Core\Entity\EntityFieldManagerInterface;
  use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
  use Drupal\Core\Entity\EntityTypeManagerInterface;
  use Drupal\Core\Entity\EntityStorageInterface;
  use Drupal\Core\Entity\EntityTypeInterface;
  use Drupal\Core\Extension\ModuleHandlerInterface;
  use Drupal\Core\Form\FormState;
  use Drupal\Core\Language\LanguageManagerInterface;
  use Drupal\Core\State\StateInterface;
  use Drupal\Core\TempStore\PrivateTempStoreFactory;
  use Drupal\language\ConfigurableLanguageInterface;
  use Drupal\lingotek\Form\LingotekManagementForm;
  use Drupal\lingotek\FormComponent\LingotekFormComponentFieldManager;
  use Drupal\lingotek\LanguageLocaleMapperInterface;
  use Drupal\lingotek\LingotekConfigurationServiceInterface;
  use Drupal\lingotek\LingotekContentTranslationServiceInterface;
  use Drupal\lingotek\LingotekInterface;
  use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Profile;
  use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Title;
  use Drupal\Tests\UnitTestCase;

  /**
   * @coversDefaultClass \Drupal\lingotek\Form\LingotekManagementForm
   * @group lingotek
   * @preserveGlobalState disabled
   */
  class LingotekManagementFormTest extends UnitTestCase {

    /**
     * @var LingotekManagementForm
     */
    protected $form;

    /**
     * The connection object on which to run queries.
     *
     * @var \Drupal\Core\Database\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $connection;

    /**
     * The language-locale mapper.
     *
     * @var \Drupal\lingotek\LanguageLocaleMapperInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $languageLocaleMapper;

    /**
     * The entity manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityTypeManager;

    /**
     * The entity field manager.
     *
     * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityFieldManager;

    /**
     * The entity type bundle info.
     *
     * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityTypeBundleInfo;

    /**
     * The language manager.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $languageManager;

    /**
     * The Lingotek configuration service.
     *
     * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $lingotekConfiguration;

    /**
     * The Lingotek service
     *
     * @var \Drupal\lingotek\LingotekInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $lingotek;

    /**
     * The content translation manager.
     *
     * @var \Drupal\content_translation\ContentTranslationManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $contentTranslationManager;

    /**
     * The Lingotek content translation service.
     *
     * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $contentTranslationService;

    /**
     * The tempstore factory.
     *
     * @var \Drupal\Core\TempStore\PrivateTempStoreFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $tempStoreFactory;

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $moduleHandler;

    /**
     * The state key value store.
     *
     * @var \Drupal\Core\State\StateInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $state;

    /**
     * The form component field manager.
     *
     * @var \Drupal\lingotek\FormComponent\LingotekFormComponentFieldManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $formComponentFieldManager;

    protected function setUp(): void {
      parent::setUp();

      $this->connection = $this->getMockBuilder(Connection::class)
        ->disableOriginalConstructor()
        ->getMock();
      $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
      $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
      $this->entityTypeBundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
      $this->languageManager = $this->createMock(LanguageManagerInterface::class);
      $this->lingotek = $this->createMock(LingotekInterface::class);
      $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
      $this->languageLocaleMapper = $this->createMock(LanguageLocaleMapperInterface::class);
      $this->contentTranslationManager = $this->createMock(ContentTranslationManagerInterface::class);
      $this->contentTranslationService = $this->createMock(LingotekContentTranslationServiceInterface::class);
      $this->tempStoreFactory = $this->getMockBuilder(PrivateTempStoreFactory::class)
        ->disableOriginalConstructor()
        ->getMock();
      $this->state = $this->createMock(StateInterface::class);
      $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
      $this->formComponentFieldManager = $this->createMock(LingotekFormComponentFieldManager::class);

      $fieldTitle = $this->createMock(Title::class);
      $fieldTitle->expects($this->any())
        ->method('getHeader')
        ->with('node')
        ->willReturn('Title');
      $fieldProfile = $this->createMock(Profile::class);
      $fieldProfile->expects($this->any())
        ->method('getHeader')
        ->with('node')
        ->willReturn('Profile');

      $this->formComponentFieldManager->expects($this->any())
        ->method('getApplicable')
        ->with(['form_id' => 'lingotek_management', 'entity_type_id' => 'node'])
        ->willReturn(['title' => $fieldTitle, 'profile' => $fieldProfile]);

      $this->form = new LingotekManagementForm(
        $this->connection,
        $this->entityTypeManager,
        $this->languageManager,
        $this->lingotek,
        $this->lingotekConfiguration,
        $this->languageLocaleMapper,
        $this->contentTranslationManager,
        $this->contentTranslationService,
        $this->tempStoreFactory,
        $this->state,
        $this->moduleHandler,
        'node',
        $this->entityFieldManager,
        $this->entityTypeBundleInfo,
        $this->formComponentFieldManager
      );
      $this->form->setConfigFactory($this->getConfigFactoryStub(
        [
          'lingotek.account' => [
            'access_token' => 'token',
            'login_id' => 'test@example.com',
          ],
        ]
      ));
      $this->form->setStringTranslation($this->getStringTranslationStub());

      $container = new ContainerBuilder();
      \Drupal::setContainer($container);
    }

    /**
     * @covers ::getFormId
     */
    public function testGetFormId() {
      $form_id = $this->form->getFormID();
      $this->assertSame('lingotek_management', $form_id);
    }

    /**
     * @covers ::buildForm
     */
    public function testQueryExcludesUndefinedLanguageContent() {
      $select = $this->getMockBuilder(PagerSelectExtender::class)->disableOriginalConstructor()->getMock();
      $select->expects(($this->any()))
        ->method('extend')
        ->with('\Drupal\Core\Database\Query\PagerSelectExtender')
        ->willReturnSelf();
      $select->expects(($this->any()))
        ->method('fields')
        ->with('entity_table', ['id'])
        ->willReturnSelf();

      $statement = $this->createMock(StatementInterface::class);

      // Assert that condition is called filtering by the undefined language.
      $select->expects($this->any())
        ->method('condition')
        ->with('entity_table.langcode', 'und', '!=')
        ->willReturnSelf();
      $select->expects($this->once())
        ->method('limit')
        ->with(10)
        ->willReturnSelf();
      $select->expects($this->once())
        ->method('execute')
        ->willReturn($statement);

      $statement->expects($this->once())
        ->method('fetchCol')
        ->with(0)
        ->willReturn([]);

      $this->connection->expects($this->once())
        ->method('select')
        ->willReturn($select);

      $tempStore = $this->getMockBuilder(PrivateTempStoreFactory::class)->disableOriginalConstructor()->getMock();
      $this->tempStoreFactory->expects($this->at(0))
        ->method('get')
        ->with('lingotek.management.filter.node')
        ->willReturn($tempStore);
      $this->tempStoreFactory->expects($this->at(1))
        ->method('get')
        ->with('lingotek.management.filter.node')
        ->willReturn($tempStore);
      $this->tempStoreFactory->expects($this->at(2))
        ->method('get')
        ->with('lingotek.management.items_per_page')
        ->willReturn($tempStore);
      $this->tempStoreFactory->expects($this->at(3))
        ->method('get')
        ->with('lingotek.management.filter.node')
        ->willReturn($tempStore);
      $this->tempStoreFactory->expects($this->at(4))
        ->method('get')
        ->with('lingotek.management.items_per_page')
        ->willReturn($tempStore);
      $tempStore->expects($this->at(0))
        ->method('get')
        ->with('label')
        ->willReturn(NULL);
      $tempStore->expects($this->at(1))
        ->method('get')
        ->with('bundle')
        ->willReturn(NULL);
      $tempStore->expects($this->at(2))
        ->method('get')
        ->with('job')
        ->willReturn(NULL);
      $tempStore->expects($this->at(3))
        ->method('get')
        ->with('document_id')
        ->willReturn(NULL);
      $tempStore->expects($this->at(4))
        ->method('get')
        ->with('entity_id')
        ->willReturn(NULL);
      $tempStore->expects($this->at(5))
        ->method('get')
        ->with('source_language')
        ->willReturn(NULL);
      $tempStore->expects($this->at(6))
        ->method('get')
        ->with('source_status')
        ->willReturn(NULL);
      $tempStore->expects($this->at(7))
        ->method('get')
        ->with('target_status')
        ->willReturn(NULL);
      $tempStore->expects($this->at(8))
        ->method('get')
        ->with('content_state')
        ->willReturn(NULL);
      $tempStore->expects($this->at(9))
        ->method('get')
        ->with('profile')
        ->willReturn(NULL);

      $entityType = $this->createMock(EntityTypeInterface::class);
      $entityType->expects($this->any())
        ->method('get')
        ->with('bundle_entity_type')
        ->willReturn('node');
      $entityType->expects($this->any())
        ->method('hasLinkTemplate')
        ->with('delete-multiple-form')
        ->willReturn(FALSE);
      $this->entityTypeManager->expects($this->any())
        ->method('getDefinition')
        ->with('node')
        ->willReturn($entityType);

      $entityType->expects($this->any())
        ->method('getKey')
        ->will($this->returnArgument(0));

      $language = $this->createMock(ConfigurableLanguageInterface::class);
      $language->expects($this->any())
        ->method('getId')
        ->willReturn('en');
      $this->languageManager->expects($this->any())
        ->method('getLanguages')
        ->willReturn(['en' => $language]);

      $languageStorage = $this->createMock(EntityStorageInterface::class);
      $languageStorage->expects($this->once())
        ->method('load')
        ->with('en')
        ->willReturn($language);

      $nodeStorage = $this->createMock(EntityStorageInterface::class);

      $this->entityTypeManager->expects($this->exactly(2))
        ->method('getStorage')
        ->withConsecutive(['configurable_language'], ['node'])
        ->willReturnOnConsecutiveCalls($languageStorage, $nodeStorage);
      $nodeStorage->expects($this->once())
        ->method('loadMultiple')
        ->willReturn([]);

      $this->entityTypeBundleInfo->expects($this->once())
        ->method('getBundleInfo')
        ->willReturn([]);

      $this->lingotekConfiguration->expects($this->any())
        ->method('isLanguageEnabled')
        ->willReturn(TRUE);

      $this->lingotekConfiguration->expects($this->any())
        ->method('getProfileOptions')
        ->willReturn(['manual']);

      $this->state->expects($this->once())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->willReturn(FALSE);

      $this->moduleHandler->expects($this->at(0))
        ->method('moduleExists')
        ->with('gnode')
        ->willReturn(FALSE);

      $this->form->buildForm([], new FormState());
    }

    /**
     * @covers ::generateBulkOptions
     */
    public function testGenerateBulkOptions() {
      $entityType = $this->createMock(EntityTypeInterface::class);
      $entityType->expects($this->any())
        ->method('hasLinkTemplate')
        ->with('delete-multiple-form')
        ->willReturn(FALSE);

      $this->entityTypeManager->expects($this->any())
        ->method('getDefinition')
        ->with('node')
        ->willReturn($entityType);

      $this->lingotekConfiguration->expects($this->any())
        ->method('getProfileOptions')
        ->willReturn(['manual']);

      $languageEnabled = $this->createMock(ConfigurableLanguageInterface::class);
      $languageDisabled = $this->createMock(ConfigurableLanguageInterface::class);
      $languageEnabled->expects($this->any())
        ->method('getId')
        ->willReturn('en');
      $languageDisabled->expects($this->any())
        ->method('getId')
        ->willReturn('di');

      $this->languageManager->expects($this->any())
        ->method('getLanguages')
        ->willReturn(['en' => $languageEnabled, 'di' => $languageDisabled]);

      $this->lingotekConfiguration->expects($this->any())
        ->method('isLanguageEnabled')
        ->withConsecutive([$languageEnabled], [$languageDisabled])
        ->willReturnOnConsecutiveCalls(TRUE, FALSE);

      $languageStorage = $this->createMock(EntityStorageInterface::class);
      $languageStorage->expects($this->exactly(2))
        ->method('load')
        ->withConsecutive(['en'], ['di'])
        ->willReturnOnConsecutiveCalls($languageEnabled, $languageDisabled);

      $this->entityTypeManager->expects($this->any())
        ->method('getStorage')
        ->with('configurable_language')
        ->willReturn($languageStorage);

      $options = $this->form->generateBulkOptions();
      $this->assertArrayHasKey('request_translations', $options['Request translations']);
      $this->assertArrayHasKey('request_translation:en', $options['Request translations']);
      $this->assertArrayNotHasKey('request_translation:di', $options['Request translations']);
    }

  }
}
