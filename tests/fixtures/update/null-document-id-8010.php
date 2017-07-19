<?php

/**
 * @file
 * Sets a document id to NULL in the database.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set a document id to NULL.
$connection->update('node_field_data')
  ->condition('nid', 3)
  ->fields(['lingotek_document_id' => NULL])
  ->execute();
