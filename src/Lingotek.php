<?php

namespace Drupal\lingotek;

use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The connecting class between Drupal and Lingotek
 */
class Lingotek implements LingotekInterface {

  use UrlGeneratorTrait;

  protected static $instance;
  protected $api;
  protected $config;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The Lingotek Filter manager.
   *
   * @var \Drupal\lingotek\LingotekFilterManagerInterface
   */
  protected $lingotekFilterManager;

  // Translation Status.
  const STATUS_EDITED = 'EDITED';
  const STATUS_IMPORTING = 'IMPORTING';
  const STATUS_NONE = 'NONE';
  const STATUS_REQUEST = 'REQUEST';
  const STATUS_PENDING = 'PENDING';
  const STATUS_INTERMEDIATE = 'INTERMEDIATE';
  const STATUS_CURRENT = 'CURRENT';
  const STATUS_READY = 'READY';
  const STATUS_DISABLED = 'DISABLED';
  const STATUS_ERROR = 'ERROR';

  /**
   * Status untracked means the target has not been added yet.
   */
  const STATUS_UNTRACKED = 'UNTRACKED';
  const PROGRESS_COMPLETE = 100;
  // Translation Profile.
  const PROFILE_AUTOMATIC = 'automatic';
  const PROFILE_MANUAL = 'manual';
  const PROFILE_DISABLED = 'disabled';

  /**
   * Constructs a Lingotek object.
   *
   * @param \Drupal\lingotek\Remote\LingotekApiInterface $api
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param LingotekFilterManagerInterface $lingotek_filter_manager
   *   The Lingotek Filter manager.
   */
  public function __construct(LingotekApiInterface $api, LanguageLocaleMapperInterface $language_locale_mapper, ConfigFactoryInterface $config, LingotekFilterManagerInterface $lingotek_filter_manager) {
    $this->api = $api;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->config = $config->getEditable('lingotek.settings');
    $this->lingotekFilterManager = $lingotek_filter_manager;
  }

  public static function create(ContainerInterface $container) {
    if (empty(self::$instance)) {
      self::$instance = new Lingotek($container->get('lingotek.api'), $container->get('lingotek.language_locale_mapper'), $container->get('config.factory'), $container->get('lingotek.filter_manager'));
    }
    return self::$instance;
  }

  public function getAccountInfo() {
    try {
      $response = $this->api->getAccountInfo();
    }
    catch (LingotekApiException $e) {
      // TODO: log a warning
      return FALSE;
    }
    if ($response) {
      return json_decode($response->getBody(), TRUE);
    }
    // TODO: log a warning
    return FALSE;
  }

  public function getResources($force = FALSE) {
    return [
        'community' => $this->getCommunities($force),
        'project' => $this->getProjects($force),
        'vault' => $this->getVaults($force),
        'workflow' => $this->getWorkflows($force),
        'filter' => $this->getFilters($force),
    ];
  }

  public function getDefaults() {
    return $this->get('default');
  }

  public function getCommunities($force = FALSE) {
    $resources_key = 'account.resources.community';
    $data = $this->get($resources_key);
    if (empty($data) || $force) {
      $data = $this->api->getCommunities($force);
      $this->set($resources_key, $data);
    }
    return $data;
  }

  public function getVaults($force = FALSE) {
    return $this->getResource('account.resources.vault', 'getVaults', $force);
  }

  public function getProjects($force = FALSE) {
    return $this->getResource('account.resources.project', 'getProjects', $force);
  }

  public function getWorkflows($force = FALSE) {
    return $this->getResource('account.resources.workflow', 'getWorkflows', $force);
  }

  public function getProjectStatus($project_id) {
    return $this->api->getProjectStatus($project_id);
  }

  public function getProject($project_id) {
    return $this->api->getProject($project_id);
  }

  /**
  * {@inheritdoc}
  */
  public function getFilters($force = FALSE) {
    return $this->getResource('account.resources.filter', 'getFilters', $force);
  }

  public function setProjectCallBackUrl($project_id, $callback_url) {
    $args = [
      'format' => 'JSON',
      'callback_url' => $callback_url,
    ];

    $response = $this->api->setProjectCallBackUrl($project_id, $args);

    if ($response->getStatusCode() == Response::HTTP_NO_CONTENT) {
      return TRUE;
    }
    // TODO: Log item
    return FALSE;
  }

  /**
   * @deprecated
   */
  public function get($key) {
    return $this->config->get($key);
  }

