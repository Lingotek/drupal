<?php

/**
 * Central location for batch create functions, before control is handed off to individual batch command files.
 */

/**
 * Field Language Data Cleanup Utility
 * 
 * Creates a batch to cleanup nodes with data in an 'und' language field.
 *
 * @param bool $front
 * If this batch was NOT created from a form_submit() handler, then pass in TRUE
 */
function lingotek_field_language_data_cleanup_batch_create($front = FALSE) {

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

  if (isset($nodes1[$entity_type])) {
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

  if (isset($nodes2[$entity_type])) {
    foreach ($nodes2[$entity_type] as $node2) {
      $operations[] = array('lingotek_field_language_data_cleanup_batch_worker', array($node2->nid));
    }
  }

  if (count($operations) > 0) {

    $batch = array(
      'title' => t('Lingotek Field Language Updater'),
      'operations' => $operations,
      'finished' => 'lingotek_field_language_data_cleanup_batch_finished',
      'file' => 'lingotek.batch.inc'
    );

    batch_set($batch);
    if ($front) { // If this batch was NOT created from a form_submit() handler, do this to initiate the batch.
      batch_process('<front>'); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.
    }
  } // END:  if operations
}

/**
 * Batch Create - Sync:  Uploads new and changed documents for translation and Downloads translated documents.
 *
 * Creates the batch operations array.  Downloads first, then uploads.
 */
function lingotek_sync_batch_create($upload = true, $download = true, $download_doc_ids = array(), $upload_doc_ids = array()) {

  // Grab the Nodes that need to be Downloaded & Uploaded.  These are batch operation arrays.
  $download_commands = $download ? lingotek_get_sync_download_batch_elements($download_doc_ids) : array();
  $upload_commands = $upload ? lingotek_get_sync_upload_batch_elements($upload_doc_ids) : array();

  // Important: Until we add the call to get target statuses, and can work off that, do the upload SECOND. (so we dont try to download what we just uploaded.)
  $operations = array();
  $operations[] = array('lingotek_mt_sync_set_status', array('set'));
  $operations = array_merge($operations, $download_commands, $upload_commands);
  $operations[] = array('lingotek_mt_sync_set_status', array('clear'));


  // Where to send the user after the batch has processed. If redirect_url GET param exists, then use it
  $redirect = (isset($_GET['redirect_url']) && strlen($_GET['redirect_url'])) ? $_GET['redirect_url'] : LINGOTEK_BASE_URL;

  if (count($operations) > 0) {

    // Note, the first step of the batch process sets a session variable that tracks that we are in a sync state.
    // The Node update hook uses that so it knows NOT to reupload the content we just recieved.
    // The last step of the sync process clears the sync flag.   $_SESSION['lingotek_sync_in_progress']
    // As a backup (in case there is an error and the batch doesnt complete successfully) there is a backup on the lingotek_dashboard() that clears the sync status flag.
    $batch = array(
      'title' => t('Syncing Content Translations with Lingotek'),
      'operations' => $operations,
      'file' => 'lingotek.batch.inc'
    );

    batch_set($batch);
    batch_process($redirect); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.
  }
  else {
    $options = strpos($redirect, '//') !== FALSE ? array('external' => TRUE) : array();
    drupal_goto($redirect, $options);
  }
}

function lingotek_sync_batch_create_old() {

  // Grab the Nodes that need to be Downloaded & Uploaded.  These are batch operation arrays.
  $download_commands = lingotek_get_sync_download_batch_elements();
  $upload_commands = lingotek_get_sync_upload_batch_elements();

  // Important: Until we add the call to get target statuses, and can work off that, do the upload SECOND. (so we dont try to download what we just uploaded.)
  $operations = array();
  $operations[] = array( 'lingotek_mt_sync_set_status', array( 'set' ) );
  $operations = array_merge( $operations, $download_commands, $upload_commands );
  $operations[] = array( 'lingotek_mt_sync_set_status', array( 'clear' ) );

  
  // Where to send the user after the batch has processed. If redirect_url GET param exists, then use it
  $redirect = (isset($_GET['redirect_url']) && strlen($_GET['redirect_url'])) ? $_GET['redirect_url'] : LINGOTEK_BASE_URL;

  if ( count( $operations ) > 0 ) {

    // Note, the first step of the batch process sets a session variable that tracks that we are in a sync state.
    // The Node update hook uses that so it knows NOT to reupload the content we just recieved.
    // The last step of the sync process clears the sync flag.   $_SESSION['lingotek_sync_in_progress']
    // As a backup (in case there is an error and the batch doesnt complete successfully) there is a backup on the lingotek_dashboard() that clears the sync status flag.
    $batch = array(
      'title' => t('Syncing Content Translations with Lingotek'),
      'operations' => $operations,
      'file' => drupal_get_path('module', 'lingotek') . '/lib/Drupal/batch/lingotek.mt.batch.sync.inc',
    );

    batch_set($batch);
    batch_process($redirect); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.

  }
  else {
    $options = strpos($redirect, '//') !== FALSE ? array('external' => TRUE) : array();
    drupal_goto($redirect, $options);
  }
}

/**
 * Sync - Upload Batch Elements:  Creates the batch elements for nodes/documents that need to be uploaded.
 */
function lingotek_get_sync_upload_batch_elements() {

  $operations = array();

  // Grab nodes that are currently marked 'EDITED'
  $query = db_select('lingotek', 'l')->fields('l');
  $query->condition('lingokey', 'node_sync_status');
  $query->condition('lingovalue', LINGOTEK_NODE_SYNC_STATUS_EDITED);

  $result = $query->execute();

  while ($record = $result->fetchAssoc()) {
    $operations[] = array('lingotek_mt_sync_upload_node', array($record['nid']));
  }

  return $operations;
}

/**
 * Sync - Download Batch Elements:  Creates the batch elements for nodes/documents that need to be downloaded.
 */
function lingotek_get_sync_download_batch_elements() {

  $operations = array();

  $target_locales = lingotek_get_target_locales();
  foreach ($target_locales as $lingotek_locale) {

    $key = 'target_sync_status_' . $lingotek_locale;
    $query = db_select('lingotek', 'l')->fields('l');
    $query->condition('lingokey', $key);
    $query->condition('lingovalue', LINGOTEK_TARGET_SYNC_STATUS_PENDING);

    $result = $query->execute();
    while ($record = $result->fetchAssoc()) {
      $operations[] = array('lingotek_mt_sync_download_node_target', array($record['nid'], $lingotek_locale));
    }
  }

  return $operations;
}

/**
 * Batch Create: Lingotek Identify Content - create informative lingonode data (in lingotek table) for pre-existing content 
 */
function lingotek_batch_identify_content($front = FALSE) {

  $result = db_query('SELECT DISTINCT nid FROM {lingotek}');
  $existing_nids = $result->fetchCol();
  $operations = array();

  foreach (lingotek_get_content_types(TRUE) as $type) {

    $nodes = node_load_multiple(array(), array('type' => $type));

    foreach ($nodes as $node) {
      if (!in_array($node->nid, $existing_nids)) {
        // Add content nodes to lingotek table, to indicate that they are machine translatable nodes
        $operations[] = array('lingotek_set_node_sync_status', array($node->nid, LINGOTEK_NODE_SYNC_STATUS_EDITED));
      };
    }
  }

  $batch = array(
    'title' => t('Identifying Translatable Content'),
    'operations' => $operations,
    'finished' => 'lingotek_batch_identify_content_finished'
  );

  batch_set($batch);
  if ($front) {
    batch_process('<front>'); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.
  }
}

/**
 *
 * Old Upload / Download Batch Functions -- Replaced with the combined Sync batch methods.
 *
 */

/**
 * Batch Create - Upload:  Create a Bulk Machine Translate UPLOAD Batch.
 */
function lingotek_bulk_mt_upload_batch_create() {

  $operations = array();
  $bundles = lingotek_translatable_node_types(); // The nodes we translate.
  // Grab nodes that are currently marked 'EDITED'
  $query = db_select('lingotek', 'l')->fields('l');
  $query->condition('lingokey', 'node_sync_status');
  $query->condition('lingovalue', LINGOTEK_NODE_SYNC_STATUS_EDITED);

  $result = $query->execute();

  while ($record = $result->fetchAssoc()) {
    $operations[] = array('lingotek_machine_translate_node', array($record['nid']));
  }

  $batch = array(
    'title' => t('Sending Content to Lingotek for Translation.'),
    'operations' => $operations,
    'finished' => 'lingotek_bulk_mt_upload_batch_finished',
    'file' => 'lingotek.batch.inc'
  );

  batch_set($batch);
  batch_process('<front>'); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.
}

/**
 * Batch Create - Download:  Create a Bulk Machine Translate DOWNLOAD Batch.
 */
function lingotek_bulk_mt_download_batch_create($lingotek_locale) {

  $operations = array();

  if ($lingotek_locale) {

    $drupal_language_code = Lingotek::convertLingotek2Drupal($lingotek_locale);
    // NOTE!!!   lingotek_download_document() does its OWN drupal to lingotek lookup.  So pass in the DRUPAL language code
    // Grab nodes that are currently marked 'PENDING'
    $query = db_select('lingotek', 'l')->fields('l');
    $query->condition('lingokey', 'node_sync_status');
    $query->condition('lingovalue', LINGOTEK_TARGET_SYNC_STATUS_PENDING);

    $result = $query->execute();

    while ($record = $result->fetchAssoc()) {
      $operations[] = array('lingotek_download_node_machine_translation', array($record['nid'], $drupal_language_code));
    }

    $batch = array(
      'title' => t('Downloading Lingotek Translated Content'),
      'operations' => $operations,
      'finished' => 'lingotek_bulk_mt_download_batch_finished',
      'file' => 'lingotek.batch.inc'
    );

    batch_set($batch);
    batch_process('<front>'); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.
  } // END:  if language_code
}

/**
 * Batch Create:  Bulk Machine Translate Upload.
 */
function lingotek_dashboard_mt_upload() {

  lingotek_bulk_mt_upload_batch_create();
}

/**
 * Batch Create:  Bulk Machine Translate Download.
 */
function lingotek_dashboard_mt_download() {

  if (isset($_GET['code'])) {

    lingotek_bulk_mt_download_batch_create($_GET['code']);
  } // If Language Code
}

////////

/**
 * Lingotek Batch Translate Process.
 */

/**
 * Batch Worker Function: Download a Machine Translation
 */
function lingotek_download_node_machine_translation($nid, $language_code, &$context) {
  $node = node_load($nid);
  lingotek_download_document($node, $language_code);
  $context['results'][] = 'Download Translated Node Content: ' . $nid;
}

/**
 * Batch Finished: Bulk Machine Translation Complete.
 */
function lingotek_bulk_mt_download_batch_finished($success, $results, $operations) {

  $messages = array();
  $result_count = count($results);

  if ($success) { // The 'success' (TRUE/FALSE) means no fatal PHP errors were detected. All other error management should be handled using 'results'.
    $messages[] = t('The process finished successfully.');
    $messages[] = format_plural($result_count, 'One translated node has been downloaded.', '@count translated nodes have been downloaded.');
  }
  else {
    $messages[] = t('The process finished with an errors.  Some content may have been missed.');
    $messages[] = format_plural($result_count, 'One translated node has been downloaded.', '@count translated nodes have been downloaded.');
  }

  $_SESSION['bulk_mt_batch_download_results'] = $messages; // Save data before redirect.

  drupal_goto(LINGOTEK_BASE_URL . '/mt-content-downloaded');
}

/**
 * Dashboard Confirmation Screen: Machine Translation Download Complete
 * Users are directed here after all their translated content has been downloaded.
 */
function lingotek_bulk_mt_download_batch_complete() {

  $form = array();
  $messages = $_SESSION['bulk_mt_batch_download_results'];

  $form['lingotek_message_1'] = array('#markup' => '<h3>Your translated content has been downloaded.</h3>');
  $form['lingotek_message_2'] = array('#markup' => '<div>&nbsp;</div>');
  $form['lingotek_message_3'] = array('#markup' => '<div><strong>Details:</strong></div>');

  $form['lingotek_message_4'] = array('#markup' => '<blockquote>');

  $cnt = 1;
  foreach ($messages as $message) {
    $form['lingotek_message_details_' . $cnt] = array('#markup' => '<div>' . $message . '</div>');
    $cnt++;
  }

  $form['lingotek_message_5'] = array('#markup' => '</blockquote>');

  $form['lingotek_button_spacer'] = array('#markup' => '<div>&nbsp;</div>');
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Continue')
  );

  return $form;
}

