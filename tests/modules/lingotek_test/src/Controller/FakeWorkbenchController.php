<?php

namespace Drupal\lingotek_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for faking the workbench in the TMS.
 *
 * @package Drupal\lingotek_test\Controller
 */
class FakeWorkbenchController extends ControllerBase {

  /**
   * Controller method for the workbench.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with the deserialized query string.
   */
  public function workbench(Request $request) {
    $query = [];
    parse_str($request->getQueryString(), $query);
    return new JsonResponse($query);
  }

}
