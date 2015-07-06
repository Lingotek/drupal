<?php

/**
 * @file
 * Contains \Drupal\lingotek\Remote\LingotekApi.
 */

namespace Drupal\lingotek\Remote;

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\lingotek\Remote\LingotekHttpInterface;
use Drupal\lingotek\Exception\LingotekApiException;
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
    return new static(
            $container->get('lingotek.http_client')
    );
  }

  public function getAccountInfo() {
    try {
      $access_token = $this->lingotekClient->getCurrentToken();
      $account_info = $this->lingotekClient->get('/auth/oauth2/access_token_info?access_token=' . $access_token);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get account info: ' . $e->getMessage());
    }
    return $account_info;
  }

  public function addDocument($args) {
    try {
      $response = $this->lingotekClient->post('/api/document', $args, TRUE);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add document: ' . $e->getMessage());
    }
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
    try {
      $response = $this->lingotekClient->patch('/api/document', $args, TRUE);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to patch (update) document: ' . $e->getMessage());
    }
    if ($response->getStatusCode() == '202') {
      $data = $response->json();
      if (!empty($data['properties']['id'])) {
        return $data['properties']['id'];
      }
    }
    // TODO: log warning
    return FALSE;
  }

  public function deleteDocument($id) {
    try {
      $response = $this->lingotekClient->delete('/api/document' . '/' . $id, array('_method' => 'DELETE'));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to delete document: ' . $e->getMessage());
    }
    return $response;
  }

  public function getDocument($id) {
    try {
      $response = $this->lingotekClient->get('/api/document', array('doc_id' => $id));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get document: ' . $e->getMessage());
    }
    return $response;
  }

  public function documentExists($id) {
    // TODO
    throw new Exception('Not implemented');
  }

  public function getDocumentStatus($id) {
    try {
      $response = $this->lingotekClient->get('/api/document/' . $id . '/status');
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get document status: ' . $e->getMessage());
    }
    return $response;
  }

  public function addTranslation($id, $locale) {
    try {
      $response = $this->lingotekClient->post('/api/document/' . $id . '/translation', array('locale_code' => $locale));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    return $response;
  }

  public function getTranslation($id, $locale) {
    try {
      $response = $this->lingotekClient->get('/api/document/' . $id . '/content', array('locale_code' => $locale));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    return $response;
  }

  public function deleteTranslation($id, $locale) {
    try {
      $response = $this->lingotekClient->delete('/api/document/' . $id . '/translation', array('locale_code' => $locale));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    return $response;
  }

  public function getCommunities() {
    try {
      $response = $this->lingotekClient->get('/api/community?limit=50');
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get communities: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  public function getProjects($community_id) {
    try {
      $response = $this->lingotekClient->get('/api/project', array('community_id' => $community_id, 'limit' => 100));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get projects: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  public function getVaults($community_id) {
    try {
      $response = $this->lingotekClient->get('/api/vault', array('community_id' => $community_id));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get vaults: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  public function getWorkflows($community_id) {
    try {
      $response = $this->lingotekClient->get('/api/workflow', array('community_id' => $community_id));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get workflows: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  protected function formatResponse($response) {
    $formatted_response = array();
    $json_response = $response->json();
    if (!empty($json_response['entities'])) {
      foreach ($json_response['entities'] as $entity) {
        if (!empty($entity['properties']['id']) && !empty($entity['properties']['title'])) {
          $formatted_response[$entity['properties']['id']] = $entity['properties']['title'];
        }
      }
    }
    return $formatted_response;
  }

}
