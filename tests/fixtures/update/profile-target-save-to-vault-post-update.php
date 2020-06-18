<?php

/**
 * @file
 * Fixture for \Drupal\Tests\lingotek\Functional\Update\LingotekProfileTargetSaveToVaultPostUpdateTest.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

foreach (['es', 'en', 'fr', 'it'] as $langcode) {
  $connection->insert('config')
    ->fields([
      'collection' => '',
      'name' => "language.entity.$langcode.yml",
      'data' => serialize(Yaml::decode(file_get_contents(
        __DIR__ . "/profile-target-save-to-vault-post-update/language.entity.$langcode.yml"
      ))),
    ])
    ->execute();
}

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'lingotek.profile.custom_profile.yml',
    'data' => serialize(Yaml::decode(file_get_contents(
      __DIR__ . "/profile-target-save-to-vault-post-update/lingotek.profile.custom_profile.yml"
    ))),
  ])
  ->execute();
