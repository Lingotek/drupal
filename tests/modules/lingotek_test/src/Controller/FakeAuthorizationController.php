<?php

namespace Drupal\lingotek_test\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class FakeAuthorizationController extends ControllerBase {

  const ACCESS_TOKEN = 'xxxxx-yyy-zzz-aaa-bbbbbbbbbb';

  public function authorize(Request $request) {
    // We need a flag for setting that we are already logged in.
    \Drupal::state()->set('lingotek_fake.logged_in', TRUE);

    // We provide a token and redirect back.
    $url = $request->get('redirect_uri');
    $url .= '#access_token=' . self::ACCESS_TOKEN;
    // JavaScript would take the token and post it to the same url. This is replaced below.
    $this->handleHandshake($request);
    return new RedirectResponse($url);
  }

  protected function handleHandshake(Request $request) {
    // Simulate the notification of content successfully uploaded.
    $urlHandshake = Url::fromRoute('lingotek.setup_account_handshake')->setAbsolute()->toString();
    $domain = parse_url($urlHandshake, PHP_URL_HOST);
    $requestToken = \Drupal::httpClient()->post($urlHandshake, [
      'body' => '{"access_token":"' . self::ACCESS_TOKEN . '"}',
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'cookies' => CookieJar::fromArray($request->cookies->all(), $domain),
      'http_errors' => FALSE,
    ]);

    assert(200 === $requestToken->getStatusCode());
    $response = Json::decode($requestToken->getBody());
    assert($response['status']);
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
