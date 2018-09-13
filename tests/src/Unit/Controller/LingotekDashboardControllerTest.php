<?php

namespace Drupal\Tests\lingotek\Unit\Controller;

use Drupal\Core\Language\Language;
use Drupal\lingotek\Controller\LingotekDashboardController;
use Drupal\Tests\UnitTestCase;

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
   * The mock entity query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityQueryFactory;

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
   * The controller under test.
   *
   * @var \Drupal\Lingotek\Controller\LingotekDashboardController
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->request = $this->getMock('Symfony\Component\HttpFoundation\Request');
    $this->configFactory = $this->getConfigFactoryStub(['lingotek.settings' => ['account' => ['access_token' => 'at', 'login_id' => 'login']]]);
    $this->entityTypeManager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManager')
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityQueryFactory = $this->getMockBuilder('Drupal\Core\Entity\Query\QueryFactory')
      ->disableOriginalConstructor()
      ->getMock();
    $this->languageManager = $this->getMock('Drupal\Core\Language\LanguageManagerInterface');
    $this->lingotek = $this->getMock('Drupal\lingotek\LingotekInterface');
    $this->languageLocaleMapper = $this->getMock('Drupal\lingotek\LanguageLocaleMapperInterface');
    $this->lingotekConfiguration = $this->getMock('Drupal\lingotek\LingotekConfigurationServiceInterface');
    $this->formBuilder = $this->getMock('Drupal\Core\Form\FormBuilderInterface');
    $this->logger = $this->getMock('Psr\Log\LoggerInterface');

    $this->controller = new LingotekDashboardController(
      $this->request,
      $this->configFactory,
      $this->entityTypeManager,
      $this->entityQueryFactory,
      $this->languageManager,
      $this->lingotek,
      $this->languageLocaleMapper,
      $this->lingotekConfiguration,
      $this->formBuilder,
      $this->logger
    );
    $this->controller->setStringTranslation($this->getStringTranslationStub());
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

    $query = $this->getMock('Drupal\Core\Entity\Query\QueryInterface');
    $query->method('condition')
      ->willReturnSelf();
    $query->method('count')
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('execute')
      ->willReturn(3);

    $this->entityQueryFactory->expects($this->any())
      ->method('get')
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
    $language = $this->getMock('Drupal\language\ConfigurableLanguageInterface');
    $storage = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');
    $storage->expects($this->any())
      ->method('load')
      ->willReturn($language);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($storage);
  }

}
