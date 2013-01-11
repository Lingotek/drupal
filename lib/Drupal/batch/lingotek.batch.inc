<?php
/**
 * @file
 * Central location for batch create functions, before control is handed off to individual batch command files.
 */


/**
 * Field Language Data Cleanup Utility
 * 
 * Creates a batch to cleanup nodes with data in an 'und' language field.
 *
 * @param bool $from_post
 *   If the batch create was NOT called from a Drupal form handler, pass in false,
 *   so that the extra call is made that will start the batch processing.
 */
function lingotek_field_language_data_cleanup_batch_create( $from_post = true, $goto = '<front>' ) {

  $operations = array();
  $entity_type = 'node';
  $source_language = lingotek_get_source_language();
  $translated_types = lingotek_translatable_node_types(); // Get the list of content types that we translate.


  // Fix node level language settings
  // This selects all the nodes that are language undefined and are content types we need to translate.  We need to change these nodes from language undefined to the source language.
  $query1 = new EntityFieldQuery();
  $nodes1 = $query1->entityCondition('entity_type', $entity_type)
    ->entityCondition('bundle', $translated_types)
    ->propertyCondition('language', 'und', '=')
    ->execute();

  if( isset( $nodes1[$entity_type] ) ) {
    foreach ($nodes1[$entity_type] as $node1) {
      $operations[] = array('lingotek_node_source_language_cleanup_batch_worker', array($node1->nid, $source_language));
      $operations[] = array('lingotek_field_language_data_cleanup_batch_worker', array($node1->nid));
    }
  }


  // Fix field languages
  // This selects all nodes that have a language defined.  It does NOT select the UND language nodes.
  $query2 = new EntityFieldQuery();
  $nodes2 = $query2->entityCondition('entity_type', $entity_type)
    ->propertyCondition('language', 'und', '<>')
    ->execute();

  if( isset( $nodes2[$entity_type] ) ) {
    foreach ($nodes2[$entity_type] as $node2) {
      $operations[] = array('lingotek_field_language_data_cleanup_batch_worker', array($node2->nid));
    }
  }


  $batch = array(
    'title' => t('Lingotek Field Language Updater'),
    'operations' => $operations,
    'finished' => 'lingotek_field_language_data_cleanup_batch_finished',
    'file' => drupal_get_path('module', 'lingotek') . '/lib/Drupal/batch/lingotek.field.language.data.cleanup.inc',
  );

  batch_set($batch);

  // If this batch was NOT created from a form_submit() handler, do this to initiate the batch.
  if( $from_post === false ) {
    batch_process($goto); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.
  }

}
