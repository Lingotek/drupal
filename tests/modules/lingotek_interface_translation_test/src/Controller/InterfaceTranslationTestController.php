<?php

namespace Drupal\lingotek_interface_translation_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;

class InterfaceTranslationTestController extends ControllerBase {

  use StringTranslationTrait;

  public function content(Request $request) {
    $build = [];

    // Context management.
    $build['context_test']['one context'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('This is test of context', [], ['context' => 'multiple p']),
    ];
    $build['context_test']['another context'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('This is test of context', [], ['context' => 'multiple t']),
    ];
    $build['context_test']['no context'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('This is test of context'),
    ];

    // Plurals management.
    $count = $request->query->get('count') ?: 1;
    $build['plurals'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->formatPlural($count, 'This is a singular example', 'This is a plural @count example'),
      '#cache' => ['max-age' => 0],
    ];
    return $build;
  }

}
