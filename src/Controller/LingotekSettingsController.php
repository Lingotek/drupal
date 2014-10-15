<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;

class LingotekSettingsController extends LingotekControllerBase {

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $markup = $this->t('Lingotek Translation Settings (coming soon)');
    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }
}
