<?php

/**
 * @file
 * Contains \Drupal\lingotek\Lingotek.
 */

namespace Drupal\lingotek;

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * The connecting class between Drupal and Lingotek
 */

class Lingotek implements LingotekInterface {

  protected static $instance;
  protected $api;
  protected $config;

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
    $data = $this->get('account.resources.community');
    if (empty($data)) {
      $data = $this->api->getCommunities();
      $this->set('account.resources.community', $data);
    }
    return $data;
  }

  public function getVaults($force = FALSE) {
    return $this->getSetting('account.resources.vault', 'getVaults', $force);
  }

  public function getProjects() {
    return $this->getSetting('account.resources.projects', 'getProjects', $force);
  }

  public function getWorkflows() {
    return $this->getSetting('account.resources.workflow', 'getWorkflows', $force);
  }

  public function get($key) {
    return $this->config->get($key);
  }

  public function set($key, $value) {
    $this->config->set($key, $value)->save();
  }

  // TODO: NEEDS RENAME?
  protected function getSetting($key, $func, $force = FALSE) {
    $data = $this->get($key);
    if (empty($data) || $force) {
      $community_id = $this->get('default.community');
      $data = $this->api->$func($community_id);
      $this->setDefaultIfNotSet($key, $data);
      $this->set($key, $data);
    }
    return $data;
  }

  protected function setDefaultIfNotSet($key, $values) {
    $dkey = 'default.' . $key;
    if (empty($this->get($dkey))) {
      if (is_array($values)) {
        $value = current(array_keys($values));
        $this->set($dkey, $value);
      }
    }
  }

}
