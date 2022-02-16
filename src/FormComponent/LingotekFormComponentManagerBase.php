<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Plugin\DefaultPluginManager;
use InvalidArgumentException;

/**
 * Abstract class for all form-component-plugin managers.
 *
 * @package Drupal\lingotek\FormComponent
 */
abstract class LingotekFormComponentManagerBase extends DefaultPluginManager implements LingotekFormComponentManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    // Fetch and sort definitions by weight.
    if (!($definitions = $this->getCachedDefinitions())) {
      $definitions = $this->findDefinitions();
      uasort($definitions, '\Drupal\Component\Utility\SortArray::sortByWeightElement');
      $this->setCachedDefinitions($definitions);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getApplicable(array $arguments = []) {
    if (!isset($arguments['form_id'])) {
      throw new InvalidArgumentException("The form_id argument is not specified.");
    }
    // Make sure this is set even if empty.
    $arguments['entity_type_id'] = $arguments['entity_type_id'] ?? NULL;

    $form_id = $arguments['form_id'];
    $entity_type_id = $arguments['entity_type_id'];

    /** @var \Drupal\lingotek\FormComponent\LingotekFormComponentInterface[] $plugins */
    $plugins = [];

    $definitions = array_filter($this->getDefinitions(), function ($definition) use ($form_id, $entity_type_id) {
      if (isset($definition['form_ids']) && in_array($form_id, $definition['form_ids'])) {
        if ($entity_type_id) {
          if (!empty($definition['entity_types'])) {
            $isApplicable = in_array($entity_type_id, $definition['entity_types']);
          }
          else {
            // No entity types equals all entity types.
            $isApplicable = TRUE;
          }
        }
        else {
          // No entity type filtered equals all entity types.
          $isApplicable = TRUE;
        }
      }
      else {
        // Not the filtered form_id.
        $isApplicable = FALSE;
      }
      return $isApplicable;
    });

    foreach (array_keys($definitions) as $plugin_id) {
      try {
        /** @var \Drupal\lingotek\FormComponent\LingotekFormComponentInterface $plugin */
        $plugin = $this->createInstance($plugin_id);

        if ($plugin->isApplicable($arguments)) {
          $plugin->setEntityTypeId($entity_type_id);
          $plugins[$plugin_id] = $plugin;
        }
      }
      catch (PluginException $e) {
        // Log a warning, ideally this would only happen on development and
        // never on prod.
        \Drupal::logger('lingotek')->warning((string) new FormattableMarkup('No plugin found for %plugin_id. Error: %message', ['%plugin_id' => $plugin_id, '%message' => $e->getMessage()]));
      }
    }

    return $plugins;
  }

}
