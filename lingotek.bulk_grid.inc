<?php

/**
 * @file
 * Bulk Grid form
 */

function lingotek_bulk_grid_form($form, $form_state) {

  $path_to_lingotek = drupal_get_path('module', 'lingotek');
  lingotek_is_module_setup();

  // Output success messages for actions
  if (isset($_SESSION['lingotek_edit_nodes'])) {
    drupal_set_message(t('Settings changed for @number node(s).', array('@number' => count($_SESSION['lingotek_edit_nodes']))));
    unset($_SESSION['lingotek_edit_nodes']);
  }
  if (isset($_SESSION['lingotek_upload_nodes'])) {
    drupal_set_message(t('@number node(s) uploaded to Lingotek successfully.', array('@number' => $_SESSION['lingotek_upload_nodes'])));
    unset($_SESSION['lingotek_upload_nodes']);
  }
  if (isset($_SESSION['lingotek_download_nodes'])) {
    drupal_set_message(t('@number node(s) downloaded from Lingotek successfully.', array('@number' => $_SESSION['lingotek_download_nodes'])));
    unset($_SESSION['lingotek_download_nodes']);
  }
  if (isset($_SESSION['lingotek_sync_nodes'])) {
    drupal_set_message(t('Target progress synced for @number node(s).', array('@number' => $_SESSION['lingotek_sync_nodes'])));
    unset($_SESSION['lingotek_sync_nodes']);
  }

  $form['header_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Manage Node Translation'),
    '#group' => 'translation_grid',
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#attached' => array(
      'js' => array( // We get php errors when TableSort and AJAX are combined (see https://drupal.org/node/1833746).
                     // So we are faking it with a hidden submit button and some jQuery.
        $path_to_lingotek . '/js/lingotek.bulk_grid.js',
      ),
    ),
  );

  // build source and target language arrays and selector
  $source_languages_base = lingotek_get_node_source_languages(); // for selector
  $source_languages = array();
  $target_languages_raw = language_list('language');
  $target_languages_base = array(); // for selector
  $target_languages = array();

  foreach ($target_languages_raw as $target_raw) {
    $target_languages[$target_raw->lingotek_locale] = array();
    $target_languages_base[$target_raw->lingotek_locale] = $target_raw->name . ' (' . $target_raw->lingotek_locale . ')';
    foreach ($target_raw as $key => $value) {
      $target_languages[$target_raw->lingotek_locale][$key] = $value;
    }
  }
  foreach ($source_languages_base as $source_language_base) {
    if (isset($target_languages[$source_language_base])) {
      // s__ added to source languages to distinguish between source and target (i.e. if English is both a source and a target)
      $source_languages_base['s__' . $source_language_base] = $target_languages[$source_language_base]['name'] . ' (' . $source_language_base . ')';
      $source_languages[$source_language_base] = $target_languages[$source_language_base];
      unset($source_languages_base[$source_language_base]);
    }
  }
  $source_and_target = array(
    'Source:' => $source_languages_base,
    'Target:' => $target_languages_base,
  );


  // build form header

  // process source_target_select to keep the same visible selection
  $stselect_default = 's__' . Lingotek::convertDrupal2Lingotek(variable_get('lingotek_source_language', 'en'));
  if (isset($_SESSION['source_target_select'])) {
    $stselect_default = $_SESSION['source_target_select'];
  }
  $form['header_fieldset']['source_target_select'] = array(
    '#type' => 'select',
    '#title' => t('Language'),
    '#options' => $source_and_target,
    '#default_value' => $stselect_default,
  );

  // process limit_select to correctly limit the query and pager
  $limit = 10;
  if (isset($_SESSION['limit_select'])) {
    $limit = $_SESSION['limit_select'];
  }
  $form_state['values']['limit_select'] = $limit;

  $form['header_fieldset']['limit_select'] = array(
    '#type' => 'select',
    '#title' => t('Page Limit'),
    '#options' => array(
      10 => '10',
      25 => '25',
      50 => '50',
      100 => '100',
    ),
    '#default_value' => $limit,
  );

  // This button gets hidden by javascript attached to the header_fieldset.
  // The selectors in the header use it to submit when they are changed.
  $form['header_fieldset']['submit_changes'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
    '#submit' => array('lingotek_grid_filter_submit'),
  );

  // process source_target_select to produce the correct form
  $form_state['values']['source'] = TRUE;
  if (!isset($_SESSION['source_target_select'])) {
    $language_raw = 's__' . Lingotek::convertDrupal2Lingotek(variable_get('lingotek_source_language', 'en'));
  }
  else {
    $language_raw = $_SESSION['source_target_select'];
  }
  $language = str_replace('s__', '', $language_raw, $replaced);
  if ($replaced == 0) {
    $form_state['values']['source'] = FALSE;
  }
  $form_state['values']['source_target_select'] = $language;

  // Fieldset for customizable columns
  $form['customize_table_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Customize Table'),
    '#group' => 'translation_grid',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  // Container to create styleable div class
  $form['customize_table_fieldset']['custom_columns'] = array(
    '#type' => 'container',
  );

  // Container to create styleable div class
  $form['customize_table_fieldset']['custom_buttons'] = array(
    '#type' => 'container',
  );

  // Submit customized columns button
  $form['customize_table_fieldset']['custom_buttons']['custom_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit Customization'),
    '#submit' => array('lingotek_grid_filter_submit'),
  );

  // Reset column defaults button
  $form['customize_table_fieldset']['custom_buttons']['custom_clear'] = array(
    '#type' => 'submit',
    '#value' => t('Reset to Defaults'),
    '#submit' => array('lingotek_grid_reset_columns'),
  );

  // Container fieldset for filters
  $form['filter_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Show Only Items Where'),
    '#group' => 'translation_grid',
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  // Container to create styleable div class
  $form['filter_fieldset']['filters'] = array(
    '#type' => 'container',
  );

  // Container to create styleable div class
  $form['filter_fieldset']['filter_buttons'] = array(
    '#type' => 'container',
  );
  
  // Filter submit button
  $form['filter_fieldset']['filter_buttons']['filter_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit Filters'),
    '#submit' => array('lingotek_grid_filter_submit'),
  );

  // Reset filter defaults button
  $form['filter_fieldset']['filter_buttons']['filter_reset'] = array(
    '#type' => 'submit',
    '#value' => t('Clear Filters'),
    '#submit' => array('lingotek_grid_clear_filters'),
  );

  // Container fieldset for actions
  $form['actions_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Actions'),
    '#group' => 'translation_grid',
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  // Populate form_state with filter values so the query can use them
  $form_state['values']['filters'] = lingotek_grid_get_filters($form_state['values']['source']);
  $form_state['values']['columns'] = lingotek_grid_get_columns($form_state['values']['source']);

  $table_data = array();
  $form_state['values']['grid_header'] = array();
  $action_options = array();

  // Get filters and table based on source or target
  if ($form_state['values']['source']) {
    // Define source actions - keys are used to decide what action to do in the 'lingotek_grid_action_submit' function
    $action_options = array(
      'upload' => t('Upload for Translation'),
      'download_all' => t('Download Translations for all Targets'),
      'sync' => t('Sync Translation Progress with Lingotek'),
      'reset' => t('Reset Translations'),
      'edit' => t('Edit Lingotek Node Settings'),
    );
  }
  else {
    // Define target actions - keys are used to decide what action to do in the 'lingotek_grid_action_submit' function
    $action_options = array(
      'download' => t('Download Translations'),
      'upload' => t('Upload Source for Translation'),
      'sync' => t('Sync Target Progress with Lingotek'),
    );
  }

  // Build column selection checkboxes
  $form['customize_table_fieldset']['custom_columns'] += lingotek_grid_build_column_checkboxes($form_state);
  // Build filter form elements
  $form['filter_fieldset']['filters'] += lingotek_grid_build_filters($form_state);
  // Run query to get table rows
  $table_data = lingotek_grid_get_rows($form, $form_state);

  // Build actions selector
  $form['actions_fieldset']['actions_select'] = array(
    '#type' => 'select',
    '#options' => $action_options,
  );

  // Actions submit button
  $form['actions_fieldset']['actions_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit Action'),
    '#name' => 'actions_submit',
    '#submit' => array('lingotek_grid_action_submit'),
  );

  // Update Workflow Progress for all 'In Progress' nodes
  $form['actions_fieldset']['sync_all'] = array(
    '#type' => 'submit',
    '#value' => t('Update Status for \'In Progress\' Nodes'),
    '#name' => 'sync_all',
    '#submit' => array('lingotek_grid_action_submit'),
  );

  // Output time that that ^^ button was last pushed
  $last_updated = variable_get('lingotek_pending_last_updated', NULL);
  $form['actions_fieldset']['last_synced_time'] = array(
    '#markup' => t('Last run @time', array('@time' => $last_updated ? lingotek_human_readable_timestamp($last_updated) . ' ago' : 'Never')),
  );

  // div container for the table and pager
  $form['grid_container'] = array(
    '#type' => 'container',
  );

  if (!empty($table_data)) { // If results, render the table.  Otherwise, output 'No results were returned.'
    // Count of returned results
    $page = pager_find_page(); // Get current page from url

    // Calculate and output the number of results shown
    $form['grid_container']['count'] = array(
      '#markup' => t('Showing results @first through @last.', array('@first' => ($page * $form_state['values']['limit_select'] + 1), '@last' => ($page * $form_state['values']['limit_select'] + count($table_data)))),
    );
    // The actual table
    $form['grid_container']['the_grid'] = array(
      '#type' => 'tableselect',
      '#header' => $form_state['values']['grid_header'],
      '#options' => $table_data,
    );
    // The pager
    $form['grid_container']['pager'] = array(
      '#theme' => 'pager',
    );
  }
  else {
    $form['grid_container']['none'] = array(
      '#markup' => t('No results were returned.'),
    );
  }

  return $form;
}

