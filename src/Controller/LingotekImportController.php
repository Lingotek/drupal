<?php

namespace Drupal\lingotek\Controller;

class LingotekImportController extends LingotekControllerBase {

  /**
   * Generates the import content. It has two tabs, the import form and the settings
   *form.
   *@author Unknown
   */
  public function content() {
    $import_tab = [
    $this->getLingotekForm('LingotekImportSettingsForm'),
    $this->getLingotekForm('LingotekImportForm'),

    ];

    return $import_tab;

  }

}
