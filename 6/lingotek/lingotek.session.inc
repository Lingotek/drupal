<?php
/**
 * @file
 * Handles api calls, logging in and logging out of LingoTek
 */

/*
 * Session handler for api calls and logging in to the Lingotek platform
 */
class LingotekSession {

  private $url;
  private $community;
  private $login_id;

  private $logged_in = FALSE;
  private $headers = array();

  //API calls that don't need to log in.
  private $sessionless = array('login', 'keyLogin');

  /*
   * Constructor
   */
  function __construct() {
    $this->url = variable_get('lingotek_url', '') . "/lingopoint/api";
    $this->community = variable_get('lingotek_community', '');
    $this->login_id = variable_get('lingotek_login_id', '');
  }

  /*
   * Destructor which logs out of the lingotek platform
   */
  function __destruct() {
    if ($this->isLoggedIn()) {
      $this->logout();
    }
  }

  //--------------------
  //Public Functions

  /*
   * Determines if the user is currently logged in to the lingotek platform
   */
  public function isLoggedIn() {
    return $this->logged_in;
  }


  /*
   * Determines if the user can log in and if so, logs in
   */
  public function canLogIn() {
    if (!$this->isLoggedIn()) {
      $this->login();
    }
    return $this->isLoggedIn();
  }


  /*
   * Makes a request to a Lingotek API
   *
   * @param $api
   *  name of the api to call
   * @param $params
   *  key/value pairs to call as parameters in the api
   * @param $data
   *  associative array with further information to use if there is an error
   * @param $returnJson
   *  default TRUE, return json response or the all of the data?
   *
   * @return
   *  object representing the json returned by the api
   */
  public function request($api, $params = NULL, $data = NULL, $returnJson = TRUE) {
    if (!$this->isLoggedIn() && !in_array($api, $this->sessionless)) {
      $this->login();
    }

    $query = "";
    if (isset($params)) {
      $query = http_build_query($params, '', '&');
    }

    $this->headers["Content-Type"] = "application/x-www-form-urlencoded;";

    $response = drupal_http_request
    (
      $this->url . "/" . $api,
      $this->headers,
      'POST',
      $query
    );

    if ($returnJson) {
      $json = json_decode($response->data);
      if ($json->results != "success") {
        lingotek_error
        (
          'API ' . $api . ' FAILED',
          array
          (
            'params' => $params,
            'response' => $json,
          ),
          1
        );
      }
      return $json;
    }
    else {
      return $response;
    }
  }

  /*
   * Downloads a document from Lingotek
   *
   * @param $api
   *  name of the api to call
   * @param $params
   *  key/value pairs to call as parameters in the api
   *
   * @return
   *  location of the file
   */
  public function download($api, $params) {
    $result = $this->request($api, $params, NULL, FALSE);

    $tmpFile = tempnam("/tmp", "lingotek");
    $fp = fopen($tmpFile, 'w');

    fwrite($fp, $result->data);
    fclose($fp);

    $json = json_decode($result->data);
    if (isset($json->results)) {
      lingotek_error
      (
        'Error downloading document',
        array
        (
          'params' => $params,
          'response' => $file_uri,
        ),
        1
      );
      return "";
    }

    $text = "";
    $file = FALSE;

    //downloadDocument zips the file up
    if ($api == 'downloadDocument') {
      $zip = new ZipArchive;
      $zip->open($tmpFile);
      $name = $zip->getNameIndex(0);
      $file = $zip->getStream($name);
    }
    else {
      $file = fopen($tmpFile, "r");
    }

    if ($file) {
      while (!feof($file)) {
        $text .= fread($file, 2);
      }
    }
    fclose($file);

    return $text;
  }

  //--------------------
  //Static Functions

  /*
   * Hash the key for logging in for this user.
   */
  public static function create_mac($json_msg) {
    $key = variable_get('lingotek_login_key', '');
    $resp = base64_encode(hash_hmac('sha256', $json_msg, pack('H*', $key), TRUE));
    return ($resp);
  }

  //--------------------
  //Private Functions

  /*
   * login to the lingotek platform
   */
  private function login($version = "3.10", $xml = FALSE) {
    $arr = array
    (
      'community' => $this->community,
      'login_id' => $this->login_id,
      'time' => time(),
    );
    $json_str = json_encode($arr);

    $params = array
    (
      "auth_json" => $json_str,
      "hmac" => LingotekSession::create_mac($json_str),
      "version" => $version,
      "returnXML" => $xml,
    );
    $data = $this->request('keyLogin', $params, NULL, FALSE);
    $this->headers = array("Cookie" =>  $data->headers['Set-Cookie']);
    $response = json_decode($data->data);

    if ($response && $response->results == "success") {
      $this->logged_in = TRUE;
    }
    else {
      $this->logged_in = FALSE;
      lingotek_error
      (
        "Unable to log in",
        array
        (
          'params' => $params,
          'response' => $response,
        ),
        2,
        WATCHDOG_WARNING
      );
    }
  }

  /*
   * logout from the lingotek platform
   */
  private function logout() {
    $json = $this->request('logout');
    if ($json->results == "success") {
      $this->logged_in = FALSE;
    }
    else {
      $this->logged_in = TRUE;
      lingotek_error('Unable to log out.');
    }
  }
}
