<?php

/**
 * @file
 * Fixture for \Drupal\lingotek\Tests\Update\LingotekConfigDependenciesPostUpdateTest.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'lingotek.lingotek_config_metadata.node_type.article',
    'data' => serialize(Yaml::decode(file_get_contents(
      __DIR__ . '/config-dependencies-post-update' . '/lingotek.lingotek_config_metadata.node_type.article.yml'))),
  ])
  ->execute();
$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'lingotek.lingotek_config_metadata.field_config.node.article.body',
    'data' => serialize(Yaml::decode(file_get_contents(
      __DIR__ . '/config-dependencies-post-update' . '/lingotek.lingotek_config_metadata.field_config.node.article.body.yml'))),
  ])
  ->execute();


// Enable lingotek_test theme.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions);
$connection->update('config')
  ->fields([
    'data' => serialize(array_merge_recursive($extensions, [
      'module' => [
        'lingotek_test' => 0,
      ],
    ])),
  ])
  ->condition('name', 'core.extension')
  ->execute();
