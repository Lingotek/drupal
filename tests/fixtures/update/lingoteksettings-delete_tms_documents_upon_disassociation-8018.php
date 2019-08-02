<?php

/**
 * @file
 * Fixture for \Drupal\Tests\lingotek\Functional\Update\LingotekConfigSettingsDeleteOnDisassociateRemoval8218Test.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Enable the delete_tms_documents_upon_disassociation preference.
$settings = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'lingotek.settings')
  ->execute()
  ->fetchField();
$settings = unserialize($settings);
$settings['preference']['delete_tms_documents_upon_disassociation'] = TRUE;
$connection->update('config')
  ->fields([
    'data' => serialize($settings),
  ])
  ->condition('collection', '')
  ->condition('name', 'lingotek.settings')
  ->execute();
