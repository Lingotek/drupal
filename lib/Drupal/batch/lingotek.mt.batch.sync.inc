<?php
/**
 * @file
 * Lingotek Translation Sync Process.
 */


/**
 * Upload Batch Worker Function: Upload Node for Translation
 */
function lingotek_mt_sync_upload_node( $nid, &$context ) {

  watchdog( 'lingotek_batch_mt', 'WORK: Upload Node: @node_id', array( '@node_id' => $nid ), WATCHDOG_DEBUG );

  $api = LingotekApi::instance();
  $node = node_load( $nid );

  // Push this node for translation.
  if ($existing_document = lingotek_lingonode($node->nid, 'document_id_' . $node->language)) {
    // Update an existing Lingotek Document.
    $api->updateContentDocument($node);
  }
  else {
    // Create a new Lingotek Document.
    // Add project and vault parameters to the node object.
    $node->lingotek_project_id = lingotek_lingonode($node->nid, 'project_id');
    $node->lingotek_vault_id = lingotek_lingonode($node->nid, 'valut_id');
    $api->addContentDocument($node);
    lingotek_add_missing_targets($node); 
  }

  // Set this Node and its targets to PENDING status. (meaning the content has been uploaded, but not downloaded)
  lingotek_set_node_and_targets_sync_status( $nid, LINGOTEK_NODE_SYNC_STATUS_PENDING, LINGOTEK_TARGET_SYNC_STATUS_PENDING );
  $context['results'][] = 'Upload Node: ' . $nid;

} // END:  lingotek_machine_translate_node();




/**
 * Download Batch Worker Function: Download Translated Node Content
 *
 * CRITICAL:
 *   Updating the node w/ the new language translation will cause the document to be flagged edited.
 *   So before that, the current node and target states have to be grabed and saved so they can be restored after the update.
 */
function lingotek_mt_sync_download_node_target( $nid, $drupal_language_code, &$context ) {

  watchdog( 'lingotek_batch_mt', 'WORK: Download Node: @node_id - @language', array( '@node_id' => $nid, '@language' => $drupal_language_code ), WATCHDOG_DEBUG );

  $node = node_load($nid);

  // CRITICAL:  lingotek_download_document() WILL EDIT THE DOCUMENT.
  // So first, Get the CURRENT Document and Target Sync Statuses, so we can restore those again after downloading the document.
  $node_status_array = lingotek_get_node_and_target_sync_status( $nid );

  lingotek_download_document($node, $drupal_language_code);
  $context['results'][] = 'Download Translated Node Content: ' . $nid;

  // Set this Target status to CURRENT.
  $node_status_array[ 'targets' ][ $drupal_language_code ] = LINGOTEK_TARGET_SYNC_STATUS_CURRENT;

  // Check:  Are all targets CURRENT?  If so, mark the Node CURRENT.
  $all_targets_current = TRUE;
  foreach( $node_status_array[ 'targets' ] AS $language => $status ) {
    if ( $status != LINGOTEK_TARGET_SYNC_STATUS_CURRENT ) {
      $all_targets_current = FALSE;
      break;
    }
  }
  // If, after the check, $all_targets_current is still TRUE, mark the Node status CURRENT.
  if ( $all_targets_current === TRUE ) {
    $node_status_array[ 'node_sync_status' ] = LINGOTEK_NODE_SYNC_STATUS_CURRENT;
  }

  // Save the $node_status_array
  lingotek_set_node_and_targets_sync_status_by_array( $nid, $node_status_array );

}
