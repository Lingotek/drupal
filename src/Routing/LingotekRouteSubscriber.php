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
      if ($route->getDefault('_controller') == '\Drupal\config_translation\Controller\ConfigTranslationController::itemPage') {
        $route->setDefault('_controller', '\Drupal\lingotek\Controller\LingotekConfigTranslationController::itemPage');
      }
    }

    $debug_enabled = \Drupal::state()->get('lingotek.enable_debug_utilities', FALSE);
    foreach ($this->entityManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->isTranslatable()
          && \Drupal::config('lingotek.settings')->get('translate.entity.' . $entity_type_id)) {

        // Add a route for bulk translation management.
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
          array('_permission' => 'manage lingotek translations'),
          $options
        );
        $collection->add("lingotek.manage.{$entity_type_id}", $route);

        // Add a route for view metadata.
        if ($debug_enabled) {
          $path = ($entity_type->hasLinkTemplate('canonical') ?
            $entity_type->getLinkTemplate('canonical') : $entity_type->getLinkTemplate('edit_form'))
            . '/metadata';
          $defaults = ['entity_type_id' => $entity_type_id];
          $options = [
            'parameters' => [
              $entity_type_id => [
                'type' => 'entity:' . $entity_type_id,
              ],
            ],
          ];
          $route = new Route(
            $path,
            [
              '_form' => 'Drupal\lingotek\Form\LingotekMetadataEditForm',
              '_title' => 'Edit translation metadata',
            ] + $defaults,
            ['_permission' => 'manage lingotek translations'],
            $options
          );
          $collection->add("lingotek.metadata.{$entity_type_id}", $route);
        }
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