/**
 * Dashboard Confirmation Screen: Machine Translation Download Complete - Form Submit - Redirects Back to the Lingotek Dashboard.
 */
function lingotek_bulk_mt_download_batch_complete_submit($form, $form_state) {
  drupal_goto(LINGOTEK_BASE_URL);
}

///////////

/**
 * Lingotek Translation Sync Process.
 */

/**
 * Upload Batch Worker Function: Upload Node for Translation
 */
function lingotek_mt_sync_upload_node($nid, &$context) {

  watchdog('ltk_upload_node', 'node: @node_id', array('@node_id' => $nid), WATCHDOG_DEBUG);

  $api = LingotekApi::instance();
  $node = node_load($nid);

  // Push this node for translation.
  if ($existing_document = lingotek_lingonode($node->nid, 'document_id')) {
    // Update an existing Lingotek Document.
    $api->updateContentDocument($node);
  }
  else {
    // Create a new Lingotek Document.
    $api->addContentDocument($node, TRUE);
  }

  $context['results'][] = 'Upload Node: ' . $nid;
}

/**
 * Download Batch Worker Function: Download Translated Node Content
 */
function lingotek_mt_sync_download_node_target($nid, $lingotek_locale, &$context) {

  watchdog('ltk_download_target', 'node: @node_id (@language)', array('@node_id' => $nid, '@language' => $lingotek_locale), WATCHDOG_DEBUG);

  $node = node_load($nid);
  lingotek_download_document($node, $lingotek_locale);
  $context['results'][] = t('Download Target Translation: Node #@nid (@langcode)', array('@nid' => $nid, '@langcode' => $lingotek_locale));
}