/**
 * Submit function for The Grid's filters (header, column, and filter fieldsets)
 * Adds filters to the session variable so the query can use them after the page load
 */
function lingotek_grid_filter_submit($form, $form_state) {
  $stselected = FALSE;
  $lselected = FALSE;
  // we have to add some of these keys to the session because otherwise they are not saved after the form submission
  if (isset($form_state['clicked_button']) && $form_state['clicked_button']['#name'] != 'op') {
    $_SESSION['button'] = $form_state['clicked_button']['#name'];
  }
  if (isset($form_state['values'])) {
    foreach ($form_state['values'] as $key => $value) {
      $add_key_to_session = FALSE;
      $nest = NULL;
      if ($key == 'source_target_select') {
        $add_key_to_session = TRUE;
        $stselected = TRUE;
      }
      elseif ($key == 'limit_select') {
        $add_key_to_session = TRUE;
        $lselected = TRUE;
      }
      elseif (strpos($key, '__filter')) {
        $add_key_to_session = TRUE;
        $nest = 'grid_filters'; //stored in $_SESSION['grid_filters'][$key]
      }
      elseif (strpos($key, '__custom')) {
        $add_key_to_session = TRUE;
        $nest = 'grid_custom'; //stored in $_SESSION['grid_custom'][$key]
      }
      // if we want this key, add it to the session
      if ($add_key_to_session) {
        if (is_null($nest)) {
          $_SESSION[$key] = $value;
        }
        else {
          $_SESSION[$nest][$key] = $value;
        }

      }
    }
  }
  if (!$stselected) {
    $_SESSION['source_target_select'] = 's__' . Lingotek::convertDrupal2Lingotek(variable_get('lingotek_source_language', 'en'));
  }
  if (!$lselected) {
    $_SESSION['limit_select'] = 10;
  }
}

/**
 * Submit function for The Grid's actions
 * The action corresponds to the key of the option selected
 * Often redirects to batch operations or to other pages entirely
 */
