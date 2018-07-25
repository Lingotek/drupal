<?php

/**
 * @file
 * Fixture for \Drupal\Tests\lingotek\Functional\Update\LingotekTargetStatusFormatterUpdate8209Test.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$config = Yaml::decode(file_get_contents(__DIR__ . '/views.view.lingotektargetstatusupgrade2985742.yml'));
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'views.view.' . $config['id'],
    'data' => serialize($config),
  ])
  ->execute();
