<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Manages Lingotek form-filter plugins.
 *
 * @see \Drupal\lingotek\FormComponent\FormComponentFilterInterface
 * @see \Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase
 * @see \Drupal\lingotek\Annotation\LingotekFormComponentFilter
 * @see \hook_lingotek_form_filter_alter()
 */
class LingotekFormComponentFilterManager extends LingotekFormComponentManagerBase {

  /**
   * FormComponentFilterManager constructor.
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
    parent::__construct('Plugin/LingotekFormComponent/Filter', $namespaces, $module_handler, 'Drupal\lingotek\FormComponent\LingotekFormComponentFilterInterface', 'Drupal\lingotek\Annotation\LingotekFormComponentFilter');

    $this->alterInfo('lingotek_form_filter');
    $this->setCacheBackend($cache_backend, 'lingotek_form_filter');
  }

}