function lingotek_grid_action_submit($form, $form_state) {
  $nids = array();
  if (isset($form_state['clicked_button']) && $form_state['clicked_button']['#name'] == 'actions_submit') { // If submitting an action
    foreach ($form_state['values']['the_grid'] as $value) {
      if ($value != 0) {
        $nids[] = $value;
      }
    }
    $language = str_replace('s__', '', $form_state['values']['source_target_select'], $removed); // Remove s__ source tag from language
    $source = $removed > 0; // source language if removed
    if (isset($form_state['values']['actions_select'])) { // If an action was selected (which it would be, I don't know if this could ever NOT occur with normal use)
      $action = $form_state['values']['actions_select']; // Get the action
      if (count($nids) <= 0) { // Select a node
          drupal_set_message(t('You must select a node to @action.', array('@action' => $action)), 'warning'); // Or pay the price
      }
      elseif ($action == 'upload') { // If uploading
        $_SESSION['lingotek_upload_nodes'] = count($nids);
        $batch = array(
          'title' => t('Uploading Nodes To Lingotek'),
        );
        $operations = lingotek_get_sync_upload_batch_elements($nids);
        $batch['operations'] = $operations;
        $redirect = 'admin/settings/lingotek/grid';

        batch_set($batch);
        batch_process($redirect); // Run batch operations to upload all of the selected nodes to Lingotek
      }
      elseif ($action == 'download_all') { // If downloading all targets
        $_SESSION['lingotek_download_nodes'] = count($nids);
        $target_locales = lingotek_get_target_locales();
        $document_ids = LingotekSync::getDocIdsFromNodeIds($nids);
        $complete_targets = array();
        $incomplete_targets = array();

        foreach ($target_locales as $language) { // Separate the nodes by target_sync_progress_[lang code] percentage
          $complete_nids = LingotekSync::getNodeIdSubsetByTargetStatusReady($nids, $language);
          $complete_doc_ids = array();
          if (!empty($complete_nids)) {
            $complete_doc_ids = LingotekSync::getDocIdsFromNodeIds($complete_nids);
          }
          foreach ($document_ids as $doc_id) {
            if (in_array($doc_id, $complete_doc_ids)) { // If workflow completed, create target json
              $complete_targets[] = (object) array('document_id' => $doc_id, 'locale' => $language);
            }
            else {
              $incomplete_targets[] = (object) array('document_id' => $doc_id, 'locale' => $language);
            }
          }
        }
        $batch = array(
          'title' => t('Downloading Translations from Lingotek'),
          'operations' => array(),
        );
        $batch['operations'] = array_merge($batch['operations'], lingotek_get_sync_download_batch_elements($complete_targets, LingotekSync::STATUS_CURRENT)); // If target progress is at 100, download and set to current
        $batch['operations'] = array_merge($batch['operations'], lingotek_get_sync_download_batch_elements($incomplete_targets, LingotekSync::STATUS_PENDING)); // Otherwise, download and set to pending
        $redirect = 'admin/settings/lingotek/grid';

        batch_set($batch);
        batch_process($redirect); // Run the batch operations
      }
      elseif ($action == 'reset') { // If resetting translations
        $_SESSION['lingotek_reset_nodes'] = $nids;
        drupal_goto('admin/settings/lingotek/grid/reset/'); // Redirect to the reset translations form to do so
      }
      elseif ($action == 'edit') { // If editing node settings
        $_SESSION['lingotek_edit_nodes'] = $nids;
        drupal_goto('admin/settings/lingotek/grid/edit/'); // Redirect to the edit Lingotek node settings form - for multiple nodes, form defaults are global defaults
      }
      elseif ($action == 'download') { // If downloading a single target
        $doc_ids = LingotekSync::getDocIdsFromNodeIds($nids);
        if ($doc_ids != NULL && !empty($doc_ids)) {
          $_SESSION['lingotek_download_nodes'] = count($nids);
          foreach ($doc_ids as $doc_id) {
            $targets[] = (object) array('document_id' => $doc_id, 'locale' => $language);
          }
          $batch = array(
            'title' => t('Downloading Target Translations from Lingotek'),
            'operations' => lingotek_get_sync_download_batch_elements($targets),
          );
          $redirect = 'admin/settings/lingotek/grid';

          batch_set($batch);
          batch_process($redirect);
        }
        else {
          drupal_set_message(t('No documents found associated with these nodes.'), 'warning');
        }
      }
      elseif ($action == 'sync') { // If syncing the progress
        $_SESSION['lingotek_sync_nodes'] = count($nids);
        lingotek_update_target_progress_batch_create($nids); // Run batch operations to get the progress report from Lingotek
      }
    }
  }
  elseif (isset($form_state['clicked_button']) && $form_state['clicked_button']['#name'] == 'sync_all') { // If the sync all button was clicked
    $nids = LingotekSync::getNodeIdsByStatusAndTarget(LingotekSync::STATUS_PENDING);
    if (count($nids) > 0) {
      $_SESSION['lingotek_sync_nodes'] = count($nids);
      variable_set('lingotek_pending_last_updated', time());
      lingotek_update_target_progress_batch_create($nids); // Run batch operations to sync all 'In Progress' nodes
    }
    else {
      drupal_set_message(t('No nodes to update'));
    }
  }
}

/**
 * Builds the checkbox elements for customizing The Grid's columns
 * Uses predefined defaults specified in 'lingotek_grid_define_columns'
 */
function lingotek_grid_build_column_checkboxes($form_state) {
  $prefix = $form_state['values']['source'] ? 'source_' : 'target_'; // Prefix specified to allow source and target defaults/options to be separate
  $suffix = '__custom'; // Suffix specified because the filter submit function differentiates based on this tag and puts the keys into the session variable as such
  $columns = lingotek_grid_define_columns($form_state['values']['source']); // Allowed columns and defaults for source and target grids are defined here
  $column_elements = array(
    'nid' => array(
      '#type' => 'checkbox',
      '#title' => t('Node ID'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'nid' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'nid' . $suffix] : in_array('nid', $columns['defaults']),
    ),
    'title' => array(
      '#type' => 'checkbox',
      '#title' => t('Node Title'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'title' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'title' . $suffix] : in_array('title', $columns['defaults']),
    ),
    'document_id' => array(
      '#type' => 'checkbox',
      '#title' => t('Doc ID'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'document_id' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'document_id' . $suffix] : in_array('document_id', $columns['defaults']),
    ),
    'upload_status' => array(
      '#type' => 'checkbox',
      '#title' => t('Upload Status'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'upload_status' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'upload_status' . $suffix] : in_array('upload_status', $columns['defaults']),
    ),
    'content_type' => array(
      '#type' => 'checkbox',
      '#title' => t('Content Type'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'content_type' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'content_type' . $suffix] : in_array('content_type', $columns['defaults']),
    ),
    'translation_status' => array(
      '#type' => 'checkbox',
      '#title' => t('Translation Status'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'translation_status' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'translation_status' . $suffix] : in_array('translation_status', $columns['defaults']),
    ),
    'translation_progress' => array(
      '#type' => 'checkbox',
      '#title' => t('Translation Progress'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'translation_progress' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'translation_progress' . $suffix] : in_array('translation_progress', $columns['defaults']),
    ),
    'auto_upload' => array(
      '#type' => 'checkbox',
      '#title' => t('Auto Upload'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'auto_upload' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'auto_upload' . $suffix] : in_array('auto_upload', $columns['defaults']),
    ),
    'auto_download' => array(
      '#type' => 'checkbox',
      '#title' => t('Auto Download'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'auto_download' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'auto_download' . $suffix] : in_array('auto_download', $columns['defaults']),
    ),
    'crowdsourcing' => array(
      '#type' => 'checkbox',
      '#title' => t('Crowdsourcing'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'crowdsourcing' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'crowdsourcing' . $suffix] : in_array('crowdsourcing', $columns['defaults']),
    ),
    'url_alias_translation' => array(
      '#type' => 'checkbox',
      '#title' => t('URL Alias Translation'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'url_alias_translation' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'url_alias_translation' . $suffix] : in_array('url_alias_translation', $columns['defaults']),
    ),
    'last_uploaded' => array(
      '#type' => 'checkbox',
      '#title' => t('Time Last Uploaded'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'last_uploaded' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'last_uploaded' . $suffix] : in_array('last_uploaded', $columns['defaults']),
    ),
    'locale_progress_percent' => array(
      '#type' => 'checkbox',
      '#title' => t('Target Progress Percentage'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'locale_progress_percent' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'locale_progress_percent' . $suffix] : in_array('locale_progress_percent', $columns['defaults']),
    ),
    'progress_updated' => array(
      '#type' => 'checkbox',
      '#title' => t('Progress Last Updated'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'progress_updated' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'progress_updated' . $suffix] : in_array('progress_updated', $columns['defaults']),
    ),
    'translation_progress_percent' => array(
      '#type' => 'checkbox',
      '#title' => t('Translation Progress Percentage'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'translation_progress_percent' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'translation_progress_percent' . $suffix] : in_array('translation_progress_percent', $columns['defaults']),
    ),
    'last_downloaded' => array(
      '#type' => 'checkbox',
      '#title' => t('Time Last Downloaded'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'last_downloaded' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'last_downloaded' . $suffix] : in_array('last_downloaded', $columns['defaults']),
    ),
    'translate_link' => array(
      '#type' => 'checkbox',
      '#title' => t('Translate Link'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'translate_link' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'translate_link' . $suffix] : in_array('translate_link', $columns['defaults']),
    ),
    'edit_settings' => array(
      '#type' => 'checkbox',
      '#title' => t('Edit Settings Link'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'edit_settings' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'edit_settings' . $suffix] : in_array('edit_settings', $columns['defaults']),
    ),
  );
  $column_elements = array_intersect_key($column_elements, $columns['columns']); // Reduces the output columns to the defaults specified in 'lingotek_grid_define_columns'

  return lingotek_grid_process_elements($column_elements, $prefix, $suffix); // adds prefixes and suffixes to the elements
}

