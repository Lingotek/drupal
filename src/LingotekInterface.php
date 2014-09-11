<?php

namespace Drupal\lingotek;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface LingotekInterface {
  public function get($key);
  public function set($key, $value);
  public static function create(ContainerInterface $container);
}
