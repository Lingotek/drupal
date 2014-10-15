<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;

class LingotekManagementController extends LingotekControllerBase {

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $markup = $this->t('Lingotek Bulk Management Grid (coming soon)');
    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }
}
