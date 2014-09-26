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
  const STATUS_PENDING = 'PENDING';
  const STATUS_CURRENT = 'CURRENT';
  const STATUS_READY = 'READY';
  const STATUS_FAILED = 'FAILED';
  const STATUS_UNTRACKED = 'UNTRACKED';
  
  // Translation Profile.
  const PROFILE_DISABLED = 'DISABLED';
  const PROFILE_AUTOMATIC = 'AUTO';
  const PROFILE_MANUAL = 'MANUAL';

  public function __construct(LingotekApiInterface $api, ConfigFactoryInterface $config) {
    $this->api = $api;
    $this->config = $config->get('lingotek.settings');
  }

  public static function create(ContainerInterface $container) {
    if (empty(self::$instance)) {
      self::$instance = new Lingotek($container->get('lingotek.api'), $container->get('config.factory'));
    }
    return self::$instance;
  }

  public function getAccountInfo() {
    return $this->api->getAccountInfo();
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

  public function get($key) {
    return $this->config->get($key);
  }

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
    $response = $this->api->uploadDocument($args);

    // TODO: Response code should be 202 on success
    return $response;
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

  protected function getResource($resources_key, $func, $force = FALSE) {
    $data = $this->get($resources_key);
    if (empty($data) || $force) {
      $community_id = $this->get('default.community');
      $data = $this->api->$func($community_id);
      $this->set($resources_key, $data);
      $default_key = 'default.' . end(explode(".", $resources_key));
      $this->setValidDefaultIfNotSet($default_key, $data);
    }
    return $data;
  }

  protected function setValidDefaultIfNotSet($default_key, $resources) {
    $default_value = $this->get($default_key);
    $valid_resource_ids = array_keys($resources);
    if (empty($this->get($default_key)) || !in_array($default_value, $valid_resource_ids)) {
      $value = current($valid_resource_ids);
      $this->set($default_key, $value);
    }
  }

  public function getTargetStatus($doc_id, $locale) {

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


}
