<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;

class LingotekManagementController extends LingotekControllerBase {

  public function content() {
    $markup = $this->t('HELLO, WORLD!');
    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }
}