/**
 * Sets and clears session sync flags.
 */
function lingotek_mt_sync_set_status($status, &$context) {

  switch ($status) {
    case 'set':
      $_SESSION['lingotek_sync_in_progress'] = 'syncing';
      break;
    case 'clear':
      if (isset($_SESSION['lingotek_sync_in_progress'])) {
        unset($_SESSION['lingotek_sync_in_progress']);
      }
      break;
  }
}

///////////

/**
 * Lingotek Batch Translate Process.
 */

/**
 * Batch Worker Function: Machine Translate a Node.
 */
function lingotek_machine_translate_node($nid, &$context) {

  watchdog('ltk_upload', 'nide: @node_id', array('@node_id' => $nid), WATCHDOG_DEBUG);

  $api = LingotekApi::instance();
  $node = node_load($nid);

  // Push this node for translation.
  if ($existing_document = lingotek_lingonode($node->nid, 'document_id')) {

    // Update an existing Lingotek Document.
    $api->updateContentDocument($node);
  }
  else {

    // Create a new Lingotek Document.
    $api->addContentDocument($node, TRUE);
  }

  $context['results'][] = 'Upload Node: ' . $nid;
}

/**
 * Batch Finished: Bulk Machine Translation Complete.
 */
function lingotek_bulk_mt_upload_batch_finished($success, $results, $operations) {

  $messages = array();
  $result_count = count($results);

  if ($success) { // The 'success' (TRUE/FALSE) means no fatal PHP errors were detected. All other error management should be handled using 'results'.
    $messages[] = t('The process finished successfully.');
    $messages[] = format_plural($result_count, 'One node has been queued for translation.', '@count nodes have been queued for translation.');
  }
  else {
    $messages[] = t('The process finished with an errors.  Some content may have been missed.');
    $messages[] = format_plural($result_count, 'One node queued for translation.', '@count nodes queued for translation.');
  }

  $_SESSION['bulk_mt_batch_results'] = $messages; // Save data before redirect.

  drupal_goto(LINGOTEK_BASE_URL . '/mt-content-queued');
}

