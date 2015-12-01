<?php

/**
 * @file
 * Contains \Drupal\lingotek\Remote\LingotekApi.
 */

namespace Drupal\lingotek\Remote;

use Drupal\lingotek\Exception\LingotekApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * a simple connector to the Lingotek Translation API
 */

class LingotekApi implements LingotekApiInterface {

  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \Drupal\lingotek\Remote\LingotekHttpInterface
   */
  protected $lingotekClient;

  /**
   * Constructs a LingotekApi object.
   *
   * @param \Drupal\lingotek\Remote\LingotekHttpInterface $client
   *  A http client.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(LingotekHttpInterface $client, LoggerInterface $logger) {
    $this->lingotekClient = $client;
    $this->logger = $logger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.http_client'),
      $container->get('logger.channel.lingotek')
    );
  }

  public function getAccountInfo() {
    try {
      $access_token = $this->lingotekClient->getCurrentToken();
      $account_info = $this->lingotekClient->get('/auth/oauth2/access_token_info?access_token=' . $access_token);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get account info: ' . $e->getMessage());
      $this->logger->notice('Account connected to Lingotek.');
    }
    return $account_info;
  }

  public function addDocument($args) {
    try {
      $this->logger->debug('Lingotek::addDocument called with ' . var_export($args, TRUE));
      $response = $this->lingotekClient->post('/api/document', $args, TRUE);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add document: ' . $e->getMessage());
    }
    if ($response->getStatusCode() == '202') {
      $data = json_decode($response->getBody(), TRUE);
      if (!empty($data['properties']['id'])) {
        return $data['properties']['id'];
      }
    }
    // TODO: log warning
    return FALSE;
  }

  public function patchDocument($id, $args) {
    try {
      $this->logger->debug('Lingotek::pathDocument called with id ' . $id . ' and ' . var_export($args, TRUE));
      $response = $this->lingotekClient->patch('/api/document/' . $id, $args, TRUE);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to patch (update) document: ' . $e->getMessage());
    }
    return $response;
  }

  public function deleteDocument($id) {
    try {
      $this->logger->debug('Lingotek::deleteDocument called with id ' . $id);
      $response = $this->lingotekClient->delete('/api/document' . '/' . $id);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to delete document: ' . $e->getMessage());
    }
    return $response;
  }

  public function getDocument($id) {
    try {
      $this->logger->debug('Lingotek::getDocument called with id ' . $id);
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
      $this->logger->debug('Lingotek::getDocumentStatus called with id ' . $id);
      $response = $this->lingotekClient->get('/api/document/' . $id . '/status');
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get document status: ' . $e->getMessage());
    }
    return $response;
  }

  public function addTranslation($id, $locale, $workflow_id = NULL) {
    try {
      $this->logger->debug('Lingotek::addTranslation called with id ' . $id . ' and locale ' . $locale);
      $args = ['locale_code' => $locale];
      if ($workflow_id) {
        $args['workflow_id'] = $workflow_id;
      }
      $response = $this->lingotekClient->post('/api/document/' . $id . '/translation', $args);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    return $response;
  }

  public function getTranslation($id, $locale) {
    try {
      $this->logger->debug('Lingotek::getTranslation called with id ' . $id . ' and locale ' . $locale);
      $response = $this->lingotekClient->get('/api/document/' . $id . '/content', array('locale_code' => $locale));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    return $response;
  }

  public function deleteTranslation($id, $locale) {
    try {
      $this->logger->debug('Lingotek::deleteTranslation called with id ' . $id . ' and locale ' . $locale);
      $response = $this->lingotekClient->delete('/api/document/' . $id . '/translation', array('locale_code' => $locale));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    return $response;
  }

  public function getCommunities() {
    try {
      $this->logger->debug('Lingotek::getCommunities called.');
      $response = $this->lingotekClient->get('/api/community', ['limit' => 100]);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get communities: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  public function getProject($project_id) {
    try {
      $this->logger->debug('Lingotek::getProject called with id ' . $project_id);
      $response = $this->lingotekClient->get('/api/project/' . $project_id); 
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get project: ' . $e->getMessage());
    }
    return $response->json();
  }

  public function getProjects($community_id) {
    try {
      $this->logger->debug('Lingotek::getProjects called with id ' . $community_id);
      $response = $this->lingotekClient->get('/api/project', array('community_id' => $community_id, 'limit' => 100));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get projects: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  public function setProjectCallBackUrl($project_id, $args) {
    try {
      $this->logger->debug('Lingotek::setProjectCallBackUrl called with id ' . $project_id . ' and ' . var_export($args, TRUE));
      $response = $this->lingotekClient->patch('/api/project/' . $project_id, $args);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to patch project: ' . $e->getMessage());
    }
    return $response;
  }


  public function getVaults($community_id) {
    try {
      $this->logger->debug('Lingotek::getVaults called with id ' . $community_id);
      // We ignore $community_id, as it is not needed for getting the TM vaults.
      $response = $this->lingotekClient->get('/api/vault', array('limit' => 100, 'is_owned' => TRUE));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get vaults: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  public function getWorkflows($community_id) {
    try {
      $this->logger->debug('Lingotek::getWorkflows called with id ' . $community_id);
      $response = $this->lingotekClient->get('/api/workflow', array('community_id' => $community_id));
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get workflows: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  protected function formatResponse($response) {
    $formatted_response = array();
    $json_response = json_decode($response->getBody(), TRUE);
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
