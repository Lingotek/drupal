<?php

namespace Drupal\lingotek\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->isTranslatable()
          && \Drupal::config('lingotek.settings')->get('translate.entity.' . $entity_type_id)) {

        if ($entity_type_id === 'paragraph') {
          $config = \Drupal::config('lingotek.settings');
          $enable_bulk_management = $config->get('preference.contrib.paragraphs.enable_bulk_management', FALSE);
          if (!$enable_bulk_management) {
            continue;
          }
        }
        if ($entity_type_id === 'cohesion_layout') {
          continue;
        }

        // Add a route for bulk translation management.
        $path = "admin/lingotek/manage/{$entity_type_id}";
        $options = ['_admin_route' => TRUE];
        $defaults = [
          'entity_type_id' => $entity_type_id,
        ];
        $route = new Route(
          $path,
          [
            '_form' => 'Drupal\lingotek\Form\LingotekManagementForm',
            '_title' => 'Manage Translations',
          ] + $defaults,
          ['_permission' => 'manage lingotek translations'],
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
          if ($entity_type_id === 'node') {
            // Using _node_operation_route would be more elegant, but this
            // subscriber runs after NodeAdminRouteSubscriber already run.
            $options['_admin_route'] = \Drupal::config('node.settings')->get('use_admin_theme');
          }

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
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -211];
    return $events;
  }

}