/**
 * Gets the columns that will be shown from the session variable
 *
 * @param bool $source
 *    Changes whether settings for source or target variables are output
 *
 * @return array $columns
 *    Associative array keyed by column name with prefix and suffix removed
 *    Keys point to a bool which specifies if the column should be shown or not
 */
function lingotek_grid_get_columns($source = TRUE) {
  $filters = array();

  if (!isset($_SESSION['grid_custom'])) { // If the columns do not exist yet in the session variable we get an error, so reset them here.
    lingotek_grid_reset_columns();
  }

  foreach ($_SESSION['grid_custom'] as $key => $value) {
    $new_key = str_replace($source ? 'source_' : 'target_', '', $key, $replaced);
    if ($replaced > 0) {
      $columns[str_replace('__custom', '', $new_key)] = $value;
    }
  }

  return $columns;
}

/**
 * Cleans up the grid_custom session array, restoring the predefined defaults.
 */
function lingotek_grid_reset_columns() {
  if (isset($_SESSION['grid_custom'])) {
    unset($_SESSION['grid_custom']);
  }
  $source_columns = lingotek_grid_define_columns(TRUE);
  $target_columns = lingotek_grid_define_columns(FALSE);
  foreach ($source_columns['columns'] as $column) {
    $_SESSION['grid_custom']['source_' . $column . '__custom'] = in_array($column, $source_columns['defaults']);
  }
  foreach ($target_columns['columns'] as $column) {
    $_SESSION['grid_custom']['target_' . $column . '__custom'] = in_array($column, $target_columns['defaults']);
  }
}

/**
 * Defines which columns should be shown for source and target tables, and what the defaults should be.
 *
 * @param bool $source
 *    Changes whether settings for source or target variables are output
 *
 * @return array $columns
 *    Associative array with two important inner arrays:
 *      1: 'columns' points to the allowed columns,
 *      2: 'defaults' points to the base defaults to use when resetting the customization
 *    The 'columns' and 'defaults' arrays are output with the keys and the values being the same
 */
function lingotek_grid_define_columns($source = TRUE) {
  if ($source) {
    $columns = array(
      'columns' => array(
        'nid',
        'title',
        'document_id',
        'upload_status',
        'content_type',
        'auto_upload',
        'auto_download',
        'crowdsourcing',
        'url_alias_translation',
        'last_uploaded',
        'translation_progress_percent',
        'edit_settings',
      ),
      'defaults' => array(
        'nid',
        'title',
        'document_id',
        'content_type',
        'upload_status',
        'edit_settings',
      ),
    );
  }
  else {
    $columns = array(
      'columns' => array(
        'nid',
        'title',
        'document_id',
        'content_type',
        'translation_status',
        'translation_progress',
        'locale_progress_percent',
        'progress_updated',
        'last_downloaded',
        'translate_link'
      ),
      'defaults' => array(
        'nid',
        'title',
        'document_id',
        'content_type',
        'translation_status',
        'translation_progress',
        'translate_link',
      ),
    );
  }
  $columns['columns'] = array_combine($columns['columns'], $columns['columns']);
  $columns['defaults'] = array_combine($columns['defaults'], $columns['defaults']);
  return $columns;
}

/**
 * Builds the form elements for the filters.
 *
 * @return array
 *    The full list of filters is later filtered so that there is exactly one column for every filter
 */
function lingotek_grid_build_filters($form_state) {
  $columns = $form_state['values']['columns'];
  $prefix = $form_state['values']['source'] ? 'source_' : 'target_';
  $suffix = '__filter';
  $filters = array(
    'nid' => array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'nid' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'nid' . $suffix] : '',
      '#title' => t('Node ID is'),
      '#size' => 8,
    ),
    'title' => array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'title' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'title' . $suffix] : '',
      '#title' => t('Title Includes'),
      '#size' => 30,
    ),
    'document_id' => array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'document_id' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'document_id' . $suffix] : '',
      '#title' => t('Doc ID is'),
      '#size' => 10,
