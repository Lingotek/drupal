<?php

namespace Drupal\lingotek\Remote;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Config\ConfigFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * a simple wrapper to Drupal http functions
 *
 * @since 0.1
 */
class LingotekHttp {

  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * An array for storing header info
   *
   * @var array
   */
  protected $headers = array();

  public function __construct(ClientInterface $httpClient, ConfigFactory $config) {
    $this->httpClient = $httpClient;
    $this->config = $config->get('lingotek.settings');
    $this->setDefaultHeaders();
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

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
   * send a request specified by method
   *
   * @since 0.1
   */
  public function request($path, $args = array(), $method = 'GET') {
    $url = $this->config->get('account.host') . $path;
    $request = $this->httpClient->createRequest($method, $url);
    $request->setHeaders($this->headers);
    if (!empty($args)) {
      $request->setQuery($args);
    }
    try {
      $response = $this->httpClient->send($request);
      $data = $response->json();
      $token = $response->getHeader('access_token');
    }
    catch (RequestException $e) {
      watchdog('lingotek', 'Request to Lingotek service failed: %error', array('%error' => $e->getMessage()));
      drupal_set_message(t('Request to Lingotek service failed: %error', array('%error' => $e->getMessage())) , 'warning');
      return FALSE;
    }
    if (!empty($token)) {
      // TODO: save token to state info.
    }
    return $data;
  }

  /*
   * send a GET request
   */
  public function get($path, $args = array()) {
    return $this->request($path, $args, 'GET');
  }

  /*
   * send a POST request
   */
  public function post($path, $args = array()) {
    return $this->request($path, $args, 'POST');
  }

  /*
   * send a DELETE request
   */
  public function delete($path, $args = array()) {
    return $this->request($path, $args, 'DELETE');
  }

  /*
   * send a PATCH request
   */
  public function patch($path, $args = array()) {
    return $this->request($path, $args, 'PATCH');
  }

  /*
   * add a header pair to the request headers.
   */
  public function addHeader($header_name, $header_content) {
    $this->headers[$header_name] = $header_content;
    return $this;
  }

  /*
   * set the headers for the request.
   */
  public function setHeaders($headers = array()) {
    $this->headers = $headers;
    return $this;
  }

  public function setDefaultHeaders() {
    if ($token = $this->config->get('account.access_token')) {
      $this->addHeader('Authorization', 'bearer ' . $token);
    }
    return $this;
  }

  public function getCurrentToken() {
    return $this->config->get('account.access_token');
  }

}
