<?php

namespace Drupal\lingotek_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\lingotek\Controller\LingotekSetupController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class FakeAuthorizationController extends ControllerBase {

  public function authorize(Request $request) {
    // We need a flag for setting that we are already logged in.
    \Drupal::state()->set('lingotek_fake.logged_in', TRUE);

    // We provide a token and redirect back.
    $url = $request->get('redirect_uri');
    $url .= '#access_token=ef4b4d69-5be2-4513-b4f1-7e0f6f9511a0';
    // JavaScript would take the token and put it in a cookie. This is imitated below
    setcookie(LingotekSetupController::LINGOTEK_TOKEN_COOKIE, 'ef4b4d69-5be2-4513-b4f1-7e0f6f9511a0', 0, '/');
    return new RedirectResponse($url);
  }

  public function authorizeNoRedirect(Request $request) {
    // We need a flag for setting that we are already logged in.
    \Drupal::state()->set('lingotek_fake.logged_in', TRUE);

    // We provide a token and redirect back.
    $url = $request->get('redirect_uri');
    $url .= '#access_token=test_token';
    return new RedirectResponse($url);
  }

  public function createAccountForm(Request $request) {
    // We redirect as will happen after clicking cancel on the form.
    $url = $request->get('app');
    return new RedirectResponse($url);
  }

}
