<?php

/**
 * @file
 * Fixture for \Drupal\Tests\lingotek\Functional\Update\LingotekProfileAutoRequestTranslationsPostUpdateTest.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

foreach (['ca', 'es', 'de', 'it'] as $langcode) {
  $connection->insert('config')
    ->fields([
      'collection' => '',
      'name' => "language.entity.$langcode.yml",
      'data' => serialize(Yaml::decode(file_get_contents(
        __DIR__ . '/profile-autorequest-post-update' . '/' . "language.entity.$langcode.yml"))),
    ])
    ->execute();
}

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'lingotek.profile.auto_download.yml',
    'data' => serialize(Yaml::decode(file_get_contents(
      __DIR__ . '/profile-autorequest-post-update' . '/lingotek.profile.auto_download.yml'))),
  ])
  ->execute();
$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'lingotek.profile.auto_upload.yml',
    'data' => serialize(Yaml::decode(file_get_contents(
      __DIR__ . '/profile-autorequest-post-update' . '/lingotek.profile.auto_upload.yml'))),
  ])
  ->execute();
