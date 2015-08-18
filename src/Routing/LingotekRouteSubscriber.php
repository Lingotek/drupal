<?php

/**
 * @file
 * Contains \Drupal\lingotek\Routing\LingotekRouteSubscriber.
 */

namespace Drupal\lingotek\Routing;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber to add lingotek controller and generate bulk administration
 * routes for content entity translation.
 */
class LingotekRouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Use instead of ContentTranslationController.
    foreach ($collection as $route) {
      if ($route->getDefault('_controller') == '\Drupal\content_translation\Controller\ContentTranslationController::overview') {
        $route->setDefault('_controller', '\Drupal\lingotek\Controller\LingotekContentTranslationController::overview');
      }
    }

    foreach ($this->entityManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->isTranslatable()
          && \Drupal::config('lingotek.settings')->get('translate.entity.' . $entity_type_id)) {
        $path = "admin/lingotek/manage/{$entity_type_id}";
        $options = array('_admin_route' => TRUE);
        $defaults = array(
          'entity_type_id' => $entity_type_id,
        );
        $route = new Route(
          $path,
          array(
            '_form' => 'Drupal\lingotek\Form\LingotekManagementForm',
            '_title' => 'Manage Translations',
          ) + $defaults,
          array('_permission' => 'administer lingotek'),
          $options
        );
        $collection->add("lingotek.manage.{$entity_type_id}", $route);

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