//      '#suffix' => '<div style="clear:both"></div>',
    ),
    'upload_status' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'upload_status' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'upload_status' . $suffix] : 'all',
      '#title' => t('Upload Status'),
      '#options' => array(
        'all' => t('All'),
        LingotekSync::STATUS_EDITED => t('Out of Sync'),
        LingotekSync::STATUS_CURRENT => t('In Sync'),
        LingotekSync::STATUS_DISABLED => t('Disabled'),
      ),
      '#multiple' => TRUE,
    ),
    'content_type' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'content_type' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'content_type' . $suffix] : 'all',
      '#title' => t('Content Type'),
      '#options' => array('all' => t('All')) + node_type_get_names(),
      '#multiple' => TRUE
    ),
    'translation_status' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'translation_status' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'translation_status' . $suffix] : 'all',
      '#title' => t('Translation Status'),
      '#options' => array(
        'all' => t('All'),
        'out_of_sync' => t('Out of Sync'),
        'in_sync' => t('In Sync'),
        'disabled' => t('Disabled'),
      ),
      '#multiple' => TRUE
    ),
    'translation_progress' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'translation_progress' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'translation_progress' . $suffix] : 'all',
      '#title' => t('Translation Progress'),
      '#options' => array(
        'all' => t('All'),
        'needs_upload' => t('Source Needs Upload'),
        'in_progress' => t('In Progress'),
        'download' => t('Ready for Download'),
        'complete' => t('Complete'),
        'disabled' => t('Disabled'),
      ),
      '#multiple' => TRUE
    ),
    'auto_upload' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'auto_upload' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'auto_upload' . $suffix] : 'all',
      '#title' => t('Auto Upload'),
      '#options' => array(
        'all' => t('All'),
        1 => t('On'),
        0 => t('Off'),
        'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_create_documents_by_default', 0) ? 'On' : 'Off'))),
      ),
      '#multiple' => TRUE
    ),
    'auto_download' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'auto_download' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'auto_download' . $suffix] : 'all',
      '#title' => t('Auto Download'),
      '#options' => array(
        'all' => t('All'),
        1 => t('On'),
        0 => t('Off'),
        'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_sync', 0) ? 'On' : 'Off')))
      ),
      '#multiple' => TRUE
    ),
    'crowdsourcing' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'crowdsourcing' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'crowdsourcing' . $suffix] : 'all',
      '#title' => t('Crowdsourcing'),
      '#options' => array(
        'all' => t('All'),
        1 => t('On'),
        0 => t('Off'),
        'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_allow_community_translation', 0) ? 'On' : 'Off')))
      ),
      '#multiple' => TRUE
    ),
    'url_alias_translation' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'url_alias_translation' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'url_alias_translation' . $suffix] : 'all',
      '#title' => t('URL Alias Translation'),
      '#options' => array(
        'all' => t('All'),
        t('None'),
        t('Translate'),
        t('Use Title'),
        'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_url_alias_translation', 0) == 2 ? 'Use Title' : variable_get('lingotek_url_alias_translation', 0) ? 'Translate' : 'None')))
      ),
      '#multiple' => TRUE
    ),
    'last_uploaded' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'last_uploaded' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'last_uploaded' . $suffix] : 'all',
      '#title' => t('Last Uploaded'),
      '#options' => array(
        'all' => t('All'),
        '1 day' => t('Less than 1 day ago'),
        '< 1 week' => t('Less than 1 week ago'),
        '>= 1 week' => t('1 week or more ago'),
        'unknown' => t('Unknown'),
      ),
      '#multiple' => FALSE
    ),
    'locale_progress_percent' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'locale_progress_percent' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'locale_progress_percent' . $suffix] : 'all',
      '#title' => t('Target Progress Percentage'),
      '#options' => array(
        'all' => t('All'),
        0 => t('0 Percent'),
        25 => t('Between 1 and 25 Percent'),
        50 => t('Between 26 and 50 Percent'),
        75 => t('Between 51 and 75 Percent'),
        100 => t('Between 76 and 100 Percent')
      ),
      '#multiple' => TRUE
    ),
    'progress_updated' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'progress_updated' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'progress_updated' . $suffix] : 'all',
      '#title' => t('Progress Updated'),
      '#options' => array(
        'all' => t('All'),
        '1 day' => t('Less than 1 day ago'),
        '< 1 week' => t('Less than 1 week ago'),
        '>= 1 week' => t('1 week or more ago'),
        'unknown' => t('Unknown'),
      ),
      '#multiple' => FALSE
    ),
    'translation_progress_percent' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'translation_progress_percent' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'translation_progress_percent' . $suffix] : 'all',
      '#title' => t('Translation Progress Percentage'),
      '#options' => array(
        'all' => t('All'),
        0 => t('0 Percent'),
        25 => t('Between 1 and 25 Percent'),
        50 => t('Between 26 and 50 Percent'),
        75 => t('Between 51 and 75 Percent'),
        100 => t('Between 76 and 100 Percent')
      ),
      '#multiple' => TRUE
    ),
    'last_downloaded' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters'][$prefix . 'last_downloaded' . $suffix]) ? $_SESSION['grid_filters'][$prefix . 'last_downloaded' . $suffix] : 'all',
      '#title' => t('Last Downloaded'),
      '#options' => array(
        'all' => t('All'),
        '1 day' => t('Less than 1 day ago'),
        '< 1 week' => t('Less than 1 week ago'),
        '>= 1 week' => t('1 week or more ago'),
        'unknown' => t('Unknown'),
      ),
      '#multiple' => FALSE
    ),
  );
  $filtered_filters = array();
  foreach ($filters as $filter => $innards) { // For each filter in the total list
    if (array_key_exists($filter, $columns) && $columns[$filter]) { // If there is a corresponding column for this filter and it is shown
      $filtered_filters[$filter] = $innards; // Add this filter to the list to be output
    }
  }

  return lingotek_grid_process_elements($filtered_filters, $prefix, $suffix); // Add prefix and suffix to the name of each filter element
}

/**
 * Get the list of filters and their values from the session
 *
 * @param bool $source
 *    Changes whether filters for source or target are output
 *
 * @return array $filters
 *    Associative array keyed by filter name with prefix and suffix removed
 *    Keys point to the values to be filtered on
 */
function lingotek_grid_get_filters($source = TRUE) {
  $filters = array();
  if (isset($_SESSION['grid_filters'])) {
    foreach ($_SESSION['grid_filters'] as $key => $value) {
      $new_key = str_replace($source ? 'source_' : 'target_', '', $key, $replaced);
      if ($replaced > 0) {
        $filters[str_replace('__filter', '', $new_key)] = $value;
      }
    }
  }
  return $filters;
}

/**
 * Completely clears out any filters from the session variable
 * Filters will automatically revert to their defaults
 */
function lingotek_grid_clear_filters() {
  if (isset($_SESSION['grid_filters'])) {
    unset($_SESSION['grid_filters']);
  }
}

/**
 * Add $prefix and $suffix to each $element name
 */
function lingotek_grid_process_elements($elements, $prefix, $suffix) {
  $new_keys = array();
  foreach ($elements as $element => $innards) {
    $new_keys[$prefix . $element . $suffix] = $innards;
  }
  return $new_keys;
}

/**
 * Dynamic query processing function for the grid
 * Since the header defines which columns are shown, this query gets all possible values and refines the header using the columns selected in the UI
 * The filters are also processed here
 *
 * @return array $table_data
 *    Returns array of rows
 *    Populates The Grid
 */
