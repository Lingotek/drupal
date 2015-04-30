<?php

/**
 * @file
 * Contains \Drupal\lingotek\Routing\LingotekRouteSubscriber.
 */

namespace Drupal\lingotek\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber to alter entity translation routes.
 */
class LingotekRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Use instead of ContentTranslationController.
    foreach ($collection as $route) {
      if ($route->getDefault('_controller') == '\Drupal\content_translation\Controller\ContentTranslationController::overview') {
        dpm($route->getDefault('_controller'), 'old');
        $route->setDefault('_controller', '\Drupal\lingotek\Controller\LingotekContentTranslationController::overview');
        dpm($route->getDefault('_controller'), 'new');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    //  ContentTranslationRouteSubscriber is -100.
    $events[RoutingEvents::ALTER] = array('onAlterRoutes', -211);
    return $events;
  }

}