/**
 * Dashboard Confirmation Screen: Machine Translation Complete
 * Users are directed here after all their content has been queued for translation.
 */
function lingotek_bulk_mt_upload_batch_complete() {

  $form = array();

  $messages = $_SESSION['bulk_mt_batch_results'];

  $form['lingotek_message_1'] = array('#markup' => '<h3>Your content has been queued for translation.</h3>');
  $form['lingotek_message_2'] = array('#markup' => '<div>&nbsp;</div>');
  $form['lingotek_message_3'] = array('#markup' => '<div><strong>Details:</strong></div>');

  $form['lingotek_message_4'] = array('#markup' => '<blockquote>');

  $cnt = 1;
  foreach ($messages as $message) {
    $form['lingotek_message_details_' . $cnt] = array('#markup' => '<div>' . $message . '</div>');
    $cnt++;
  }

  $form['lingotek_message_5'] = array('#markup' => '</blockquote>');

  $form['lingotek_button_spacer'] = array('#markup' => '<div>&nbsp;</div>');
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Continue')
  );

  return $form;
}

/**
 * Dashboard: Machine Translation Complete - Form Submit - Redirects Back to the Lingotek Dashboard.
 */
function lingotek_bulk_mt_upload_batch_complete_submit($form, $form_state) {
  drupal_goto(LINGOTEK_BASE_URL);
}

