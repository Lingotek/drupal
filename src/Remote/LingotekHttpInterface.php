<?php

namespace Drupal\lingotek\Remote;

use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * a simple interface to http functions
 *
 * @since 0.1
 */
interface LingotekHttpInterface {

  public static function create(ContainerInterface $container);
  public function get($path, $args = array());
  public function post($path, $args = array());
  public function delete($path, $args = array());
  public function patch($path, $args = array());
  public function getCurrentToken();

}
