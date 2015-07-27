<?php

namespace Drupal\lingotek_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class FakeAuthorizationController extends ControllerBase {

  public function authorize(Request $request) {
    // We need a flag for setting that we are already logged in.
    \Drupal::state()->set('lingotek_fake.logged_in', TRUE);

    // We provide a token and redirect back.
    $url = $request->get('redirect_uri');
    $url .= '&access_token=test_token';
    return new RedirectResponse($url);
  }

}
