<?php

namespace Drupal\Tests\lingotek\Unit\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\lingotek\Form\LingotekManagementForm;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionManager;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionOptionManager;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldManager;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterManager;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Profile;
use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Title;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\lingotek\Form\LingotekManagementForm
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekManagementFormTest extends UnitTestCase {

  /**
   * @var \Drupal\lingotek\Form\LingotekManagementForm
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

  /**
   * The form component filter manager.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentFilterManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formComponentFilterManager;

  /**
   * The form component bulk actions manager.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formComponentActionsManager;

  /**
   * The form component bulk action options manager.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionOptionManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formComponentActionOptionsManager;

  /**
   * Available form-bulk-actions executor.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formBulkActionExecutor;

  /**
   * {@inheritdoc}
   */
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
    $this->formComponentFilterManager = $this->createMock(LingotekFormComponentFilterManager::class);
    $this->formComponentActionsManager = $this->createMock(LingotekFormComponentBulkActionManager::class);
    $this->formComponentActionOptionsManager = $this->createMock(LingotekFormComponentBulkActionOptionManager::class);
    $this->formBulkActionExecutor = $this->createMock(LingotekFormComponentBulkActionExecutor::class);

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

    $this->formComponentFilterManager->expects($this->any())
      ->method('getApplicable')
      ->with(['form_id' => 'lingotek_management', 'entity_type_id' => 'node'])
      ->willReturn([]);

    $plugin = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $plugin->expects($this->any())
      ->method('getGroup')
      ->willReturn('Request translations');
    $plugin->expects($this->any())
      ->method('getTitle')
      ->willReturn('Request xxx');
    $this->formComponentActionsManager->expects($this->any())
      ->method('getApplicable')
      ->with(['form_id' => 'lingotek_management', 'entity_type_id' => 'node'])
      ->willReturn([
        'request_translations' => $plugin,
        'request_translation:en' => $plugin,
      ]);

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
      $this->formComponentFieldManager,
      $this->formComponentFilterManager,
      $this->formComponentActionsManager,
      $this->formComponentActionOptionsManager,
      $this->formBulkActionExecutor
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
    $request_stack = $this->createMock(RequestStack::class);
    $request = $this->createMock(Request::class);
    $request->expects($this->any())
      ->method('getRequestUri')
      ->willReturn('/my-request-uri');
    $request_stack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($request);
    $container->set('request_stack', $request_stack);
    $path = $this->createMock(Url::class);
    $path->expects($this->any())
      ->method('getOptions')
      ->willReturn([]);
    $pathValidator = $this->createMock(PathValidatorInterface::class);
    $pathValidator->expects($this->any())
      ->method('getUrlIfValidWithoutAccessCheck')
      ->willReturn($path);
    $container->set('path.validator', $pathValidator);
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
   * @covers ::generateBulkOptions
   */
  public function testGenerateBulkOptions() {
    $options = $this->form->generateBulkOptions();
    $this->assertArrayHasKey('request_translations', $options['Request translations']);
    $this->assertArrayHasKey('request_translation:en', $options['Request translations']);
  }

  /**
   * @covers ::formSubmit
   */
  public function testFormSubmit() {
    $plugin = $this->formComponentActionsManager->getApplicable([
      'form_id' => 'lingotek_management',
      'entity_type_id' => 'node',
    ])['request_translations'];

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $form = [];
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->at(0))
      ->method('getValue')
      ->with('operation')
      ->willReturn('request_translations');
    $formState->expects($this->at(1))
      ->method('getValue')
      ->with('options')
      ->willReturn(['job_id' => 'example_job_id']);
    $formState->expects($this->at(2))
      ->method('getValue')
      ->with(['table'])
      ->willReturn(['1' => '1', '2' => 0, '3' => 0]);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);
    $node = $this->createMock(ContentEntityInterface::class);
    $entities = [$node];
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['1'])
      ->willReturn($entities);
    $this->formBulkActionExecutor->expects($this->once())
      ->method('execute')
      ->with($plugin, $entities, ['job_id' => 'example_job_id'])
      ->willReturn(TRUE);
    $this->form->submitForm($form, $formState);
  }

  /**
   * @covers ::formSubmit
   */
  public function testFormSubmitWithRedirect() {
    /** @var \PHPUnit_Framework_MockObject_MockObject $plugin */
    $plugin = $this->formComponentActionsManager->getApplicable([
      'form_id' => 'lingotek_management',
      'entity_type_id' => 'node',
    ])['request_translations'];
    $plugin->expects($this->once())
      ->method('getPluginDefinition')
      ->willReturn(['redirect' => 'my.redirect.route']);

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $form = [];
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->at(0))
      ->method('getValue')
      ->with('operation')
      ->willReturn('request_translations');
    $formState->expects($this->at(1))
      ->method('getValue')
      ->with('options')
      ->willReturn(['job_id' => 'example_job_id']);
    $formState->expects($this->at(2))
      ->method('getValue')
      ->with(['table'])
      ->willReturn(['1' => '1', '2' => 0, '3' => 0]);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);
    $node = $this->createMock(ContentEntityInterface::class);
    $entities = [$node];
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['1'])
      ->willReturn($entities);
    $this->formBulkActionExecutor->expects($this->once())
      ->method('execute')
      ->with($plugin, $entities, ['job_id' => 'example_job_id'])
      ->willReturn(TRUE);

    $formState->expects($this->once())
      ->method('setRedirect')
      ->with('my.redirect.route', [], [
        'query' => [
          'destination' => '/my-request-uri',
        ],
      ]);

    $this->form->submitForm($form, $formState);
  }

  /**
   * @covers ::formSubmit
   */
  public function testFormSubmitWithRedirectEntity() {
    /** @var \PHPUnit_Framework_MockObject_MockObject $plugin */
    $plugin = $this->formComponentActionsManager->getApplicable([
      'form_id' => 'lingotek_management',
      'entity_type_id' => 'node',
    ])['request_translations'];
    $plugin->expects($this->once())
      ->method('getPluginDefinition')
      ->willReturn(['redirect' => 'entity:my-entity-link']);

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $form = [];
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->at(0))
      ->method('getValue')
      ->with('operation')
      ->willReturn('request_translations');
    $formState->expects($this->at(1))
      ->method('getValue')
      ->with('options')
      ->willReturn(['job_id' => 'example_job_id']);
    $formState->expects($this->at(2))
      ->method('getValue')
      ->with(['table'])
      ->willReturn(['1' => '1', '2' => 0, '3' => 0]);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->expects($this->once())
      ->method('getLinkTemplate')
      ->with('my-entity-link')
      ->willReturn('/addasd');
    $entity->expects($this->once())
      ->method('getEntityType')
      ->willReturn($entityType);
    $entities = [$entity];
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['1'])
      ->willReturn($entities);
    $this->formBulkActionExecutor->expects($this->once())
      ->method('execute')
      ->with($plugin, $entities, ['job_id' => 'example_job_id'])
      ->willReturn(TRUE);

    $formState->expects($this->once())
      ->method('setRedirectUrl');

    $this->form->submitForm($form, $formState);
  }

  /**
   * @covers ::formSubmit
   */
  public function testFormSubmitWithEntitiesExecuteReturningFalseKeepsSelection() {
    /** @var \PHPUnit_Framework_MockObject_MockObject $plugin */
    $plugin = $this->formComponentActionsManager->getApplicable([
      'form_id' => 'lingotek_management',
      'entity_type_id' => 'node',
    ])['request_translations'];

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $form = [];
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->at(0))
      ->method('getValue')
      ->with('operation')
      ->willReturn('request_translations');
    $formState->expects($this->at(1))
      ->method('getValue')
      ->with('options')
      ->willReturn(['job_id' => 'example_job_id']);
    $formState->expects($this->at(2))
      ->method('getValue')
      ->with(['table'])
      ->willReturn(['2' => 0, '3' => 0]);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with([])
      ->willReturn([]);
    $this->formBulkActionExecutor->expects($this->once())
      ->method('execute')
      ->with($plugin, [], ['job_id' => 'example_job_id'])
      ->willReturn(FALSE);

    $formState->expects($this->once())
      ->method('setRebuild');

    $this->form->submitForm($form, $formState);
  }

}
