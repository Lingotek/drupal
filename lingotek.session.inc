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
    $this->url = variable_get('lingotek_url', 'http://myaccount.lingotek.com') . "/lingopoint/api";
    $this->community = variable_get('lingotek_community', '');
    $this->login_id = variable_get('lingotek_login_id', '');
    $this->key = variable_get('lingotek_login_key', '');
    $this->password = variable_get('lingotek_password', '');
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

    $response = drupal_http_request(
      $this->url . "/" . $api,
      array(
        'headers' => $this->headers,
        'method' => 'POST',
        'data' => $query
      )
    );

    if ($returnJson) {
      if (isset($response->data) && ($json = json_decode($response->data)) && $json->results == "success") {
        return $json;
      }
      else {
        lingotek_error(
          'API ' . $api . ' FAILED',
          array(
            'params' => $params,
            'error' => isset($response->error) ? $response->error : "",
            'response' => isset($json) ? $json : "",
            'logged_in' => $this->logged_in,
          ),
          1
        );
        $json = new stdClass();
        $json->results = 'fail';
        return $json;
      }
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

    if (!isset($result->data) || ( ($json = json_decode($result->data)) && isset($json->results) )) {
      lingotek_error(
        'Error downloading document',
        array(
          'params' => $params,
          'response' => $result,
        ),
        1
      );
      return "";
    }

    $tmpFile = tempnam("/tmp", "lingotek");
    $fp = fopen($tmpFile, 'w');

    fwrite($fp, $result->data);
    fclose($fp);

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
    $resp = base64_encode(hash_hmac('sha256', $json_msg, pack('H*', variable_get('lingotek_login_key', '')), TRUE));
    return ($resp);
  }

  //--------------------
  //Private Functions

  /*
   * login to the lingotek platform
   */
  private function login($version = "3.11", $xml = FALSE) {

    if ($this->key == "") {
      $params = array(
        'userLogin' => $this->login_id,
        'password' => $this->password,
        'version' => $version,
        'community' => $this->community,
      );
      $login = 'login';
    }
    else {
      $arr = array(
        'community' => $this->community,
        'login_id' => $this->login_id,
        'time' => time(),
      );
      $json_str = json_encode($arr);

      $params = array(
        "auth_json" => $json_str,
        "hmac" => LingotekSession::create_mac($json_str),
        "version" => $version,
        "returnXML" => $xml,
      );
      $login = 'keyLogin';
    }

    $data = $this->request($login, $params, NULL, FALSE);

    if (!isset($data->error) && ($response = json_decode($data->data)) && $response->results == "success") {
      $this->headers = array("Cookie" =>  $data->headers['set-cookie']);
      $this->logged_in = TRUE;
    }
    else {
      //Delete the key so they can use a password to log in again (otherwise the password fails every time since it defaults to using the key)
      if(!isset($data->error)) {
        variable_del("lingotek_login_key");
      }
      $this->logged_in = FALSE;
      lingotek_error(
        "Unable to log in",
        array(
          'params' => $params,
          'error' => isset($data->error) ? $data->error : "",
          'response' => isset($response) ? $response : "",
        ),
        2,
        isset($data->error) ? WATCHDOG_ERROR : WATCHDOG_WARNING
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
