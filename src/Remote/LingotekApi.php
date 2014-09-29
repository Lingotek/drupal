<?php

/**
 * @file
 * Contains \Drupal\lingotek\Remote\LingotekApi.
 */

namespace Drupal\lingotek\Remote;

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\lingotek\Remote\LingotekHttpInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * a simple connector to the Lingotek Translation API
 */
class LingotekApi implements LingotekApiInterface {

  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \Drupal\lingotek\Remote\LingotekHttp
   */
  protected $lingotekClient;

  /**
   * Constructs a LingotekApi object.
   */
  public function __construct(LingotekHttpInterface $client) {
    $this->lingotekClient = $client;
  }

  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('lingotek.http_client')
    );
  }

  public function getAccountInfo() {
    $access_token = $this->lingotekClient->getCurrentToken();
    $account_info = $this->lingotekClient->get('/auth/oauth2/access_token_info?access_token=' . $access_token);
    return $account_info;
  }

  public function addDocument($args) {
    $response = $this->lingotekClient->post($this->api_url . '/api/document', $args, TRUE);
    if ($response->getStatusCode() == '202') {
      $data = $response->json();
      if (!empty($data['properties']['id'])) {
        return $data['properties']['id'];
      }
    }
    // TODO: log warning
    return FALSE;
  }

  public function patchDocument($id, $args) {
  }

  public function deleteDocument($id) {
  }

  public function getDocument($id) {
    return $this->lingotekClient->get($this->api_url . '/api/document', array('doc_id' => $id));
  }

  public function documentExists($id) {
  }

  public function getDocumentStatus($id) {
    return $this->lingotekClient->get($this->api_url . '/api/document/' . $id . '/status');
  }

  public function addDocumentTranslation($id, $locale) {
    return $this->lingotekClient->post($this->api_url . '/api/document/' . $id . '/translation', array('locale_code' => $locale));
  }

  public function getTranslation($id, $locale) {
  }

  public function deleteTranslation($id, $locale) {
  }

  public function getConnectUrl($redirect_uri) {
  }

  public function getCommunities() {
    $response = $this->lingotekClient->get('/api/community?limit=50');
    return $this->formatResponse($response);
  }

  public function getProjects($community_id) {
    $response = $this->lingotekClient->get('/api/project', array('community_id' => $community_id));
    return $this->formatResponse($response);
  }

  public function getVaults($community_id) {
    $response = $this->lingotekClient->get('/api/vault', array('community_id' => $community_id));
    return $this->formatResponse($response);
  }

  public function getWorkflows($community_id) {
    $response = $this->lingotekClient->get('/api/workflow', array('community_id' => $community_id));
    return $this->formatResponse($response);
  }

  protected function formatResponse($response) {
    $formatted_response = array();
    if (!empty($response['entities'])) {
      foreach ($response['entities'] as $entity) {
        if (!empty($entity['properties']['id']) && !empty($entity['properties']['title'])) {
          $formatted_response[$entity['properties']['id']] = $entity['properties']['title'];
        }
      }
    }
    return $formatted_response;
  }
}
