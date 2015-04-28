<?php

/**
 * @file
 * Contains \Drupal\lingotek\Routing\LingotekContentRouteSubscriber.
 */

namespace Drupal\lingotek\Routing;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber to alter entity translation routes.
 */
class LingotekContentRouteSubscriber extends RouteSubscriberBase {

  public function __construct(ContentTranslationManagerInterface $content_translation_manager) {
    $this->contentTranslationManager = $content_translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      // Try to get the route from the current collection.
      $link_template = $entity_type->getLinkTemplate('canonical');
      if (strpos($link_template, '/') !== FALSE) {
        $base_path = '/' . $link_template;
      }
      else {
        if (!$entity_route = $collection->get("entity.$entity_type_id.canonical")) {
          continue;
        }
        $base_path = $entity_route->getPath();
      }

      // Inherit admin route status from edit route, if exists.
      $is_admin = FALSE;
      $route_name = "entity.$entity_type_id.edit_form";
      if ($edit_route = $collection->get($route_name)) {
        $is_admin = (bool) $edit_route->getOption('_admin_route');
      }

      $path = $base_path . '/Lingotek/translations';

      $route = new Route(
          $path, array(
        '_controller' => '\Drupal\lingotek\Controller\LingotekContentTranslationController::overview',
        'entity_type_id' => $entity_type_id,
          ), array(
        '_access_content_translation_overview' => $entity_type_id,
          ), array(
        'parameters' => array(
          $entity_type_id => array(
            'type' => 'entity:' . $entity_type_id,
          ),
        ),
        '_admin_route' => $is_admin,
          )
      );
      $route_name = "entity.$entity_type_id.lingotek_content_translation_overview";
      $collection->add($route_name, $route);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    //  ContentTranslationRouteSubscriber is -210.
    $events[RoutingEvents::ALTER] = array('onAlterRoutes', -220);
    return $events;
  }

}
