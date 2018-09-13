<?php

/**
 * @file
 * Fixture for \Drupal\lingotek\Tests\Update\LingotekContentEntityMetadataUpgrade8010WithNullSourceLanguageTest.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set all the language source to NULL.
$connection->update('node_field_data')
  ->fields(['lingotek_translation_source' => NULL])
  ->execute();
