<?php

namespace Drupal\Tests\lingotek\Unit\Controller;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\lingotek\Controller\LingotekDashboardController;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\lingotek\Controller\LingotekDashboardController
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekDashboardControllerTest extends UnitTestCase {

  /**
   * The mocked request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $request;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $languageManager;

  /**
   * The Lingotek service
   *
   * @var \Drupal\lingotek\LingotekInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lingotek;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $languageLocaleMapper;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lingotekConfiguration;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formBuilder;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The mocked entity storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityStorage;

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * The controller under test.
   *
   * @var \Drupal\lingotek\Controller\LingotekDashboardController
   */
  protected $controller;

  /**
   * The user with permissions we're testing
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->request = $this->createMock(Request::class);
    $this->configFactory = $this->getConfigFactoryStub(['lingotek.settings' => ['account' => ['access_token' => 'at', 'login_id' => 'login']]]);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->lingotek = $this->createMock(LingotekInterface::class);
    $this->languageLocaleMapper = $this->createMock(LanguageLocaleMapperInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->formBuilder = $this->createMock(FormBuilderInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $this->user = $this->createMock(AccountInterface::class);

    $this->controller = new LingotekDashboardController(
      $this->request,
      $this->configFactory,
      $this->entityTypeManager,
      $this->languageManager,
      $this->lingotek,
      $this->languageLocaleMapper,
      $this->lingotekConfiguration,
      $this->formBuilder,
      $this->logger,
      $this->urlGenerator,
      $this->user
    );
    $this->controller->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests get dashboard info which includes data required by gmc library.
   *
   * @covers ::getDashboardInfo
   */
  public function testGetDashboardInfo() {
    // This is a hack for testing protected methods.
    $class = new ReflectionClass('\Drupal\lingotek\Controller\LingotekDashboardController');
    $method = $class->getMethod('getDashboardInfo');
    $method->setAccessible(TRUE);

    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with('lingotek.dashboard_endpoint')
      ->willReturn('/dashboard');

    $result = $method->invokeArgs($this->controller, []);
    $this->assertEquals('/dashboard', $result['endpoint_url']);
  }

  /**
   * Tests that when no type is enabled, no types are included in the stats.
   *
   * @covers ::endpoint
   */
  public function testNoTypesEnabledForLingotekTranslation() {
    $this->setUpConfigurableLanguageMock();

    $this->request->expects($this->any())
      ->method('getMethod')
      ->willReturn('GET');

    $languages = [];
    foreach (['en', 'fr'] as $langcode) {
      $language = new Language(['id' => $langcode]);
      $languages[$langcode] = $language;
    }

    $this->languageManager->expects($this->any())
      ->method('getLanguages')
      ->will($this->returnValue($languages));
    $this->lingotekConfiguration->expects($this->any())
      ->method('getEnabledEntityTypes')
      ->will($this->returnValue([]));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->will($this->returnValueMap([['en', 'en_US'], ['fr', 'fr_CA']]));

    /** @var \Symfony\Component\HttpFoundation\JsonResponse $value */
    $response = $this->controller->endpoint($this->request);
    $content = json_decode($response->getContent(), TRUE);

    $this->assertEquals('GET', $content['method']);
    $this->assertEquals(2, count($content['languages']));
    $this->assertEquals(0, count($content['languages']['en_US']['source']['types']));
    $this->assertEquals(0, count($content['languages']['fr_CA']['source']['types']));
  }

  /**
   * Tests that when the node entity type is enabled, the response contains the
   * stats of nodes.
   *
   * @covers ::endpoint
   */
  public function testNodeTypesEnabledForLingotekTranslation() {
    $this->setUpConfigurableLanguageMock();

    $this->request->expects($this->any())
      ->method('getMethod')
      ->willReturn('GET');

    $languages = [];
    foreach (['en', 'fr'] as $langcode) {
      $language = new Language(['id' => $langcode]);
      $languages[$langcode] = $language;
    }

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')
      ->willReturnSelf();
    $query->method('count')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('execute')
      ->willReturn(3);

    $this->entityStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);

    $this->languageManager->expects($this->any())
      ->method('getLanguages')
      ->will($this->returnValue($languages));
    $this->lingotekConfiguration->expects($this->any())
      ->method('getEnabledEntityTypes')
      ->will($this->returnValue(['node' => 'node']));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->will($this->returnValueMap([['en', 'en_US'], ['fr', 'fr_CA']]));

    /** @var \Symfony\Component\HttpFoundation\JsonResponse $value */
    $response = $this->controller->endpoint($this->request);
    $content = json_decode($response->getContent(), TRUE);

    $this->assertEquals('GET', $content['method']);

    $this->assertEquals(2, count($content['languages']));

    $this->assertEquals(3, $content['languages']['en_US']['source']['types']['node']);
    $this->assertEquals(1, count($content['languages']['en_US']['source']['types']));
    $this->assertEquals(3, $content['languages']['en_US']['target']['types']['node']);
    $this->assertEquals(1, count($content['languages']['en_US']['target']['types']));

    $this->assertEquals(3, $content['languages']['fr_CA']['source']['types']['node']);
    $this->assertEquals(1, count($content['languages']['fr_CA']['source']['types']));
    $this->assertEquals(3, $content['languages']['fr_CA']['target']['types']['node']);
    $this->assertEquals(1, count($content['languages']['fr_CA']['target']['types']));

    $this->assertEquals(6, $content['source']['types']['node']);
    $this->assertEquals(6, $content['target']['types']['node']);
    $this->assertEquals(6, $content['source']['total']);
    $this->assertEquals(6, $content['target']['total']);
  }

  /**
   * Setup the entity type manager for returning configurable language storage
   * and its mocks.
   */
  protected function setUpConfigurableLanguageMock() {
    $language = $this->createMock(ConfigurableLanguageInterface::class);
    $this->entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityStorage->expects($this->any())
      ->method('load')
      ->willReturn($language);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($this->entityStorage);
  }

}
