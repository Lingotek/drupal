<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Form\LingotekImportForm;
use Drupal\lingotek\Form\LingotekImportSettingsForm;

class LingotekImportController extends LingotekControllerBase {

  /**
   * Generates the import content. It has two tabs, the import form and the settings
   * form.
   */
  public function content() {
    $import_tab = [
      $this->formBuilder->getForm(LingotekImportSettingsForm::class),
      $this->formBuilder->getForm(LingotekImportForm::class),
    ];

    return $import_tab;
  }

}
