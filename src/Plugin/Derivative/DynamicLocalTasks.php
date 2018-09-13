<?php

namespace Drupal\lingotek\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines dynamic local tasks.
 */
class DynamicLocalTasks extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $config = \Drupal::configFactory()->get('lingotek.settings');
    $show_import_tab = $config->get('preference.enable_content_cloud');

    if ($show_import_tab) {
      $this->derivatives['lingotek.import'] = $base_plugin_definition;
      $this->derivatives['lingotek.import']['title'] = 'Import (Beta)';
      $this->derivatives['lingotek.import']['route_name'] = 'lingotek.import';
      $this->derivatives['lingotek.import']['base_route'] = 'lingotek.dashboard';

    }

    return $this->derivatives;
  }

}
