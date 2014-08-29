<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekDashboardController extends LingotekControllerBase {

  /**
   * Presents a dashboard overview page of translation status through Lingotek.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The page request.
   *
   * @return array
   *   The dashboard form, or a redirect to the connect page.
   */
  public function dashboardPage(Request $request) {
    return $this->formBuilder->getForm('\Drupal\lingotek\Form\LingotekSettingsForm', $request);
  }
}
