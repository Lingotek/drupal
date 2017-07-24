<?php

/**
 * @file
 * Fixture for \Drupal\lingotek\Tests\Update\LingotekContentEntityMetadataUpgrade8010WithNullDocumentIdTest.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set a document id to NULL.
$connection->update('node_field_data')
  ->condition('nid', 3)
  ->fields(['lingotek_document_id' => NULL])
  ->execute();
