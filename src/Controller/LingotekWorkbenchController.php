<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

/**
 * Class LingotekWorkbenchController.
 *
 * @package Drupal\lingotek\Controller
 *
 * @deprecated Use LingotekWorkbenchRedirectController instead. Since 8.x-2.2.
 */
class LingotekWorkbenchController extends LingotekControllerBase {

  /**
   * Load a document for redirect.
   *
   * @param string $doc_id
   *   The Lingotek document id.
   * @param string $locale
   *   The Lingotek locale.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   A redirect.
   *
   * @deprecated Use LingotekWorkbenchRedirectController instead. Since 8.x-2.2.
   */
  public function loadDocument($doc_id, $locale) {
    return $this->workbenchPageRedirect($doc_id, $locale);
  }

  /**
   * Redirect to the TMS Workbench.
   *
   * @param string $doc_id
   *   The Lingotek document id.
   * @param string $locale
   *   The Lingotek locale.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   A redirect.
   *
   * @deprecated Use LingotekWorkbenchRedirectController instead. Since 8.x-2.2.
   */
  protected function workbenchPageRedirect($doc_id, $locale) {
    // Get account settings to build workbench link.
    $account = $this->lingotek->get('account');

    // Generate an external link to the Lingotek Workbench.
    $link = self::generateWorkbenchLink(
      $doc_id,
      $locale,
      $account['default_client_id'],
      $account['access_token'],
      $account['login_id'],
      $account['login_id'],
      $account['host']
    );
    return new TrustedRedirectResponse(Url::fromUri($link)->toString());
  }

  /**
   * Generates a workbench uri.
   *
   * @param string $document_id
   *   The Lingotek document id.
   * @param string $locale_code
   *   The Lingotek locale.
   * @param string $client_id
   *   The Lingotek client id.
   * @param string $access_token
   *   The Lingotek access token.
   * @param string $login_id
   *   The Lingotek login id.
   * @param string $acting_login_id
   *   The Lingotek acting login id.
   * @param string $base_url
   *   The site base url.
   * @param int|null $expiration
   *   The expiration time of this link.
   *
   * @return string
   *   The workbench uri.
   *
   * @deprecated Use LingotekWorkbenchRedirectController instead. Since 8.x-2.2.
   */
  public static function generateWorkbenchLink($document_id, $locale_code, $client_id, $access_token, $login_id, $acting_login_id = "anonymous", $base_url = "https://myaccount.lingotek.com", $expiration = NULL) {
    // 30-minute default, otherwise use $expiration as passed in.
    $expiration_default = time() + (60 * 30);
    $expiration = is_null($expiration) ? $expiration_default : $expiration;
    $data = [
      'document_id'     => $document_id,
      'locale_code'     => $locale_code,
      'client_id'       => $client_id,
      'login_id'  => $login_id,
      'acting_login_id' => $acting_login_id,
      'expiration'      => $expiration,
    ];
    $query_data = utf8_encode(http_build_query($data));
    $hmac = urlencode(base64_encode(hash_hmac('sha1', $query_data, $access_token, TRUE)));
    $workbench_url = $base_url . '/lingopoint/portal/wb.action?' . $query_data . "&hmac=" . $hmac;
    return $workbench_url;
  }

}
