<?php

namespace Drupal\lingotek\Remote;

use Drupal\lingotek\Exception\LingotekApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * A simple connector to the Lingotek Translation API.
 */
class LingotekApi implements LingotekApiInterface {

  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \Drupal\lingotek\Remote\LingotekHttpInterface
   */
  protected $lingotekClient;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a LingotekApi object.
   *
   * @param \Drupal\lingotek\Remote\LingotekHttpInterface $client
   *   A http client.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(LingotekHttpInterface $client, LoggerInterface $logger) {
    $this->lingotekClient = $client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.http_client'),
      $container->get('logger.channel.lingotek')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLocales() {
    $this->logger->debug('Starting Locales request: /api/locale with args [limit => 1000]');
    /** @var \Psr\Http\Message\ResponseInterface $response */
    try {
      $response = $this->lingotekClient->get('/api/locale', ['limit' => 1000]);
      if ($response->getStatusCode() == Response::HTTP_OK) {
        $data = json_decode($response->getBody(), TRUE);
        $this->logger->debug('getLocales response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
        return $data;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error requesting locales: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Error requesting locales: ' . $e->getMessage());
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountInfo() {
    try {
      $access_token = $this->lingotekClient->getCurrentToken();
      $trimmed_token = $access_token ? substr($access_token, 0, 8) . '…' : '';
      $this->logger->debug('Starting account info request: /auth/oauth2/access_token_info Token: %token', ['%token' => $trimmed_token]);
      $response = $this->lingotekClient->get('/auth/oauth2/access_token_info');
    }
    catch (\Exception $e) {
      $this->logger->error('Error requesting account info: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get account info: ' . $e->getMessage());
    }
    $this->logger->debug('getAccountInfo response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function addDocument($args) {
    try {
      $this->logger->debug('Lingotek::addDocument (POST /api/document) called with ' . var_export($args, TRUE));
      $response = $this->lingotekClient->post('/api/document', $args, TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Error adding document: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Error adding document: ' . $e->getMessage());
    }
    if ($response->getStatusCode() == Response::HTTP_ACCEPTED) {
      $data = json_decode($response->getBody(), TRUE);
      $this->logger->debug('addDocument response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
      if (!empty($data['properties']['id'])) {
        return $response;
      }
    }
    // TODO: log warning
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function patchDocument($id, $args) {
    try {
      $this->logger->debug('Lingotek::patchDocument (PATCH /api/document) called with id %id and args %args', ['%id' => $id, '%args' => var_export($args, TRUE)]);
      $response = $this->lingotekClient->patch('/api/document/' . $id, $args, TRUE);
    }
    catch (\Exception $e) {
      $this->logger->error('Error updating document: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to patch (update) document: ' . $e->getMessage());
    }
    $this->logger->debug('patchDocument response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocument($id) {
    try {
      $this->logger->debug('Lingotek::cancelDocument called with id ' . $id);
      $args = [
        'id' => $id,
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
      ];
      $response = $this->lingotekClient->post('/api/document/' . $id . '/cancel', $args);
    }
    catch (\Exception $e) {
      $http_status_code = $e->getCode();
      if ($http_status_code === Response::HTTP_NOT_FOUND) {
        $this->logger->error('Error cancelling document: %message.', ['%message' => $e->getMessage()]);
        return new Response($e->getMessage(), Response::HTTP_NOT_FOUND);
      }
      $this->logger->error('Error cancelling document: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to cancel document: ' . $e->getMessage(), $http_status_code, $e);
    }
    $this->logger->debug('cancelDocument response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocumentTarget($document_id, $locale) {
    try {
      $this->logger->debug('Lingotek::cancelDocumentTarget called with id ' . $document_id . ' and locale ' . $locale);
      $args = [
        'id' => $document_id,
        'locale' => $locale,
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
        'mark_invoiceable' => 'true',
      ];
      $response = $this->lingotekClient->post('/api/document/' . $document_id . '/translation/' . $locale . '/cancel', $args);
    }
    catch (\Exception $e) {
      $http_status_code = $e->getCode();
      if ($http_status_code === Response::HTTP_NOT_FOUND) {
        $this->logger->error('Error cancelling document target: %message.', ['%message' => $e->getMessage()]);
        return new Response($e->getMessage(), Response::HTTP_NOT_FOUND);
      }
      $this->logger->error('Error cancelling document target: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to cancel document target: ' . $e->getMessage(), $http_status_code, $e);
    }
    $this->logger->debug('cancelDocumentTarget response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentContent($doc_id) {
    try {
      $this->logger->debug('Lingotek::getDocumentContent called with id ' . $doc_id);
      $response = $this->lingotekClient->get('/api/document/' . $doc_id . '/content');
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get document: ' . $e->getMessage());
    }
    return $response->getBody()->getContents();
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentInfo($id) {
    try {
      $this->logger->debug('Lingotek::getDocumentInfo called with id ' . $id);
      $response = $this->lingotekClient->get('/api/document/' . $id);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting document info: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get document: ' . $e->getMessage());
    }
    $this->logger->debug('getDocumentInfo response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentStatus($id) {
    try {
      $this->logger->debug('Lingotek::getDocumentStatus called with id ' . $id);
      $response = $this->lingotekClient->get('/api/document/' . $id . '/status');
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting document status: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get document status: ' . $e->getMessage());
    }
    $this->logger->debug('getDocumentStatus response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentTranslationStatuses($id) {
    try {
      $this->logger->debug('Lingotek::getDocumentTranslationStatuses called with %id', ['%id' => $id]);
      $response = $this->lingotekClient->get('/api/document/' . $id . '/translation');
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting document translation status (%id): %message.',
        ['%id' => $id, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get document translation status: ' . $e->getMessage());
    }
    $this->logger->debug('getDocumentTranslationStatuses response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentTranslationStatus($id, $locale) {
    try {
      $this->logger->debug('Lingotek::getDocumentTranslationStatus called with %id and %locale', ['%id' => $id, '%locale' => $locale]);
      $response = $this->lingotekClient->get('/api/document/' . $id . '/translation');
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting document translation status (%id, %locale): %message.',
        ['%id' => $id, '%locale' => $locale, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get document translation status: ' . $e->getMessage());
    }
    $this->logger->debug('getDocumentTranslationStatus response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  public function addTranslation($id, $locale, $workflow_id = NULL, $vault_id = NULL) {
    try {
      $this->logger->debug('Lingotek::addTranslation called with id ' . $id . ' and locale ' . $locale);
      $args = ['locale_code' => $locale];
      if ($workflow_id) {
        $args['workflow_id'] = $workflow_id;
      }
      if ($vault_id) {
        $args['vault_id'] = $vault_id;
      }
      $response = $this->lingotekClient->post('/api/document/' . $id . '/translation', $args);
    }
    catch (\Exception $e) {
      // If the problem is that the translation already exist, don't fail.
      if ($e->getCode() === Response::HTTP_BAD_REQUEST) {
        $responseBody = json_decode($e->getResponse()->getBody(), TRUE);
        if ($responseBody['messages'][0] === 'Translation (' . $locale . ') already exists.') {
          $this->logger->info('Added an existing target for %id with %args.',
            ['%id' => $id, '%args' => var_export($args, TRUE)]);
        }
        return new Response('Was already requested. All is good.', Response::HTTP_CREATED);
      }
      $this->logger->error('Error requesting translation (%id, %args): %message.',
        ['%id' => $id, '%args' => var_export($args, TRUE), '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    $this->logger->debug('addTranslation response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslation($id, $locale, $useSource = FALSE) {
    try {
      $this->logger->debug('Lingotek::getTranslation called with id ' . $id . ' and locale ' . $locale);
      $response = $this->lingotekClient->get('/api/document/' . $id . '/content', ['locale_code' => $locale, 'use_source' => $useSource ? 'true' : 'false']);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting translation (%id, %locale): %message.',
        ['%id' => $id, '%locale' => $locale, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    $this->logger->debug('getTranslation response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTranslation($id, $locale) {
    try {
      $this->logger->debug('Lingotek::deleteTranslation called with id ' . $id . ' and locale ' . $locale);
      $response = $this->lingotekClient->delete('/api/document/' . $id . '/translation', ['locale_code' => $locale]);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting translation (%id, %locale): %message.',
        ['%id' => $id, '%locale' => $locale, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to add translation: ' . $e->getMessage());
    }
    $this->logger->debug('deleteTranslation response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunities() {
    try {
      $this->logger->debug('Lingotek::getCommunities called.');
      $response = $this->lingotekClient->get('/api/community', ['limit' => 100]);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting communities: %message.', ['%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get communities: ' . $e->getMessage());
    }
    $this->logger->debug('deleteTranslation response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $this->formatResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function getProject($project_id) {
    try {
      $this->logger->debug('Lingotek::getProject called with id ' . $project_id);
      $response = $this->lingotekClient->get('/api/project/' . $project_id);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting project %project: %message.', ['%project' => $project_id, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get project: ' . $e->getMessage());
    }
    $this->logger->debug('getProject response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response->json();
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects($community_id) {
    try {
      $this->logger->debug('Lingotek::getProjects called with id ' . $community_id);
      $response = $this->lingotekClient->get('/api/project', ['community_id' => $community_id, 'limit' => 1000]);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting projects for community %community: %message.', ['%community' => $community_id, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get projects: ' . $e->getMessage());
    }
    $this->logger->debug('getProjects response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $this->formatResponse($response);
  }

  public function setProjectCallBackUrl($project_id, $args) {
    try {
      $this->logger->debug('Lingotek::setProjectCallBackUrl called with id ' . $project_id . ' and ' . var_export($args, TRUE));
      $response = $this->lingotekClient->patch('/api/project/' . $project_id, $args);
    }
    catch (\Exception $e) {
      $this->logger->error('Error patching project %project_id with %args: %message.', [
      '%project_id' => $project_id,
        '%args' => var_export($args, TRUE),
      '%message' => $e->getMessage(),
]);
      throw new LingotekApiException('Failed to patch project: ' . $e->getMessage());
    }
    $this->logger->debug('setProjectCallBackUrl response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getVaults($community_id) {
    try {
      $this->logger->debug('Lingotek::getVaults called with id ' . $community_id);
      // We ignore $community_id, as it is not needed for getting the TM vaults.
      $response = $this->lingotekClient->get('/api/vault', ['limit' => 100, 'is_owned' => 'TRUE']);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting vaults for community %community: %message.', ['%community' => $community_id, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get vaults: ' . $e->getMessage());
    }
    $this->logger->debug('getVaults response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $this->formatResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflows($community_id) {
    try {
      $this->logger->debug('Lingotek::getWorkflows called with id ' . $community_id);
      $response = $this->lingotekClient->get('/api/workflow', ['community_id' => $community_id, 'limit' => 1000]);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting workflows for community %community: %message.', ['%community' => $community_id, '%message' => $e->getMessage()]);
      throw new LingotekApiException('Failed to get workflows: ' . $e->getMessage());
    }
    $this->logger->debug('getWorkflows response received, code %code and body %body', ['%code' => $response->getStatusCode(), '%body' => (string) $response->getBody(TRUE)]);
    return $this->formatResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    try {
      $this->logger->debug('Lingotek::getFilters called.');
      $response = $this->lingotekClient->get('/api/filter', ['limit' => 1000]);
    }
    catch (\Exception $e) {
      throw new LingotekApiException('Failed to get filters: ' . $e->getMessage());
    }
    return $this->formatResponse($response);
  }

  /**
   * Formats the response data as id => title based on the JSON returned
   * properties.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   A response.
   *
   * @return array
   *   Array of titles keyed by id from the response entities.
   */
  protected function formatResponse($response) {
    $formatted_response = [];
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
