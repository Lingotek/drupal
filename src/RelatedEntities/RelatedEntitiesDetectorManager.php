<?php

namespace Drupal\lingotek\RelatedEntities;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a RelatedEntitiesDetector plugin manager.
 *
 * @see \Drupal\lingotek\RelatedEntitiesDetector
 * @see plugin_api
 */
class RelatedEntitiesDetectorManager extends DefaultPluginManager {

  /**
   * Constructs a new LingotekEntityManagmentManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/RelatedEntitiesDetector',
      $namespaces,
      $module_handler,
      'Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface',
      'Drupal\lingotek\Annotation\RelatedEntitiesDetector'
    );
    $this->alterInfo('related_entities_detector_info');
    $this->setCacheBackend($cache_backend, 'related_entities_detector_info_plugins');
  }

}
