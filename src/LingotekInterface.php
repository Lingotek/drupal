<?php

namespace Drupal\lingotek;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface LingotekInterface {
  public function get($key);
  public function set($key, $value);
  public static function create(ContainerInterface $container);

  public function getAccountInfo();
  public function getResources($force = FALSE);
  public function getWorkflows($force = FALSE);
  public function getVaults($force = FALSE);
  public function getCommunities($force = FALSE);
  public function getProjects($force = FALSE);
  public function getDefaults();
  public function getProject($project_id);
  public function setProjectCallBackUrl($project_id, $callback_url);

}
