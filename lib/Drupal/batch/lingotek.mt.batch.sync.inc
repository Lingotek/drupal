<?php
/**
 * @file
 * Lingotek Translation Sync Process.
 */


/**
 * Upload Batch Worker Function: Upload Node for Translation
 */
function lingotek_mt_sync_upload_node( $nid, &$context ) {

  watchdog( 'ltk_upload_node', 'node: @node_id', array( '@node_id' => $nid ), WATCHDOG_DEBUG );

  $api = LingotekApi::instance();
  $node = node_load( $nid );

  // Push this node for translation.
  if ($existing_document = lingotek_lingonode($node->nid, 'document_id')) {
    // Update an existing Lingotek Document.
    $api->updateContentDocument($node);
  }
  else {
    // Create a new Lingotek Document.
    $api->addContentDocument($node, TRUE);
  }

  // Set this Node and its targets to PENDING status. (meaning the content has been uploaded, but not downloaded)
  lingotek_set_node_and_targets_sync_status( $nid, LINGOTEK_NODE_SYNC_STATUS_CURRENT, LINGOTEK_TARGET_SYNC_STATUS_PENDING );
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

  watchdog( 'ltk_download_target', 'node: @node_id (@language)', array( '@node_id' => $nid, '@language' => $drupal_language_code ), WATCHDOG_DEBUG );

  $node = node_load($nid);
  lingotek_download_document($node, $drupal_language_code);
  $context['results'][] = t('Download Target Translation: Node #@nid (@langcode)', array('@nid'=>$nid,'@langcode'=>$drupal_language_code));
}


/**
 * Sets and clears session sync flags.
 */
function lingotek_mt_sync_set_status( $status, &$context ) {

  switch ($status) {
    case 'set':
      $_SESSION['lingotek_sync_in_progress'] = 'syncing';
      break;
    case 'clear':
      if ( isset( $_SESSION['lingotek_sync_in_progress'] ) ) {
        unset($_SESSION['lingotek_sync_in_progress']);
      }
      break;
  }

}