function lingotek_grid_get_rows($form, &$form_state) {

  $table_data = array();
  $language = isset($form_state['values']['source_target_select']) ? $form_state['values']['source_target_select'] : 'en_US';
  $limit = isset($form_state['values']['limit_select']) ? $form_state['values']['limit_select'] : 10;
  $filters = isset($form_state['values']['filters']) ? $form_state['values']['filters'] : array();
  $columns = isset($form_state['values']['columns']) ? $form_state['values']['columns'] : array();
  $source = isset($form_state['values']['source']) ? $form_state['values']['source'] : TRUE;
  if ($source) { // If a source language
    $nids = LingotekSync::GetNodeIdsBySource(Lingotek::convertLingotek2Drupal($language)); // Get nids of all nodes with this source language
  }
  else { // Otherwise
    $nids = LingotekSync::getNodeIdsByTarget($language); // Get nids of all nodes in the lingotek table that have this language as a target
  }
  if (!empty($nids)) { // If any nids were returned
    $header = array( // Define the tentative source header
      'nid' => array('data' => 'Node ID', 'field' => 'n.nid'),
      'title' => array('data' => 'Title', 'field' => 'n.title', 'sort' => 'asc'),
      'document_id' => array('data' => 'Doc ID', 'field' => 'document_id'),
      'upload_status' => array('data' => t('Upload Status'), 'field' => 'upload_status'),
      'content_type' => array('data' => t('Content Type'), 'field' => 'n.type'),
      'auto_upload' => array('data' => t('Auto Upload'), 'field' => 'auto_upload'),
      'auto_download' => array('data' => t('Auto Download'), 'field' => 'auto_download'),
      'crowdsourcing' => array('data' => t('Crowdsourcing'), 'field' => 'crowdsourcing'),
      'url_alias_translation' => array('data' => t('URL Alias'), 'field' => 'url_alias'),
      'last_uploaded' => array('data' => t('Last Uploaded'), 'field' => 'last_uploaded'),
      'translation_progress_percent' => array('data' => t('Workflow Progress'), 'field' => 'translation_progress_percent'),
      'edit_settings' => array('data' => t('Edit')),
      'translation_status' => array('data' => t('Translation Status')),
      'translation_progress' => array('data' => t('Translation Progress')),
  //    'workflow' => array('data' => 'Workflow', 'field' => 'workflow'),
      'locale_progress_percent' => array('data' => t('Target Progress'), 'field' => 'locale_progress_percent'),
      'progress_updated' => array('data' => t('Progress Updated'), 'field' => 'progress_updated'),
      'last_downloaded' => array('data' => t('Last Downloaded'), 'field' => 'last_downloaded'),
      'translate_link' => array('data' => t('Translate')),
    );

    foreach ($header as $title => $data) { // Refine the source header using the selected columns
      if (array_key_exists($title, $columns) && $columns[$title]) {
        $form_state['values']['grid_header'][$title] = $data;
      }
    }

    // Initialize Query and extend paginator and tablesort
    $query = db_select('node', 'n')
      ->extend('PagerDefault')
      ->extend('TableSort');
    $query->limit($limit);
    $query->orderByHeader($form_state['values']['grid_header']);

    // Node Title and Name of Content Type (type)
    $query->fields('n', array('nid', 'title', 'type'));
      if ($source) {
        $query->condition('n.language', Lingotek::convertLingotek2Drupal($language));
      }
      $query->condition('n.nid', $nids, 'IN');

    // left joins are necessary here because some lingotek table keys might not exist
    // Lingotek Document ID
    $query->leftJoin('lingotek', 'lingo_document_id', 'lingo_document_id.nid = n.nid and lingo_document_id.lingokey = \'document_id\'');
      $query->addField('lingo_document_id', 'lingovalue', 'document_id');

    // Node Upload Status
    $query->leftJoin('lingotek', 'lingo_upload_status', 'lingo_upload_status.nid = n.nid and lingo_upload_status.lingokey = \'node_sync_status\'');
      $query->condition('lingo_upload_status.lingovalue', LingotekSync::STATUS_TARGET, '<>');
      $query->addField('lingo_upload_status', 'lingovalue', 'upload_status');

    // Auto Upload Settings
    $query->leftJoin('lingotek', 'lingo_auto_upload', 'lingo_auto_upload.nid = n.nid and lingo_auto_upload.lingokey = \'create_lingotek_document\'');
      $query->addField('lingo_auto_upload', 'lingovalue', 'auto_upload');

    // Auto Download Settings
    $query->leftJoin('lingotek', 'lingo_auto_download', 'lingo_auto_download.nid = n.nid and lingo_auto_download.lingokey = \'sync_method\'');
      $query->addField('lingo_auto_download', 'lingovalue', 'auto_download');

    // Community Translation Settings
    $query->leftJoin('lingotek', 'lingo_crowdsourcing', 'lingo_crowdsourcing.nid = n.nid and lingo_crowdsourcing.lingokey = \'allow_community_translation\'');
      $query->addField('lingo_crowdsourcing', 'lingovalue', 'crowdsourcing');

    // URL Alias Translation Settings
    $query->leftJoin('lingotek', 'lingo_url_alias', 'lingo_url_alias.nid = n.nid and lingo_url_alias.lingokey = \'url_alias_translation\'');
      $query->addField('lingo_url_alias', 'lingovalue', 'url_alias');

    // Last Uploaded Timestamp
    $query->leftJoin('lingotek', 'lingo_last_uploaded', 'lingo_last_uploaded.nid = n.nid and lingo_last_uploaded.lingokey = \'last_uploaded\'');
      $query->addField('lingo_last_uploaded', 'lingovalue', 'last_uploaded');
      
    // Translation Progress Percent (Average of all target workflow progresses)
    $query->leftJoin('lingotek', 'lingo_translation_progress_percent', 'lingo_translation_progress_percent.nid = n.nid and lingo_translation_progress_percent.lingokey = \'translation_progress\'');
      $query->addField('lingo_translation_progress_percent', 'lingovalue', 'translation_progress_percent');

    // Add translation status to the query
    $query->leftJoin('lingotek', 'lingo_translation_status', 'n.nid = lingo_translation_status.nid and lingo_translation_status.lingokey LIKE \'target_sync_status_' . $language . '\'');
      $query->addField('lingo_translation_status', 'lingovalue', 'translation_status');

    // Add workflow to the query
    $query->leftJoin('lingotek', 'lingo_workflow', 'n.nid = lingo_workflow.nid and lingo_workflow.lingokey = \'workflow_id\'');
      $query->addField('lingo_workflow', 'lingovalue', 'workflow');

    // Target Workflow Progress
    $query->leftJoin('lingotek', 'lingo_locale_progress_percent', 'n.nid = lingo_locale_progress_percent.nid and lingo_locale_progress_percent.lingokey LIKE \'target_sync_progress_' . $language . '\'');
      $query->addField('lingo_locale_progress_percent', 'lingovalue', 'locale_progress_percent');

    // Target Progress Last Updated Timestamp
    $query->leftJoin('lingotek', 'lingo_progress_updated', 'n.nid = lingo_progress_updated.nid and lingo_progress_updated.lingokey LIKE \'target_sync_last_progress_updated_' . $language . '\'');
      $query->addField('lingo_progress_updated', 'lingovalue', 'progress_updated');

    // Last Downloaded Timestamp
    $query->leftJoin('lingotek', 'lingo_last_downloaded', 'n.nid = lingo_last_downloaded.nid and lingo_last_downloaded.lingokey LIKE \'target_last_downloaded_' . $language . '\'');
      $query->addField('lingo_last_downloaded', 'lingovalue', 'last_downloaded');

    // START FILTERS
      //  Node ID
      if (isset($columns['nid']) && $columns['nid'] && isset($filters['nid']) && $filters['nid'] != '') {
        $query->condition('n.nid', $filters['nid']);
      }

      //  Title Field
      if (isset($columns['title']) && $columns['title'] && isset($filters['title']) && $filters['title'] != '') {
        $query->condition('n.title', '%' . $filters['title'] . '%', 'LIKE');
      }

      // Lingotek Document ID
      if (isset($columns['document_id']) && $columns['document_id'] && isset($filters['document_id']) && $filters['document_id'] != '') {
        if ($filters['document_id'] == 'None') {
          $query->condition('lingo_document_id.lingovalue', NULL);
        }
        else {
          $query->condition('lingo_document_id.lingovalue', $filters['document_id']);
        }
      }

      // Upload Status
      if (isset($columns['upload_status']) && $columns['upload_status'] && isset($filters['upload_status']) && !in_array('all', $filters['upload_status'])) {
        $query->condition('lingo_upload_status.lingovalue', $filters['upload_status'], 'IN');
      }

      //  Content Type
      if (isset($columns['content_type']) && $columns['content_type'] && isset($filters['content_type']) && !in_array('all', $filters['content_type'])) {
        $query->condition('n.type', $filters['content_type'], 'IN');
      }

      // Auto Upload
      if (isset($columns['auto_upload']) && $columns['auto_upload'] && isset($filters['auto_upload']) && !in_array('all', $filters['auto_upload'])) {
        $or = db_or();
        if (in_array('default', $filters['auto_upload'])) {
          $or->condition('lingo_auto_upload.lingovalue', NULL);
        }
        $or->condition('lingo_auto_upload.lingovalue', $filters['auto_upload'], 'IN');
        $query->condition($or);
      }

      // Auto Download
      if (isset($columns['auto_download']) && $columns['auto_download'] && isset($filters['auto_download']) && !in_array('all', $filters['auto_download'])) {
        $or = db_or();
        if (in_array('default', $filters['auto_download'])) {
          $or->condition('lingo_auto_download.lingovalue', NULL);
        }
        $or->condition('lingo_auto_download.lingovalue', $filters['auto_download'], 'IN');
        $query->condition($or);
      }

      // Community Translation
      if (isset($columns['crowdsourcing']) && $columns['crowdsourcing'] && isset($filters['crowdsourcing']) && !in_array('all', $filters['crowdsourcing'])) {
        $or = db_or();
        if (in_array('default', $filters['crowdsourcing'])) {
          $or->condition('lingo_community.lingovalue', $filters['crowdsourcing'], 'IN');
        }
        $or->condition('lingo_community.lingovalue', NULL);
        $query->condition($or);
      }

      // URL Alias Translation
      if (isset($columns['url_alias']) && $columns['url_alias'] && isset($filters['url_alias']) && !in_array('all', $filters['url_alias'])) {
        $or = db_or();
        if (in_array('default', $filters['url_alias'])) {
          $or->condition('lingo_url_alias.lingovalue', $filters['url_alias'], 'IN');
        }
        $or->condition('lingo_url_alias.lingovalue', NULL);
        $query->condition($or);
      }

      // Last Uploaded
      if (isset($columns['last_uploaded']) && $columns['last_uploaded'] && isset($filters['last_uploaded']) && $filters['last_uploaded'] != 'all') {
        if ($filters['last_uploaded'] == '1 day') {
          $query->condition('lingo_last_uploaded.lingovalue', strToTime($filters['last_uploaded']), '<');
        }
        elseif ($filters['last_uploaded'] == 'unknown') {
          $query->condition('lingo_last_uploaded.lingovalue', NULL);
        }
        else {
          $params = explode(' ', $filters['last_uploaded'], 2); // string formatted like '< 1 week', so explode with a limit of two gives us array(0 => '<', 1 => '1 week')
          $query->condition('lingo_last_uploaded.lingovalue', strToTime($params[1]), $params[0]);
        }
      }

      // Translation Progress Percentage
      if (isset($columns['translation_progress_percent']) && $columns['translation_progress_percent'] && isset($filters['translation_progress_percent']) && !in_array('all', $filters['translation_progress_percent'])) {
        $or = db_or();
        foreach ($filters['translation_progress_percent'] as $percent) {
          if ($percent == 0) {
            $or->condition('lingo_translation_progress_percent.lingovalue', $percent);
            $or->condition('lingo_translation_progress_percent.lingovalue', NULL);
          }
          else {
            $range = array((int)$percent - 24, (int)$percent);
            $or->where('CAST(lingo_translation_progress_percent.lingovalue as UNSIGNED) BETWEEN ' . $range[0] . '  AND ' . $range[1]);
          }
        }
        $query->condition($or);
      }

      // Translation Status
      if (isset($columns['translation_status']) && $columns['translation_status'] && isset($filters['translation_status']) && !empty($filters['translation_status']) && !in_array('all', $filters['translation_status'])) {
        $or = db_or();
        if (in_array('out_of_sync', $filters['translation_status'])) {
          $or->condition('lingo_upload_status.lingovalue', LingotekSync::STATUS_EDITED);
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_PENDING);
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_READY);
        }
        if (in_array('in_sync', $filters['translation_status'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_CURRENT);
        }
        if (in_array('disabled', $filters['translation_status'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_DISABLED);
        }
        $query->condition($or);
      }

      // Translation Progress
      if (isset($columns['translation_progress']) && $columns['translation_progress'] && isset($filters['translation_progress']) && !empty($filters['translation_progress']) && !in_array('all', $filters['translation_progress'])) {
        $or = db_or();
        if (in_array('needs_upload', $filters['translation_progress'])) {
          $or->condition('lingo_upload_status.lingovalue', LingotekSync::STATUS_EDITED);
        }
        if (in_array('in_progress', $filters['translation_progress'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_PENDING);
        }
        if (in_array('download', $filters['translation_progress'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_READY);
        }
        if (in_array('complete', $filters['translation_progress'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_CURRENT);
        }
        if (in_array('disabled', $filters['translation_progress'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_DISABLED);
        }
        $query->condition($or);
      }

      // Target Progress Percentage
      if (isset($columns['locale_progress_percent']) && $columns['locale_progress_percent'] && isset($filters['locale_progress_percent']) && !in_array('all', $filters['locale_progress_percent'])) {
        $or = db_or();
        foreach ($filters['locale_progress_percent'] as $percent) {
          if ($percent == 0) {
            $or->condition('lingo_locale_progress_percent.lingovalue', $percent);
            $or->condition('lingo_locale_progress_percent.lingovalue', NULL);
          }
          else {
            $range = array((int)$percent - 24, (int)$percent);
            $or->where('CAST(lingo_locale_progress_percent.lingovalue as UNSIGNED) BETWEEN ' . $range[0] . '  AND ' . $range[1]);
          }
        }
        $query->condition($or);
      }

      // Progress Last Updated
      if (isset($columns['progress_updated']) && $columns['progress_updated'] && isset($filters['progress_updated']) && $filters['progress_updated'] != 'all') {
        if ($filters['progress_updated'] == '1 day') {
          $query->condition('lingo_progress_updated.lingovalue', strToTime($filters['progress_updated']), '<');
        }
        elseif ($filters['progress_updated'] == 'unknown') {
          $query->condition('lingo_progress_updated.lingovalue', NULL);
        }
        else {
          $params = explode(' ', $filters['progress_updated'], 2); // string formatted like '< 1 week', so explode with a limit of two gives us array(0 => '<', 1 => '1 week')
          $query->condition('lingo_progress_updated.lingovalue', strToTime($params[1]), $params[0]);
        }
      }

      // Last Downloaded
      if (isset($columns['last_downloaded']) && $columns['last_downloaded'] && isset($filters['last_downloaded']) && $filters['last_downloaded'] != 'all') {
        if ($filters['last_downloaded'] == '1 day') {
          $query->condition('lingo_last_downloaded.lingovalue', strToTime($filters['last_downloaded']), '<');
        }
        elseif ($filters['last_downloaded'] == 'unknown') {
          $query->condition('lingo_last_downloaded.lingovalue', NULL);
        }
        else {
          $params = explode(' ', $filters['last_downloaded'], 2); // string formatted like '< 1 week', so explode with a limit of two gives us array(0 => '<', 1 => '1 week')
          $query->condition('lingo_last_downloaded.lingovalue', strToTime($params[1]), $params[0]);
        }
      }
    // END FILTERS

    // Execute the query
    $table_data_raw = $query->execute()->fetchAllAssoc('nid');

    // Set strings used for renaming some of the lingotek settings when the settings are null in the database
    $upload_default = t('Default (@default)', array('@default' => variable_get('lingotek_create_documents_by_default', 0) ? 'On' : 'Off'));
    $download_default = t('Default (@default)', array('@default' => variable_get('lingotek_sync', 1) ? 'On' : 'Off'));
    $crowdsourcing_default = t('Default (@default)', array('@default' => variable_get('lingotek_allow_community_translation', 0) ? 'On' : 'Off'));
    $url_alias_default = t('Default (@default)', array('@default' => variable_get('lingotek_url_alias_translation', 0) ? (variable_get('lingotek_url_alias_translation', 0) == 1 ? 'Translate' : 'Use Title') : 'Off'));

    // Parse returned objects and make them arrays keyed by the Node ID for clean use in The Grid.
    foreach ($table_data_raw as $row) {
      // RENAMING
      $title_cap = 70;
      if (strlen($row->title) > $title_cap) { // very long title names make The Grid look messy, so we cap them.
        $row->title = substr($row->title, 0, $title_cap) . '...';
      }
      $translation_progress = NULL;
      if (!is_null($row->upload_status)) {
        if (isset($row->upload_status)) {
          $translation_status = $row->upload_status;
          if ($row->upload_status == LingotekSync::STATUS_EDITED) {
            $row->upload_status = l('Out of Sync', 'node/' . $row->nid . '/lingotek_pm', array('attributes' => array('target' => '_blank')));
          }
          elseif ($row->upload_status == LingotekSync::STATUS_CURRENT) {
            $row->upload_status = t('In Sync');
          }
          elseif ($row->upload_status == LingotekSync::STATUS_DISABLED) {
            $row->upload_status = t('Disabled');
          }
          else {
            $row->upload_status = t('N/A');
          }
        }
        if ($translation_status == LingotekSync::STATUS_EDITED) {
            $translation_progress = l('Source Needs Upload', 'node/' . $row->nid . '/lingotek_pm', array('attributes' => array('target' => '_blank')));
        }
        elseif (!is_null($row->translation_status)) {
          if ($row->translation_status == LingotekSync::STATUS_PENDING) {
            $translation_progress = l('In Progress', 'node/' . $row->nid . '/lingotek_pm', array('attributes' => array('target' => '_blank')));
          }
          elseif ($row->translation_status == LingotekSync::STATUS_READY) {
            $translation_progress = l('Ready for Download', 'node/' . $row->nid . '/lingotek_pm', array('attributes' => array('target' => '_blank')));
          }
          elseif ($row->translation_status == LingotekSync::STATUS_CURRENT) {
            $translation_progress = t('Complete');
          }
          elseif ($row->translation_status == LingotekSync::STATUS_DISABLED) {
            $translation_progress = t('Disabled');
          }
        }
      }
      // Build the data to be output for this row
      $data = array(
        'nid' => $row->nid ?: t('??'),
        'title' => $row->nid ? l($row->title, 'node/' . $row->nid, array('attributes' => array('target' => '_blank'))) : t('N/A'),
        'document_id' => $row->document_id ?: t('N/A'),
        'upload_status' => $row->upload_status ?: t('N/A'),
        'content_type' => $row->type ?: t('??'),
        'translation_status' => $row->upload_status ?: t('N/A'),
        'translation_progress' => $translation_progress ?: t('N/A'),
        'auto_upload' => $row->auto_upload ? t('On') : (is_null($row->auto_upload) ? $upload_default : t('Off')),
        'auto_download' => $row->auto_download ? t('On') : (is_null($row->auto_download) ? $download_default : t('Off')),
        'crowdsourcing' => $row->crowdsourcing ? t('On') : (is_null($row->crowdsourcing) ? $crowdsourcing_default : t('Off')),
        'url_alias_translation' => $row->url_alias ? ($row->url_alias == 1 ? t('Translate') : t('Use Title')) : (is_null($row->url_alias) ? $url_alias_default : t('Off')),
        'last_uploaded' => $row->last_uploaded ? t('@time ago', array('@time' => lingotek_human_readable_timestamp($row->last_uploaded))) : t('Never'),
        'locale_progress_percent' => $row->locale_progress_percent ? lingotek_grid_create_progress_bar($row->locale_progress_percent) : lingotek_grid_create_progress_bar(0),
        'progress_updated' => $row->progress_updated ? t('@time ago', array('@time' => lingotek_human_readable_timestamp($row->progress_updated))) : t('Never'),
//        'workflow' => $row->workflow ?: '',
        'translation_progress_percent' => $row->translation_progress_percent ? lingotek_grid_create_progress_bar($row->translation_progress_percent) : lingotek_grid_create_progress_bar(0),
        'last_downloaded' => $row->last_downloaded ? t('@time ago', array('@time' => lingotek_human_readable_timestamp($row->last_downloaded))) : t('Never'),
        'translate_link' => $row->nid ? l('Translate', 'node/' . $row->nid . '/lingotekworkbench/' . $language, array('attributes' => array('target' => '_blank'))) : t('N/A'),
        'edit_settings' => $row->nid ? l('Edit', 'admin/settings/lingotek/grid/edit/' . $row->nid, array('attributes' => array('target' => '_blank'))) : t('N/A'),
      );

      $table_data[$row->nid] = $data;
    }
  }
  return $table_data;
}

/**
 * Helper function for creating the HTML for a progress bar
 *
 * @param int $progress
 *    Percentage complete for the progress bar
 *
 * @return string $html
 *    Raw HTML for a progress bar themed in style/base.css
 */
function lingotek_grid_create_progress_bar($progress) {
  $style = "width:" . round($progress) . "%;";
  $html = '<div class="lingotek-progress"><div class="bar" style="' . $style . '"></div><div class="percent">' . $progress . '%' . '</div></div>';
  return $html;
}

/**
 * Callback function to reset translations for multiple nodes at a time
 *
 * Node IDs are passed through the $_SESSION variable at $_SESSION['lingotek_reset_nodes']
 *
 * Returns a fully rendered html form
 */
function lingotek_reset_nodes() {

  if (isset($_SESSION['lingotek_reset_nodes'])) {
    $nids = $_SESSION['lingotek_reset_nodes'];

    return drupal_render(drupal_get_form('lingotek_node_content_reset_form', $nids, FALSE));
  }
  else {
    drupal_set_message(t('No nodes were selected'), 'error');
    return;
  }
}

/**
 * Callback function to edit settings for multiple nodes at a time
 *
 * Node IDs are passed through the $_SESSION variable at $_SESSION['lingotek_edit_nodes']
 *
 * Returns a fully rendered html form
 */
function lingotek_edit_nodes($param = NULL) {

  if (!is_null($param)) {
    $nids = $param;
  }
  elseif (isset($_SESSION['lingotek_edit_nodes'])) {
    $nids = $_SESSION['lingotek_edit_nodes'];
  }
  else {
    drupal_set_message(t('No nodes were selected'), 'error');
    $_SESSION['lingotek_edit_nodes'] = array();
    drupal_goto('admin/settings/lingotek/grid');
  }
  if (!is_array($nids)) {
    $nids = array($nids);
  }
  $form = drupal_get_form('lingotek_get_node_settings_form', $nids);
  return drupal_render($form);
}
