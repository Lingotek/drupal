<?php

namespace Drupal\Tests\lingotek\Unit\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\lingotek\Form\LingotekJobManagementContentEntitiesForm;
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

/**
 * @coversDefaultClass \Drupal\lingotek\Form\LingotekManagementForm
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekJobManagementContentEntitiesFormTest extends UnitTestCase {

  /**
   * @var \Drupal\lingotek\Form\LingotekJobManagementContentEntitiesForm
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
      ->with(['form_id' => 'lingotek_job_content_entities_management', 'entity_type_id' => 'node'])
      ->willReturn(['title' => $fieldTitle, 'profile' => $fieldProfile]);

    $this->formComponentFilterManager->expects($this->any())
      ->method('getApplicable')
      ->with(['form_id' => 'lingotek_job_content_entities_management', 'entity_type_id' => 'node'])
      ->willReturn([]);

    $this->formComponentActionsManager->expects($this->any())
      ->method('getApplicable')
      ->with(['form_id' => 'lingotek_job_content_entities_management', 'entity_type_id' => 'node'])
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
      ->with(['form_id' => 'lingotek_job_content_entities_management', 'entity_type_id' => 'node'])
      ->willReturn([
        'request_translations' => $plugin,
        'request_translation:en' => $plugin,
      ]);

    $this->form = new LingotekJobManagementContentEntitiesForm(
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
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId() {
    $form_id = $this->form->getFormID();
    $this->assertSame('lingotek_job_content_entities_management', $form_id);
  }

  /**
   * @covers ::generateBulkOptions
   */
  public function testGenerateBulkOptions() {
    $options = $this->form->generateBulkOptions();
    $this->assertEmpty($options);
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildForm() {
    $query = $this->createMock(SelectInterface::class);
    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('lingotek_content_metadata')
      ->willReturn($storage);
    $storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $query->expects($this->once())
      ->method('condition')
      ->with('job_id', 'my_job_id')
      ->willReturnSelf();

    $form = [];
    $formState = $this->createMock(FormStateInterface::class);
    $form = $this->form->buildForm($form, $formState, 'my_job_id');

    $this->assertFalse($form['filters']['wrapper']['job']['#access']);
    $this->assertFalse($form['options']['options']['job_id']['#access']);
    $this->assertSame('my_job_id', $form['options']['options']['job_id']['#value']);
  }

}
