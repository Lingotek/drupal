<?php
// $Id:lingotek.session.inc,LingoTek$
/**
 * @file
 * Handles api calls, logging in and logging out of LingoTek
 */

/*
 * Session handler for api calls and logging in to the Lingotek platform
 */
class LingotekSession {
  private $ch;
  private $downloadFile;

  private $url;
  private $community;
  private $login_id;
  private $logged_in;

  /*
   * Constructor which logs in to the lingotek platform
   */
  function __construct() {
    //error_log("LINGOTEK: logging in");
    $this->url = variable_get('lingotek_url', '') . "/lingopoint/api";
    $this->community = variable_get('lingotek_community', '');
    $this->login_id = variable_get('lingotek_login_id', '');
    $this->login();
  }

  /*
   * Destructor which logs out of the lingotek platform
   */
  function __destruct() {
    //error_log("LINGOTEK: logging out");
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
   * Makes a request to a Lingotek API
   *
   * @param $api
   *  name of the api to call
   * @param $params
   *  key/value pairs to call as parameters in the api
   *
   * @return
   *  object representing the json returned by the api
   */
  public function request($api, $params) {
    curl_setopt($this->ch, CURLOPT_URL, $this->url . "/" . $api);
    curl_setopt($this->ch, CURLOPT_POST, TRUE);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);

    return json_decode(curl_exec($this->ch));
  }

  /*
   * Uploads a document to Lingotek
   *
   * @param $api
   *  name of the api to call
   * @param $params
   *  key/value pairs to call as parameters in the api
   * @file
   *  File location to be passed in to the api
   *
   * @return
   *  object representing the json returned by the api
   */
  public function upload($api, $params, $file) {
    $paramscopy = $params;
    $paramscopy['file'] = "@" . $file;

    curl_setopt($this->ch, CURLOPT_URL, $this->url . "/" . $api);
    curl_setopt($this->ch, CURLOPT_POST, TRUE);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $paramscopy);

    return json_decode(curl_exec($this->ch));
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
    $tmpFile = tempnam("/tmp", "lingotek");
    $fp = fopen($tmpFile, "w");
    curl_setopt($this->ch, CURLOPT_FILE, $fp);
    curl_setopt($this->ch, CURLOPT_URL, $this->url . "/" . $api);
    curl_setopt($this->ch, CURLOPT_POST, TRUE);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);
    curl_exec($this->ch);
    copy($tmpFile, "/tmp/" . $this->downloadFile);
    fclose($fp);
    //Restore returning output instead of to a file
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
    return "/tmp/" . $this->downloadFile;
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
  private function login($version = "3.4", $xml = FALSE) {
    $this->ch = curl_init();
    curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, 'setCookies'));
    curl_setopt($this->ch, CURLOPT_URL, $this->url . "/keyLogin");
    curl_setopt($this->ch, CURLOPT_COOKIESESSION, TRUE);
    curl_setopt($this->ch, CURLOPT_POST, TRUE);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);


    $arr = array
    (
      'community' => $this->community,
      'login_id' => $this->login_id,
      'time' => time(),
    );

    $json_str = json_encode($arr);

    curl_setopt($this->ch, CURLOPT_POSTFIELDS, array
    (
      "auth_json" => $json_str,
      "hmac" => LingotekSession::create_mac($json_str),
      "version" => $version,
      "returnXML" => $xml,
    ));

    if (curl_exec($this->ch) == FALSE) {
      error_log("LINGOTEK: Unable to log in - CURL ERROR: " . curl_error($this->ch) . " - " . __FILE__ . ":" . __LINE__);
      $this->logged_in = FALSE;
      return;
    }
    $response = json_decode(curl_exec($this->ch));

    if ($response->results == "success") {
      $this->logged_in = TRUE;
    }
    else {
      $this->logged_in = FALSE;
      error_log("LINGOTEK: Unable to log in - " . __FILE__ . ":" . __LINE__);
    }
  }

  /*
   * logout from the lingotek platform
   */
  private function logout() {
    curl_setopt($this->ch, CURLOPT_URL, $this->url . "/logout");
    curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
    $response = json_decode(curl_exec($this->ch));
    if ($response->results == "success") {
      $this->logged_in = FALSE;
    }
    else {
      $this->logged_in = TRUE;
      error_log("LINGOTEK: Unable to log out - " . __FILE__ . ":" . __LINE__);
    }
  }

  /*
   * store session data
   */
  private function setCookies($ch, $string) {
    $length = drupal_strlen($string);

    if (strncmp($string, "Content-Disposition:", 20) == 0) {
      if (preg_match('/.*filename="(.+)".*/', $string, $regs)) {
        $this->downloadFile = $regs[1];
      }
    }

    if (strncmp($string, "Set-Cookie:", 11) == 0) {
      $cookiestr = trim(drupal_substr($string, 11, -1));
      curl_setopt($ch, CURLOPT_COOKIE, $cookiestr);
    }

    return $length;
  }
}
