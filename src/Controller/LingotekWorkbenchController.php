<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LingotekWorkbenchController extends LingotekControllerBase {

  public function loadDocument($doc_id, $locale) {
    return $this->workbenchPageRedirect($doc_id, $locale);
  }

  protected function workbenchPageRedirect($doc_id, $locale) {
    // Get a current-user email to use as the workbench's acting login id.
    global $user;
    $acting_login_id = $user->getEmail();

    // Get account settings to build workbench link.
    $account = $this->L->get('account');

    // generate an external link to the Lingotek Workbench
    $link = self::generateWorkbenchLink(
      $doc_id,
      $locale,
      $account['default_client_id'],
      $account['access_token'],
      $account['login_id'],
      $acting_login_id,
      $account['host']
    );
    return new RedirectResponse(Url::fromUri($link)->toString() );
  }

  /*
   * generates a workbench link
   * function provided by Matt Smith from Lingotek
   *
   * @param string $document_id
   * @param string $locale_code
   * @param string $client_id
   * @param string $access_token
   * @param string $login_id
   * @param string $acting_login_id
   * @param string $base_url
   * @param int|null $expiration
   * @return string workbench link
   */
  public static function generateWorkbenchLink($document_id, $locale_code, $client_id, $access_token, $login_id, $acting_login_id = "anonymous", $base_url = "https://myaccount.lingotek.com", $expiration = NULL) {
    $expiration_default = time() + (60 * 30); // 30-minute default, otherwise use $expiration as passed in
    $expiration = is_null($expiration) ? $expiration_default : $expiration;
    $data = array(
      'document_id'     => $document_id,
      'locale_code'     => $locale_code,
      'client_id'       => $client_id,
      'login_id'  => $login_id,
      'acting_login_id' => $acting_login_id,
      'expiration'      => $expiration
    );
    $query_data = utf8_encode(http_build_query($data));
    $hmac = urlencode(base64_encode(hash_hmac('sha1', $query_data, $access_token, TRUE)));
    $workbench_url = $base_url . '/lingopoint/portal/wb.action?' . $query_data . "&hmac=" . $hmac;
    return $workbench_url;
  }

}
