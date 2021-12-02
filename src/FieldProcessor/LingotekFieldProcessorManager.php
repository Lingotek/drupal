<?php

namespace Drupal\lingotek\FieldProcessor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a LingotekFieldProcessor plugin manager.
 *
 * @see \Drupal\lingotek\RelatedEntitiesDetector
 * @see plugin_api
 */
class LingotekFieldProcessorManager extends DefaultPluginManager {

  /**
   * Constructs a new LingotekFieldProcessorManager object.
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
      'Plugin/LingotekFieldProcessor',
      $namespaces,
      $module_handler,
      'Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface',
      'Drupal\lingotek\Annotation\LingotekFieldProcessor'
    );
    $this->alterInfo('lingotek_field_processor_info');
    $this->setCacheBackend($cache_backend, 'lingotek_field_processor_info_plugins');
  }

  public function getProcessorsForField($field_definition, $entity) {
    $field_processor_definitions = $this->getDefinitions();
    $valid_processors = [];
    uasort($field_processor_definitions, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    foreach ($field_processor_definitions as $field_processor_definition_id => $field_processor_definition) {
      /** @var \Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface $processor */
      $processor = $this->createInstance($field_processor_definition_id, []);
      if ($processor->appliesToField($field_definition, $entity)) {
        $valid_processors[] = $processor;
      }
    }
    if (empty($valid_processors)) {
      $valid_processors[] = $this->getDefaultProcessor();
    }
    // TODO: Decide if the first applying processor should exclude others, or if we should allow to refine extractions.
    return $valid_processors;
  }

  public function getDefaultProcessor() {
    return $this->createInstance('default');
  }

}
