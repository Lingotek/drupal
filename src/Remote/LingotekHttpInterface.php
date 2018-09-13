<?php

namespace Drupal\lingotek\Remote;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * a simple interface to http functions
 *
 *@since 0.1
 */
interface LingotekHttpInterface {

  public static function create(ContainerInterface $container);

  public function get($path, $args = []);

  public function post($path, $args = []);

  public function delete($path, $args = []);

  public function patch($path, $args = []);

  public function getCurrentToken();

}
