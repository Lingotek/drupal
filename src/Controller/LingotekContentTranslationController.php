<?php

namespace Drupal\lingotek\Controller;

use Drupal\content_translation\Controller\ContentTranslationController;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\lingotek\Form\LingotekContentTranslationForm;

/**
 * Override default translate page for Content Entities.
 */
class LingotekContentTranslationController extends ContentTranslationController {

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    $build = parent::overview($route_match, $entity_type_id);
    return \Drupal::formBuilder()->getForm(LingotekContentTranslationForm::class, $build);
  }

}