/////// FIELD CLEAN-UP

/**
 * Functions for the Batch:  lingotek_field_language_data_cleanup_batch_create()
 */

/**
 * Batch API worker for changing the node language setting.
 */
function lingotek_node_source_language_cleanup_batch_worker($nid, $source_language) {

  $loaded_node = node_load($nid);
  $loaded_node->language = $source_language;
  $loaded_node->lingotek_upload_override = 0; // Set 0 : Ensure that uploading does not occur. Set 1 : Force uploading to occur
  node_save($loaded_node);
}

/**
 * Batch API processor for field data language updates.
 */
function lingotek_field_language_data_cleanup_batch_worker($nid, &$context) {

  $process_node = node_load($nid);
  if ($process_node->nid) {
    lingotek_field_language_data_cleanup_update_node($process_node->nid);
    //$context['message'] = t('Updating field data for node: @node_title', array('@node_title' => $process_node->title));
    $context['message'] = t('Preparing translatable content: @node_title', array('@node_title' => $process_node->title));
  }

  $context['finished'] = 1;
}

/**
 * Ensures correct language-specific field data for the specified item.
 * 
 * Logic: Look at each translatable_node_field (Any field marked for lingotek translation management) for the given node.
 * If the field has data in the language 'und' area, and is empty in the language area that this node is, copy the data over.
 * So if this node is marked as English, but there is no data in the English language spots, but there IS in the 'und' spots, move the data to the English locations.
 *
 * @param int $nid
 *   The node ID of the item to be updated.
 *
 * @return bool
 *   TRUE if the specified node's field data was updated. FALSE if no changes were made.
 */
function lingotek_field_language_data_cleanup_update_node($nid) {
  $edited = FALSE;

  $node = node_load($nid, NULL, TRUE);
  if ($node->nid) {
    if ($node->language != 'und') {
      $translatable_fields = lingotek_translatable_node_fields();
      foreach ($translatable_fields as $field_name) {
        if (!empty($node->{$field_name}['und']) && empty($node->{$field_name}[$node->language])) {
          $node->{$field_name}[$node->language] = $node->{$field_name}['und'];
          $edited = TRUE;
        }
      }
    }

    if ($edited) {
      $node->lingotek_upload_override = 0;
      node_save($node);
    }
  }
  else {
    watchdog('lingotek', 'Attempted to update field data for a non-existent node: @node_id', array('@node_id' => $node_id), WATCHDOG_ERROR);
  }

  return $edited;
}

/**
 * FINISHED CALLBACK:  lingotek_field_language_data_cleanup_batch_create()
 */
function lingotek_field_language_data_cleanup_batch_finished($success, $results, $operations) {
  
}
