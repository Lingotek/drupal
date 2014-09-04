<?php

/**
 * @file
 * Contains \Drupal\lingotek\Remote\LingotekApi.
 */

namespace Drupal\lingotek\Remote

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Core\Config\ConfigFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LingotekApi implements LingotekApiInterface {
  
  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a LingotekApi object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A Guzzle client object.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   A logger instance.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  protected function __construct(ClientInterface $http_client, ConfigFactory $config, LoggerInterface $logger) {
    $this->httpClient = $http_client;
    $this->config = $config;
    $this->logger = $logger;
  }

  public static function create(ContainerInterface $container) {
    if (empty(self::$instance)) {
      self::$instance = new static(
        $container->get('http_client'),
        $container->get('config.factory'),
        $container->get('logger.factory')->get('lingotek')
      );
    }
    return self::$instance;
  }

  public function get_token_details($access_token) {
  }

  public function upload_document($args) {
  }

  public function patch_document($id, $args) {
  }

  public function delete_document($id) {
  }

  public function get_documents($args = array()) {
  }

  public function document_exists($id) {
  }

  public function get_translations_status($id) {
  }

  public function request_translation($id, $locale) {
  }

  public function get_translation($id, $locale) {
  }

  public function delete_translation($id, $locale) {
  }

  public function get_connect_url($redirect_uri) {
  }

  public function get_communities() {
  }

  public function get_projects($community_id) {
  }

  public function get_vaults($community_id) {
  }

  public function get_workflows($community_id) {
  }

  protected function request($path, $params = array(), $method = 'GET') {
    $default_params = array(
      'token' => $this->getToken(),
    );
    $request = $this->httpClient->createRequest($method, $path);
    $request->addHeader('header stuff from WP module');

    try {
      $response = $this->httpClient->send($request);
    }
    catch (RequestException $e) {
      $this->logger->warning('Request to Lingotek service failed: %error', array('%error' => $e->getMessage()));
      drupal_set_message(t('Request to Lingotek service failed: %error', array('%error' => $e->getMessage())) , 'warning');
      return FALSE;
    }

    $message = $response->getBody(TRUE);
    $token = $response->getHeader('access_token')
    // TODO: save token to state info.
    return $message;
  }
}
