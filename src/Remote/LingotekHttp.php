<?php

namespace Drupal\lingotek\Remote;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * Lingotek HTTP implementation using Guzzle.
 */
class LingotekHttp implements LingotekHttpInterface {

  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * An array for storing headers info
   *
   * @var array
   */
  protected $headers = array();

  /**
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->config = $config_factory->get('lingotek.settings');
    $this->setDefaultHeaders();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /*
   * send a GET request
   */
  public function get($path, $args = array()) {
    $options = [];
    if (count($args)) {
      $options = [RequestOptions::QUERY => $args];
    }
    return $this->httpClient->get($this->config->get('account.sandbox_host') . $path,
      [
        RequestOptions::HEADERS => $this->headers,
      ] + $options
    );
  }

  /*
   * send a POST request
   */
  public function post($path, $args = array(), $use_multipart = FALSE) {
    $options = [];
    if (count($args) && $use_multipart) {
      $multipart = [];
      foreach ($args as $name => $contents) {
        $multipart[] = ['name' => $name, 'contents' => $contents];
      }
      $options[RequestOptions::MULTIPART] = $multipart;
    }
    elseif (count($args) && !$use_multipart) {
      $options[RequestOptions::FORM_PARAMS] = $args;
    }
    return $this->httpClient->post($this->config->get('account.sandbox_host') . $path,
      [
        RequestOptions::HEADERS => $this->headers,
      ] + $options
    );
  }

  /*
   * send a DELETE request
   */
  public function delete($path, $args = array()) {
    // Let the post method masquerade as a DELETE
    $this->addHeader('X-HTTP-Method-Override', 'DELETE');
    $options = [];
    if (count($args)) {
      $options = [RequestOptions::QUERY => $args];
    }
    return $this->httpClient->delete($this->config->get('account.sandbox_host') . $path,
      [
        RequestOptions::HEADERS => $this->headers,
      ] + $options
    );
  }

  /*
   * send a PATCH request
   */
  public function patch($path, $args = array(), $use_multipart = FALSE) {
    // Let the post method masquerade as a PATCH
    $this->addHeader('X-HTTP-Method-Override', 'PATCH');

    return $this->httpClient->patch($this->config->get('account.sandbox_host') . $path,
      [
        RequestOptions::FORM_PARAMS => $args,
        RequestOptions::HEADERS => $this->headers,
      ]
    );
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

}
