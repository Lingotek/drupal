<?php

/*
 * a simple wrapper to Drupal http functions
 *
 * @since 0.1
 */
class LingotekHttp {
  protected $headers = array();

  /*
   * formats a request as multipart
   * greatly inspired from mailgun wordpress plugin
   *
   * @since 0.1
   */
  public function format_as_multipart(&$body) {
    $boundary = '----------------------------32052ee8fd2c'; // arbitrary boundary

    $this->headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

    $data = '';

    foreach ($body as $key => $value) {
      if (is_array($value)) {
        // FIXME is this block useful for Lingotek ?
        foreach($value as $k => $v) {
          $data .= '--' . $boundary . "\r\n";
          $data .= 'Content-Disposition: form-data; name="' . $key . '[' . $k . ']"' . "\r\n\r\n";
          $data .= $v . "\r\n";
        }
      }
      else {
        $data .= '--' . $boundary ."\r\n";
        $data .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
        $data .= $value . "\r\n";
      }
    }

    $body = $data . '--' . $boundary . '--';
  }

  /*
   * send a POST request
   *
   * @since 0.1
   */
  public function post($url, $args  = array()) {
    $request = Drupal::httpClient()->post($url);
    $request->addHeaders($this->headers);
    $request->addBody($args);
    try {
      $response = $request->send();
      $data = $response->getBody(TRUE);
    }
    catch (RequestException $e) {
      watchdog_exception('lingotek', $e);
    }
    return $data;
  }

  /*
   * send a GET request
   *
   * @since 0.1
   */
  public function get($url, $args  = array()) {
    return wp_remote_get($url, array('headers' => $this->headers, 'body' => $args));
  }

  /*
   * send a DELETE request
   *
   * @since 0.1
   */
  public function delete($url, $args  = array()) {
    return wp_remote_request($url, array('method' => 'DELETE', 'headers' => $this->headers, 'body' => $args));
  }

  /*
   * send a PATCH request
   *
   * @since 0.1
   */
  public function patch($url, $args  = array()) {
    return wp_remote_request($url, array('method' => 'PATCH', 'headers' => $this->headers, 'body' => $args));
  }
}

