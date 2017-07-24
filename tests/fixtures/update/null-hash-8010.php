<?php

/**
 * @file
 * Fixture for \Drupal\lingotek\Tests\Update\LingotekContentEntityMetadataUpgrade8010WithNullHashTest.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set a profile to NULL.
$connection->update('node_field_data')
  ->condition('nid', 3)
  ->fields(['lingotek_hash' => NULL])
  ->execute();
