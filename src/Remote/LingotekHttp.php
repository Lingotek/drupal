<?php

namespace Drupal\lingotek\Remote;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
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
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->config = $config_factory->get('lingotek.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function get($path, $args = []) {
    $options = [];
    if (count($args)) {
      $options = [RequestOptions::QUERY => $args];
    }
    return $this->httpClient->get($this->getBaseUrl() . $path,
      [
        RequestOptions::HEADERS => $this->getDefaultHeaders(),
      ] + $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function post($path, $args = [], $use_multipart = FALSE) {
    $options = [];
    if (count($args) && $use_multipart) {
      $multipart = [];
      foreach ($args as $name => $contents) {
        if (is_array($contents)) {
          foreach ($contents as $content) {
            $multipart[] = ['name' => $name, 'contents' => $content];
          }
        }
        else {
          $multipart[] = ['name' => $name, 'contents' => $contents];
        }
      }
      $options[RequestOptions::MULTIPART] = $multipart;
    }
    elseif (count($args) && !$use_multipart) {
      $options[RequestOptions::FORM_PARAMS] = $args;
    }
    return $this->httpClient->post($this->getBaseUrl() . $path,
      [
        RequestOptions::HEADERS => $this->getDefaultHeaders(),
      ] + $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function delete($path, $args = []) {
    // Let the post method masquerade as a DELETE
    $options = [];
    if (count($args)) {
      $options = [RequestOptions::QUERY => $args];
    }
    return $this->httpClient->delete($this->getBaseUrl() . $path,
      [
        RequestOptions::HEADERS => $this->getDefaultHeaders() +
          ['X-HTTP-Method-Override' => 'DELETE'],
      ] + $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function patch($path, $args = [], $use_multipart = FALSE) {
    $options = [];
    if (count($args) && $use_multipart) {
      $multipart = [];
      foreach ($args as $name => $contents) {
        if (is_array($contents)) {
          foreach ($contents as $content) {
            $multipart[] = ['name' => $name, 'contents' => $content];
          }
        }
        else {
          $multipart[] = ['name' => $name, 'contents' => $contents];
        }
      }
      $options[RequestOptions::MULTIPART] = $multipart;
    }
    elseif (count($args) && !$use_multipart) {
      $options[RequestOptions::FORM_PARAMS] = $args;
    }
    return $this->httpClient->patch($this->getBaseUrl() . $path,
      [
        RequestOptions::HEADERS => $this->getDefaultHeaders() +
          // Let the post method masquerade as a PATCH.
          ['X-HTTP-Method-Override' => 'PATCH'],
      ] + $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentToken() {
    return $this->config->get('account.access_token');
  }

  /**
   * Get the headers that are used in every request.
   *
   * @return string[]
   */
  protected function getDefaultHeaders() {
    $headers = ['Accept' => '*/*'];
    if ($token = $this->getCurrentToken()) {
      $headers['Authorization'] = 'bearer ' . $token;
    }
    return $headers;
  }

  /**
   * Gets the API base url.
   *
   * @return string
   *   The API base url.
   */
  protected function getBaseUrl() {
    $base_url = $this->config->get('account.host');
    return $base_url;
  }

}
