<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Manages Lingotek form-filter plugins.
 *
 * @see \Drupal\lingotek\FormComponent\FormComponentBulkActionInterface
 * @see \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionBase
 * @see \Drupal\lingotek\Annotation\LingotekFormComponentBulkAction
 * @see \hook_lingotek_form_bulk_action_alter()
 */
class LingotekFormComponentBulkActionManager extends LingotekFormComponentManagerBase {

  /**
   * LingotekFormComponentBulkActionManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache.discovery service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module_handler service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/LingotekFormComponent/BulkAction', $namespaces, $module_handler, 'Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionInterface', 'Drupal\lingotek\Annotation\LingotekFormComponentBulkAction');

    $this->alterInfo('lingotek_form_bulk_action');
    $this->setCacheBackend($cache_backend, 'lingotek_form_bulk_action');
  }

}