  /**
   * @deprecated
   */
  public function set($key, $value) {
    $this->config->set($key, $value)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument($title, $content, $locale, $url = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL) {
    if (!is_array($content)) {
      $data = json_decode($content, TRUE);
      // This is the quickest way if $content is not a valid json object.
      $content = ($data === NULL) ? $content : $data;
    }
    // Handle adding site defaults to the upload here, and leave
    // the handling of the upload call itself to the API.
    $defaults = [
      'format' => 'JSON',
      'project_id' => $this->get('default.project'),
      'fprm_id' => $this->lingotekFilterManager->getFilterId($profile),
      'fprm_subfilter_id' => $this->lingotekFilterManager->getSubfilterId($profile),
      'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
    ];
    // Remove filters set to NULL
    $defaults = array_filter($defaults);

    $metadata = $this->getIntelligenceMetadata($content);

    if ($profile !== NULL && $project = $profile->getProject()) {
      if ($project !== 'default') {
        $defaults['project_id'] = $project;
      }
    }

    if ($profile !== NULL && $vault = $profile->getVault()) {
      if ($vault === 'default') {
        $vault = $this->get('default.vault');
      }
      $defaults['vault_id'] = $vault;
      // If we use the project workflow template default vault, we omit the
      // vault parameter and the TMS will decide.
      if ($vault === 'project_workflow_vault') {
        unset($defaults['vault_id']);
      }
    }

    $args = array_merge($metadata, $defaults);

    $args = array_merge(['content' => json_encode($content), 'title' => $title, 'locale_code' => $locale], $args);
    if ($url !== NULL) {
      $args['external_url'] = $url;
    }
    if ($job_id !== NULL) {
      $args['job_id'] = $job_id;
    }
    $response = $this->api->addDocument($args);

    // TODO: Response code should be 202 on success
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument($doc_id, $content, $url = NULL, $title = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL) {
    if (!is_array($content)) {
      $data = json_decode($content, TRUE);
      // This is the quickest way if $content is not a valid json object.
      $content = ($data === NULL) ? $content : $data;
    }

    $defaults = [
      'format' => 'JSON',
      'fprm_id' => $this->lingotekFilterManager->getFilterId($profile),
      'fprm_subfilter_id' => $this->lingotekFilterManager->getSubfilterId($profile),
      'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
    ];

    $metadata = $this->getIntelligenceMetadata($content);
    $args = array_merge($metadata, $defaults);

    if ($url !== NULL) {
      $args['external_url'] = $url;
    }
    if ($title !== NULL) {
      $args['title'] = $title;
    }
    if ($job_id !== NULL) {
      $args['job_id'] = $job_id;
    }
    if ($content !== NULL) {
      $args = array_merge(['content' => json_encode($content)], $args);
    }
    else {
      // IF there's no content, let's remove filters, we may want to update only
      // the Job ID.
      unset($args['fprm_id']);
      unset($args['fprm_subfilter_id']);
    }

    $response = $this->api->patchDocument($doc_id, $args);
    if ($response->getStatusCode() == Response::HTTP_ACCEPTED) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Pulls the Intelligence Metadata from the Lingotek Metadata and returns it.
   *
   * @param array $data
   *   The structure of a document content.
   *
   * @return array
   */
  public function getIntelligenceMetadata(&$data) {
    $metadata = [];
    if (is_array($data) && isset($data['_lingotek_metadata']['_intelligence'])) {
      $metadata = $data['_lingotek_metadata']['_intelligence'];
      unset($data['_lingotek_metadata']['_intelligence']);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDocument($doc_id) {
    $response = $this->api->deleteDocument($doc_id);
    $status_code = $response->getStatusCode();
    if ($status_code == Response::HTTP_NO_CONTENT || $status_code == Response::HTTP_ACCEPTED) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * @param $doc_id
   * @return bool
   *
   * @deprecated in 8.x-1.4. Use ::getDocumentStatus() instead.
   */
  public function documentImported($doc_id) {
    return $this->getDocumentStatus($doc_id);
  }

  public function addTarget($doc_id, $locale, LingotekProfileInterface $profile = NULL) {
    $workflow_id = NULL;
    $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);

    if ($profile !== NULL && $workflow_id = $profile->getWorkflowForTarget($drupal_language->getId())) {
      if ($workflow_id === 'default') {
        $workflow_id = $this->get('default.workflow');
      }
    }

    $response = $this->api->addTranslation($doc_id, $locale, $workflow_id);
    if ($response->getStatusCode() == Response::HTTP_CREATED) {
      return TRUE;
    }
    return FALSE;
  }

  public function getLocales() {
    $data = $this->api->getLocales();
    $locales = [];
    if ($data) {
      foreach ($data['entities'] as $locale) {
        $locales[] = $locale['properties']['code'];
      }
    }
    return $locales;
  }

  protected function getResource($resources_key, $func, $force = FALSE) {
    $data = $this->get($resources_key);
    if (empty($data) || $force) {
      $community_id = $this->get('default.community');
      $data = $this->api->$func($community_id);
      $this->set($resources_key, $data);
      $keys = explode(".", $resources_key);
      $default_key = 'default.' . end($keys);
      $this->setValidDefaultIfNotSet($default_key, $data);
    }
    return $data;
  }

  protected function setValidDefaultIfNotSet($default_key, $resources) {
    $default_value = $this->get($default_key);
    $valid_resource_ids = array_keys($resources);
    if ($default_key === 'default.filter') {
      $valid_resource_ids[] = 'drupal_default';
    }
    if (empty($default_value) || !in_array($default_value, $valid_resource_ids)) {
      $value = current($valid_resource_ids);
      $this->set($default_key, $value);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getUploadedTimestamp($doc_id) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $modified_date = FALSE;
    try {
      $response = $this->api->getDocumentInfo($doc_id);
      if ($response->getStatusCode() == Response::HTTP_OK) {
        $response_json = json_decode($response->getBody(), TRUE);
        // We have millisecond precision in Lingotek.
        $modified_date = intval(floor($response_json['properties']['last_uploaded_date'] / 1000));
      }
    }
    catch (LingotekApiException $exception) {
      return FALSE;
    }
    return $modified_date;
  }

  public function getDocumentStatus($doc_id) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    try {
      $response = $this->api->getDocumentStatus($doc_id);
      if ($response->getStatusCode() == Response::HTTP_OK) {
        // If an exception didn't happen, the document is succesfully imported.
        // The status value there is related with translation status, so we must
        // ignore it.
        return TRUE;
      }
    }
    catch (LingotekApiException $exception) {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentTranslationStatus($doc_id, $locale) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $response = $this->api->getDocumentTranslationStatus($doc_id, $locale);
    $progress = FALSE;
    if ($response->getStatusCode() == Response::HTTP_OK) {
      $progress_json = json_decode($response->getBody(), TRUE);
      $lingotek_locale = str_replace("_", "-", $locale);
      if (!empty($progress_json['entities'])) {

        foreach ($progress_json['entities'] as $index => $data) {
          if ($data['properties']['locale_code'] === $lingotek_locale) {
            $progress = $data['properties']['percent_complete'];
            break;
          }
        }
      }
      if ($progress === self::PROGRESS_COMPLETE) {
        return TRUE;
      }
    }
    return $progress;
  }

  public function getDocumentTranslationStatuses($doc_id) {
    $statuses = [];
    try {
      $response = $this->api->getDocumentTranslationStatuses($doc_id);
    }
    catch (LingotekApiException $e) {
      // No targets found for this doc
      return $statuses;
    }
    if ($response->getStatusCode() == Response::HTTP_OK) {
      $progress_json = json_decode($response->getBody(), TRUE);
      if (!empty($progress_json['entities'])) {
        foreach ($progress_json['entities'] as $index => $data) {
          $lingotek_locale = $data['properties']['locale_code'];
          $statuses[$lingotek_locale] = $data['properties']['percent_complete'];
        }
      }
    }
    return $statuses;
  }

  public function downloadDocument($doc_id, $locale) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $response = $this->api->getTranslation($doc_id, $locale, $this->config->get('preference.enable_download_source'));
    if ($response->getStatusCode() == Response::HTTP_OK) {
      return json_decode($response->getBody(), TRUE);
    }
    return FALSE;
  }

  public function downloadDocumentContent($doc_id) {
    $response = $this->api->getDocumentContent($doc_id);
    return $response;
  }

  public function downloadDocuments($args = []) {
    $response = $this->api->getDocuments($args);
    return $response;
  }

  /**
   * Returns a redirect response object for the specified route.
   *
   * @param string $route_name
   *   The name of the route to which to redirect.
   * @param array $route_parameters
   *   Parameters for the route.
   * @param int $status
   *   The HTTP redirect status code for the redirect. The default is 302 Found.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by any controller.
   */
  public function redirect($route_name, array $route_parameters = [], $status = 302) {
    $url = $this->url($route_name, $route_parameters, ['absolute' => TRUE]);
    return new RedirectResponse($url, $status);
  }

}
