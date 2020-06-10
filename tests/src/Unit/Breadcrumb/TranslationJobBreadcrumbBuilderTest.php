<?php

namespace Drupal\Tests\lingotek\Unit\Breadcrumb;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lingotek\Breadcrumb\TranslationJobBreadcrumbBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\lingotek\Breadcrumb\TranslationJobBreadcrumbBuilder
 * @group lingotek
 */
class TranslationJobBreadcrumbBuilderTest extends UnitTestCase {

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $request;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $requestStack;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $accessManager;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $titleResolver;

  /**
   * The user currently logged in.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The breadcrumb builder under test.
   *
   * @var \Drupal\lingotek\Breadcrumb\TranslationJobBreadcrumbBuilder
   */
  protected $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $cache_contexts_manager = $this->getMockBuilder(CacheContextsManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new Container();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);

    $this->request = $this->createMock(Request::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($this->request);
    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->accessManager = $this->createMock(AccessManagerInterface::class);
    $this->titleResolver = $this->createMock(TitleResolverInterface::class);

    $this->builder = new TranslationJobBreadcrumbBuilder($this->requestStack, $this->currentUser, $this->titleResolver, $this->accessManager);
    $this->builder->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests TranslationJobBreadcrumbBuilder::applies().
   *
   * @param bool $expected
   *   TranslationJobBreadcrumbBuilder::applies() expected result.
   * @param string|null $route_name
   *   (optional) A route name.
   * @param array $parameter_map
   *   (optional) An array of parameter names and values.
   *
   * @dataProvider providerTestApplies
   * @covers ::applies
   */
  public function testApplies($expected, $route_name = NULL, $parameter_map = []) {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->expects($this->once())
      ->method('getRouteName')
      ->will($this->returnValue($route_name));
    $route_match->expects($this->any())
      ->method('getParameter')
      ->will($this->returnValueMap($parameter_map));

    $this->assertEquals($expected, $this->builder->applies($route_match));
  }

  /**
   * Provides test data for testApplies().
   *
   * @return array
   *   Array of datasets for testApplies(). Structured as such:
   *   - TranslationJobBreadcrumbBuilder::applies() expected result.
   *   - TranslationJobBreadcrumbBuilder::applies() $route_name input array.
   *   - TranslationJobBreadcrumbBuilder::applies() $parameter_map input array.
   */
  public function providerTestApplies() {
    return [
      [
        FALSE,
      ],
      [
        FALSE,
        'entity.node.canonical',
      ],
      [
        TRUE,
        'lingotek.translation_jobs',
      ],
      [
        TRUE,
        'lingotek.translation_job_info',
        [['job_id', 'my_job_id']],
      ],
      [
        TRUE,
        'lingotek.translation_job_info.content',
        [['job_id', 'my_job_id']],
      ],
      [
        TRUE,
        'lingotek.translation_job_info.config',
        [['job_id', 'my_job_id']],
      ],
    ];
  }

  /**
   * Tests TranslationJobBreadcrumbBuilder::build().
   *
   * @see \Drupal\lingotek\Breadcrumb\TranslationJobBreadcrumbBuilder::build()
   * @dataProvider providerTestBuild
   * @covers ::build
   */
  public function testBuild($route_name, $expected, $title, $job_id) {
    $route_match = $this->createMock(ResettableStackedRouteMatchInterface::class);
    $route_match->expects($this->any())
      ->method('getRouteName')
      ->willReturn($route_name);

    $access = new AccessResultAllowed();
    $this->accessManager->expects($this->once())
      ->method('check')
      ->with($route_match, $this->currentUser, NULL, TRUE)
      ->willReturn($access);

    if ($route_name === 'lingotek.translation_jobs') {
      $route_match->expects($this->never())
        ->method('getRouteMatchFromRequest');
      $route_match->expects($this->never())
        ->method('getRouteObject');
      $this->titleResolver->expects($this->never())
        ->method('getTitle');
    }
    else {
      $route_match->expects($this->once())
        ->method('getRouteMatchFromRequest')
        ->with($this->request)
        ->willReturn($route_match);
      $route = $this->createMock(Route::class);
      $route_match->expects($this->once())
        ->method('getParameter')
        ->with('job_id')
        ->willReturn($job_id);
      $route_match->expects($this->once())
        ->method('getRouteObject')
        ->willReturn($route);

      $this->titleResolver->expects($this->once())
        ->method('getTitle')
        ->with($this->request, $route)
        ->willReturn($title);
    }

    $expectedLinks = array_map(function ($expected) {
      $args = isset($expected[2]) ? $expected[2] : [];
      return Link::createFromRoute($expected[0], $expected[1], $args);
    }, $expected);
    $breadcrumb = $this->builder->build($route_match);
    $this->assertEquals($expectedLinks, $breadcrumb->getLinks());
  }

  /**
   * Provides test data for testBuild().
   *
   * @return array
   *   Array of datasets for testApplies(). Structured as such:
   *   - TranslationJobBreadcrumbBuilder::applies() $route_name.
   *   - TranslationJobBreadcrumbBuilder::applies() $expected Expected links.
   *   - TranslationJobBreadcrumbBuilder::applies() $parameter_map input array.
   */
  public function providerTestBuild() {
    return [
      [
        'lingotek.translation_jobs',
        [
          ['Home', '<front>'],
          ['Administration', 'system.admin'],
          ['Lingotek Translation Dashboard', 'lingotek.dashboard'],
          ['Translation Jobs', 'lingotek.translation_jobs'],
        ],
        NULL,
        NULL,
      ],
      [
        'lingotek.translation_job_info',
        [
          ['Home', '<front>'],
          ['Administration', 'system.admin'],
          ['Lingotek Translation Dashboard', 'lingotek.dashboard'],
          ['Translation Jobs', 'lingotek.translation_jobs'],
          ['Job @job', 'lingotek.translation_job_info', ['job_id' => 'my-job-id']],
        ],
        'Job @job',
        'my-job-id',
      ],
      [
        'lingotek.translation_job_info.content',
        [
          ['Home', '<front>'],
          ['Administration', 'system.admin'],
          ['Lingotek Translation Dashboard', 'lingotek.dashboard'],
          ['Translation Jobs', 'lingotek.translation_jobs'],
          ['Job @job Content', 'lingotek.translation_job_info.content', ['job_id' => 'my-job-id']],
        ],
        'Job @job Content',
        'my-job-id',
      ],
      [
        'lingotek.translation_job_info.config',
        [
          ['Home', '<front>'],
          ['Administration', 'system.admin'],
          ['Lingotek Translation Dashboard', 'lingotek.dashboard'],
          ['Translation Jobs', 'lingotek.translation_jobs'],
          ['Job @job Configuration', 'lingotek.translation_job_info.config', ['job_id' => 'my-job-id']],
        ],
        'Job @job Configuration',
        'my-job-id',
      ],
    ];
  }

}
