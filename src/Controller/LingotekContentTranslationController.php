<?php

/**
 * @file
 * Contains \Drupal\lingotek\Controller\LingotekContentTranslationController.
 */

namespace Drupal\lingotek\Controller;

use Drupal\content_translation\Controller\ContentTranslationController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Override default translate page for Content Entities.
 */
class LingotekContentTranslationController extends ContentTranslationController  {

  /**
   * {@inheritdoc}
   */
  public function overview(Request $request, $entity_type_id = NULL) {
    $build = parent::overview($request, $entity_type_id);
    return \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekContentTranslationForm', $build);
  }
}

