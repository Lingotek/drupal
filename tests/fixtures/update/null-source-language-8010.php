<?php

$connection = Drupal\Core\Database\Database::getConnection();

// Set all the language source to NULL.
$connection->update('node_field_data')
  ->fields(['lingotek_translation_source' => NULL])
  ->execute();
