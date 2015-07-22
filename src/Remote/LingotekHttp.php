<?php

namespace Drupal\lingotek\Remote;

use Drupal\Core\Config\ConfigFactory;
use Drupal\lingotek\Remote\LingotekHttpInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * a simple wrapper to Drupal http functions
 */
class LingotekHttp implements LingotekHttpInterface {

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
    $this->config     = $config->getEditable('lingotek.settings');
    $this->setDefaultHeaders();
  }

  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /*
   * send a request specified by method
   *
   * @since 0.1
   */
  public function request($path, $args = array(), $method = 'GET', $use_multipart = FALSE) {
    $url = $this->config->get('account.sandbox_host') . $path;
    $request = $this->httpClient->createRequest($method, $url);
    $request->setHeaders($this->headers);
    if ($method == 'POST') {
      $postBody = $request->getBody();
      $postBody->forceMultipartUpload($use_multipart);
      $postBody->replaceFields($args);
    }
    elseif (!empty($args)) {
      $request->setQuery($args);
    }
    try {
      $response = $this->httpClient->send($request);
    }
     catch (RequestException $e) {
      watchdog_exception('lingotek', $e, 'Request to Lingotek service failed: %error', array('%error' => $e->getMessage()));
      drupal_set_message(t('Request to Lingotek service failed: %error', array('%error' => $e->getMessage())), 'warning');
      throw $e;
    }
    return $response;
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
  public function post($path, $args = array(), $use_multipart = FALSE) {
    try {
      $response = $this->request($path, $args, 'POST', $use_multipart);
    } catch (Exception $e) {
      throw $e;
    }
    return $response;
  }

  /*
   * send a DELETE request
   */
  public function delete($path, $args = array()) {
    // Let the post method masquerade as a DELETE
    $this->addHeader('X-HTTP-Method-Override', 'DELETE');
    return $this->request($path, $args, 'POST');
  }

  /*
   * send a PATCH request
   */
  public function patch($path, $args = array(), $use_multipart = FALSE) {
    // Let the post method masquerade as a PATCH
    $this->addHeader('X-HTTP-Method-Override', 'PATCH');
    return $this->request($path, $args, 'POST', $use_multipart);
  }

  public function getCurrentToken() {
    return $this->config->get('account.access_token');
  }

  /*
   * add a header pair to the request headers.
   */
  protected function addHeader($header_name, $header_content) {
    $this->headers[$header_name] = $header_content;
    return $this;
  }

  /*
   * set the headers for the request.
   */
  protected function setHeaders($headers = array()) {
    $this->headers = $headers;
    return $this;
  }

  protected function setDefaultHeaders() {
    $this->addHeader('Accept', '*/*');
    if ($token = $this->config->get('account.access_token')) {
      $this->addHeader('Authorization', 'bearer ' . $token);
    }
    return $this;
  }

  /*
   * formats a request as multipart
   * greatly inspired from mailgun wordpress plugin
   *
   * @since 0.1
   */
  public function formatAsMultipart(&$body) {
    $boundary = '----------------------------32052ee8fd2c';// arbitrary boundary

    $this->addHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary);
    $data = '';

    foreach ($body as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $k => $v) {
          $data .= '--' . $boundary . "\r\n";
          $data .= 'Content-Disposition: form-data; name="' . $key . '[' . $k . ']"' . "\r\n\r\n";
          $data .= $v . "\r\n";
        }
      } else {
        $data .= '--' . $boundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
        $data .= $value . "\r\n";
      }
    }

    $body = $data . '--' . $boundary . '--';
  }

}
