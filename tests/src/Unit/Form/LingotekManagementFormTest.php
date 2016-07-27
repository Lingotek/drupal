<?php

namespace Drupal\Tests\lingotek\Unit\Form {

  use Drupal\content_translation\ContentTranslationManagerInterface;
  use Drupal\Core\Entity\EntityManagerInterface;
  use Drupal\Core\Entity\EntityStorageInterface;
  use Drupal\Core\Entity\EntityTypeInterface;
  use Drupal\Core\Entity\Query\QueryFactory;
  use Drupal\Core\Entity\Query\QueryInterface;
  use Drupal\Core\Form\FormState;
  use Drupal\Core\Language\LanguageManagerInterface;
  use Drupal\Core\State\StateInterface;
  use Drupal\language\ConfigurableLanguageInterface;
  use Drupal\lingotek\Form\LingotekManagementForm;
  use Drupal\lingotek\LanguageLocaleMapperInterface;
  use Drupal\lingotek\LingotekConfigurationServiceInterface;
  use Drupal\lingotek\LingotekContentTranslationServiceInterface;
  use Drupal\lingotek\LingotekInterface;
  use Drupal\Tests\UnitTestCase;
  use Drupal\user\PrivateTempStore;
  use Drupal\user\PrivateTempStoreFactory;

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
     * The language-locale mapper.
     *
     * @var \Drupal\lingotek\LanguageLocaleMapperInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $languageLocaleMapper;

    /**
     * The entity manager.
     *
     * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityManager;

    /**
     * The language manager.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $languageManager;

    /**
     * The entity query factory service.
     *
     * @var \Drupal\Core\Entity\Query\QueryFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityQuery;

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
     * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $contentTranslationService;

    /**
     * The tempstore factory.
     *
     * @var \Drupal\user\PrivateTempStoreFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $tempStoreFactory;

    /**
     * The state key value store.
     *
     * @var \Drupal\Core\State\StateInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $state;

    protected function setUp() {
      parent::setUp();

      $this->entityManager = $this->getMock(EntityManagerInterface::class);
      $this->languageManager = $this->getMock(LanguageManagerInterface::class);
      $this->entityQuery = $this->getMockBuilder(QueryFactory::class)->disableOriginalConstructor()->getMock();
      $this->lingotek = $this->getMock(LingotekInterface::class);
      $this->lingotekConfiguration = $this->getMock(LingotekConfigurationServiceInterface::class);
      $this->languageLocaleMapper = $this->getMock(LanguageLocaleMapperInterface::class);
      $this->contentTranslationManager = $this->getMock(ContentTranslationManagerInterface::class);
      $this->contentTranslationService = $this->getMock(LingotekContentTranslationServiceInterface::class);
      $this->tempStoreFactory = $this->getMockBuilder(PrivateTempStoreFactory::class)->disableOriginalConstructor()->getMock();
      $this->state = $this->getMock(StateInterface::class);

      $this->form = new LingotekManagementForm(
        $this->entityManager,
        $this->languageManager,
        $this->entityQuery,
        $this->lingotek,
        $this->lingotekConfiguration,
        $this->languageLocaleMapper,
        $this->contentTranslationManager,
        $this->contentTranslationService,
        $this->tempStoreFactory,
        $this->state,
        'node'
      );
      $this->form->setConfigFactory($this->getConfigFactoryStub(
        ['lingotek.settings' => ['account' => ['access_token' => 'token', 'login_id' => 'test@example.com']]]
      ));
      $this->form->setStringTranslation($this->getStringTranslationStub());
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
      $query = $this->getMock(QueryInterface::class);
      $this->entityQuery->expects($this->once())
        ->method('get')
        ->with('node')
        ->willReturn($query);
      $query->expects($this->once())
        ->method('pager')
        ->with(10, NULL)
        ->willReturn($query);

      // Assert that condition is called filtering by the undefined language.
      $query->expects($this->once())
        ->method('condition')
        ->with('langcode', 'und', '!=', NULL)
        ->willReturn($query);

      $tempStore = $this->getMockBuilder(PrivateTempStore::class)->disableOriginalConstructor()->getMock();
      $this->tempStoreFactory->expects($this->at(0))
        ->method('get')
        ->with('lingotek.management.items_per_page')
        ->willReturn($tempStore);
      $this->tempStoreFactory->expects($this->at(1))
        ->method('get')
        ->with('lingotek.management.filter.node')
        ->willReturn($tempStore);

      $entityType = $this->getMock(EntityTypeInterface::class);
      $this->entityManager->expects($this->once())
        ->method('getDefinition')
        ->with('node')
        ->willReturn($entityType);
      $entityType->expects($this->once())
        ->method('getKey')
        ->with('langcode')
        ->willReturn('langcode');

      $storage = $this->getMock(EntityStorageInterface::class);
      $this->entityManager->expects($this->once())
        ->method('getStorage')
        ->with('node')
        ->willReturn($storage);
      $storage->expects($this->once())
        ->method('loadMultiple')
        ->willReturn([]);

      $this->entityManager->expects($this->once())
        ->method('getBundleInfo')
        ->willReturn([]);

      $language = $this->getMock(ConfigurableLanguageInterface::class);
      $this->languageManager->expects($this->any())
        ->method('getLanguages')
        ->willReturn(['en' => $language]);

      $this->lingotekConfiguration->expects($this->once())
        ->method('getProfileOptions')
        ->willReturn(['manual']);

      $this->state->expects($this->once())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->willReturn(FALSE);

      $this->form->buildForm([], new FormState());
    }

  }
}

namespace {
  // @todo Delete after https://drupal.org/node/1858196 is in.
  if (!function_exists('drupal_set_message')) {
    function drupal_set_message() {
    }
  }
}
