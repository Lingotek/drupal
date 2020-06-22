<?php

namespace Drupal\Tests\lingotek\Unit\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\lingotek\Controller\LingotekSupportedLocalesController;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\lingotek\Controller\LingotekSupportedLocalesController
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekSupportedLocalesControllerTest extends UnitTestCase {

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
   * @var \Drupal\lingotek\Controller\LingotekSupportedLocalesController
   */
  protected $controller;

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

    $this->controller = new LingotekSupportedLocalesController(
      $this->request,
      $this->configFactory,
      $this->lingotek,
      $this->languageLocaleMapper,
      $this->formBuilder,
      $this->logger
    );
    $this->controller->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests autocomplete.
   *
   * @covers ::autocomplete
   */
  public function testAutocomplete() {
    $this->lingotek->expects($this->any())
      ->method('getLocalesInfo')
      ->willReturn([
        'es_ES' => [
          'code' => 'es_ES',
          'language_code' => 'es',
          'title' => 'Spanish (Spain)',
          'language' => 'Spanish',
          'country_code' => 'es',
          'country' => 'Spain',
        ],
        'es_MX' => [
          'code' => 'es_MX',
          'language_code' => 'es',
          'title' => 'Spanish (Mexico)',
          'language' => 'Spanish',
          'country_code' => 'MX',
          'country' => 'Mexico',
        ],
        'de_DE' => [
          'code' => 'de_DE',
          'language_code' => 'de',
          'title' => 'German (Germany)',
          'language' => 'German',
          'country_code' => 'de',
          'country' => 'Germany',
        ],
        'de_ES' => [
          'code' => 'de_ES',
          'language_code' => 'de',
          'title' => 'German (Spain)',
          'language' => 'German',
          'country_code' => 'es',
          'country' => 'Spain',
        ],
      ]);

    $query = $this->createMock(ParameterBag::class);
    $query->expects($this->once())
      ->method('get')
      ->with('q')
      ->willReturn('es');
    $this->request->query = $query;
    /** @var \Symfony\Component\HttpFoundation\JsonResponse $response */
    $response = $this->controller->autocomplete($this->request);
    $matches = json_decode($response->getContent(), TRUE);
    $this->assertCount(3, $matches);
    $expected = [
      [
        'value' => 'de_ES',
        'label' => 'German (Spain) (de_ES) [matched: Code: <em class="placeholder">de_ES</em>]',
      ],
      [
        'value' => 'es_ES',
        'label' => 'Spanish (Spain) (es_ES) [matched: Code: <em class="placeholder">es_ES</em>]',
      ],
      [
        'value' => 'es_MX',
        'label' => 'Spanish (Mexico) (es_MX) [matched: Code: <em class="placeholder">es_MX</em>]',
      ],
    ];
    $this->assertEquals($expected, $matches);

    $query = $this->createMock(ParameterBag::class);
    $query->expects($this->once())
      ->method('get')
      ->with('q')
      ->willReturn('German');
    $this->request->query = $query;
    /** @var \Symfony\Component\HttpFoundation\JsonResponse $response */
    $response = $this->controller->autocomplete($this->request);
    $matches = json_decode($response->getContent(), TRUE);
    $this->assertCount(0, $matches);

    $query = $this->createMock(ParameterBag::class);
    $query->expects($this->once())
      ->method('get')
      ->with('q')
      ->willReturn('de');
    $this->request->query = $query;
    /** @var \Symfony\Component\HttpFoundation\JsonResponse $response */
    $response = $this->controller->autocomplete($this->request);
    $matches = json_decode($response->getContent(), TRUE);
    $this->assertCount(2, $matches);
    $expected = [
      [
        'value' => 'de_DE',
        'label' => 'German (Germany) (de_DE) [matched: Code: <em class="placeholder">de_DE</em>]',
      ],
      [
        'value' => 'de_ES',
        'label' => 'German (Spain) (de_ES) [matched: Code: <em class="placeholder">de_ES</em>]',
      ],
    ];
    $this->assertEquals($expected, $matches);
  }

}
