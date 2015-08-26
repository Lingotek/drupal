<?php

/**
 * @file
 * Contains \Drupal\lingotek\Lingotek.
 */

namespace Drupal\lingotek;

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/*
 * The connecting class between Drupal and Lingotek
 */

class Lingotek implements LingotekInterface {

  use UrlGeneratorTrait;

  protected static $instance;
  protected $api;
  protected $config;

  // Translation Status.
  const STATUS_EDITED = 'EDITED';
  const STATUS_IMPORTING = 'IMPORTING';
  const STATUS_NONE = 'NONE';
  const STATUS_REQUEST = 'REQUEST';
  const STATUS_PENDING = 'PENDING';
  const STATUS_CURRENT = 'CURRENT';
  const STATUS_READY = 'READY';
  const STATUS_FAILED = 'FAILED';
  /**
   * Status untracked means the target has not been added yet.
   */
  const STATUS_UNTRACKED = 'UNTRACKED';
  const PROGRESS_COMPLETE = 100;
  // Translation Profile.
  const PROFILE_AUTOMATIC = 'automatic';
  const PROFILE_MANUAL = 'manual';
  const PROFILE_DISABLED = 'disabled';

  public function __construct(LingotekApiInterface $api, ConfigFactoryInterface $config) {
    $this->api = $api;
    $this->config = $config->getEditable('lingotek.settings');
  }

  public static function create(ContainerInterface $container) {
    if (empty(self::$instance)) {
      self::$instance = new Lingotek($container->get('lingotek.api'), $container->get('config.factory'));
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
    return array(
        'community' => $this->getCommunities($force),
        'project' => $this->getProjects($force),
        'vault' => $this->getVaults($force),
        'workflow' => $this->getWorkflows($force)
    );
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

  public function setProjectCallBackUrl($project_id, $callback_url) {
    $args = array(
      'format' => 'JSON',
      'callback_url' => $callback_url,
    );

    $response = $this->api->setProjectCallBackUrl($project_id, $args);

    if ($response->getStatusCode() == '204') {
      return TRUE;
    }
    //TODO: Log item
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

  public function uploadDocument($title, $content, $locale = NULL) {
    // Handle adding site defaults to the upload here, and leave
    // the handling of the upload call itself to the API.
    $defaults = array(
      'format' => 'JSON',
      'project_id' => $this->get('default.project'),
      'workflow_id' => $this->get('default.workflow'),
    );
    
    $args = array_merge(array('content' => $content, 'title' => $title, 'locale_code' => $locale), $defaults);
    $response = $this->api->addDocument($args);

    // TODO: Response code should be 202 on success
    return $response;
  }

  public function updateDocument($doc_id, $content) {
    $args = array(
      'format' => 'JSON',
      'content' => $content,
    );
    $response = $this->api->patchDocument($doc_id, $args);
    if ($response->getStatusCode() == '202') {
      return TRUE;
    }
    return FALSE;
  }

  public function deleteDocument($doc_id) {
    $response = $this->api->deleteDocument($doc_id);

    if ($response->getStatusCode() == '204') {
      return TRUE;
    }
    return FALSE;
  }

  public function documentImported($doc_id) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $response = $this->api->getDocument($doc_id);
    if ($response->getStatusCode() == '200') {
      return TRUE;
    }
    return FALSE;
  }

  public function addTarget($doc_id, $locale) {
    $response = $this->api->addTranslation($doc_id, $locale);
    if ($response->getStatusCode() == '201') {
      return TRUE;
    }
    return FALSE;
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
    $key = $this->get($default_key);
    if (empty($key) || !in_array($default_value, $valid_resource_ids)) {
      $value = current($valid_resource_ids);
      $this->set($default_key, $value);
    }
  }

  public function getDocumentStatus($doc_id) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $response = $this->api->getDocumentStatus($doc_id);
    if ($response->getStatusCode() == '200') {
      $progress_json = json_decode($response->getBody(), TRUE);
      $progress = !empty($progress_json['properties']['progress']) ? $progress_json['properties']['progress'] : NULL;
      if ($progress === self::PROGRESS_COMPLETE) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function downloadDocument($doc_id, $locale) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $response = $this->api->getTranslation($doc_id, $locale);
    if ($response->getStatusCode() == '200') {
      return json_decode($response->getBody(), TRUE);
    }
    return FALSE;
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
  public function redirect($route_name, array $route_parameters = array(), $status = 302) {
    $url = $this->url($route_name, $route_parameters, ['absolute' => TRUE]);
    return new RedirectResponse($url, $status);
  }

  public static function d($data, $label = NULL, $die = FALSE) {
    echo '<pre style="background: #f3f3f3; color: #000">';
    if (is_string($label)) {
      echo '<h1>' . $label . '</h1>';
    }
    echo '</pre>';
    if ($die || is_bool($label) && $label) {
      die();
    }
  }

}
