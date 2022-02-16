<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Manages Lingotek form-field plugins.
 *
 * @package Drupal\lingotek\FormComponent
 *
 * @see \Drupal\lingotek\FormComponent\LingotekFormComponentFieldInterface
 * @see \Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase
 * @see \Drupal\lingotek\Annotation\LingotekFormComponentField
 * @see \hook_lingotek_form_field_alter()
 */
class LingotekFormComponentFieldManager extends LingotekFormComponentManagerBase {

  /**
   * FormComponentFieldManager constructor.
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
    parent::__construct('Plugin/LingotekFormComponent/Field', $namespaces, $module_handler, 'Drupal\lingotek\FormComponent\LingotekFormComponentFieldInterface', 'Drupal\lingotek\Annotation\LingotekFormComponentField');

    $this->alterInfo('lingotek_form_field');
    $this->setCacheBackend($cache_backend, 'lingotek_form_field');
  }

}
