<?php

namespace Drupal\lingotek\Breadcrumb;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

class TranslationJobBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * The user currently logged in.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack, AccountInterface $current_user, TitleResolverInterface $title_resolver, AccessManagerInterface $access_manager) {
    $this->request = $request_stack->getCurrentRequest();
    $this->accessManager = $access_manager;
    $this->titleResolver = $title_resolver;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $routeName = $route_match->getRouteName();
    if (strpos($routeName, 'lingotek.translation_job') === 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $access = $this->accessManager->check($route_match, $this->currentUser, NULL, TRUE);
    $breadcrumb->addCacheableDependency($access);
    $breadcrumb->addCacheContexts(['url.path']);
    $links = [];
    if ($access->isAllowed()) {
      if ($route_match->getRouteName() !== 'lingotek.translation_jobs') {
        $title = $this->titleResolver->getTitle($this->request, $route_match->getRouteObject());
        $route = $route_match->getRouteMatchFromRequest($this->request);
        $links[] = Link::createFromRoute($title, $route_match->getRouteName(), ['job_id' => $route->getParameter('job_id')]);
      }
      $links[] = Link::createFromRoute($this->t('Translation Jobs'), 'lingotek.translation_jobs');
      $links[] = Link::createFromRoute($this->t('Lingotek Translation Dashboard'), 'lingotek.dashboard');
      $links[] = Link::createFromRoute($this->t('Administration'), 'system.admin');
    }
    $links[] = Link::createFromRoute($this->t('Home'), '<front>');
    return $breadcrumb->setLinks(array_reverse($links));
  }

}
