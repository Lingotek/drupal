<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Controller\ControllerBase;

class LingotekController extends ControllerBase {
  public function content() {
    $config = $this->config('lingotek.settings');
    $case = $config->get('case');
    if ($case == 'upper') {
      $markup = $this->t('HELLO, WORLD!');
    }
    else {
      $markup = $this->t('Hello, World!');
    }
    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }
}
