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

  // Container fieldset for filters
  $form['filter_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Show Only Items Where'),
    '#group' => 'translation_grid',
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  $form['filter_fieldset']['filters'] = array(
    '#type' => 'container',
  );

  $form['filter_fieldset']['filter_buttons'] = array(
    '#type' => 'container',
  );
  
  // Filter submit button
  $form['filter_fieldset']['filter_buttons']['filter_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit Filters'),
    '#submit' => array('lingotek_grid_filter_submit'),
  );

  $form['filter_fieldset']['filter_buttons']['filter_reset'] = array(
    '#type' => 'submit',
    '#value' => t('Clear Filters'),
    '#submit' => array('lingotek_grid_clear_filters'),
  );

  $form['actions_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Actions'),
    '#group' => 'translation_grid',
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  $form['customize_table_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Customize Table'),
    '#group' => 'translation_grid',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['customize_table_fieldset']['custom_columns'] = array(
    '#type' => 'container',
  );

  $form['customize_table_fieldset']['custom_buttons'] = array(
    '#type' => 'container',
  );

  $form['customize_table_fieldset']['custom_buttons']['custom_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit Customization'),
    '#submit' => array('lingotek_grid_filter_submit'),
  );

  $form['customize_table_fieldset']['custom_buttons']['custom_clear'] = array(
    '#type' => 'submit',
    '#value' => t('Reset to Defaults'),
    '#submit' => array('lingotek_grid_reset_custom'),
  );

  // Populate form_state with filter values so the query can use them
  $form_state['values']['filters'] = lingotek_grid_get_filters();
  $form_state['values']['columns'] = lingotek_grid_get_custom_columns();

  $table_data = array();
  $form_state['values']['grid_header'] = array();
  $action_options = array();

  // Get filters and table based on source or target
  if ($form_state['values']['source']) {
    $form['filter_fieldset']['filters'] += lingotek_grid_build_filters($form_state);
    $form['customize_table_fieldset']['custom_columns'] += lingotek_grid_build_custom_columns($form_state);

    $action_options = array(
      'upload' => t('Upload for Translation'),
      'download_all' => t('Download Translations for all Targets'),
      'sync' => t('Sync Translation Progress with Lingotek'),
      'reset' => t('Reset Translations'),
      'edit' => t('Edit Lingotek Node Settings'),
    );
    $table_data = lingotek_get_source_rows($form, $form_state);
  }
  else {
    $form['filter_fieldset']['filters'] += lingotek_grid_build_filters($form_state);
    $form['customize_table_fieldset']['custom_columns'] += lingotek_grid_build_custom_columns($form_state);

    $action_options = array(
      'download' => t('Download Translations'),
      'upload' => t('Upload Source for Translation'),
      'sync' => t('Sync Target Progress with Lingotek'),
    );
    $table_data = lingotek_get_target_rows($form, $form_state);
  }

  $form['actions_fieldset']['actions_select'] = array(
    '#type' => 'select',
    '#options' => $action_options,
  );

  $form['actions_fieldset']['actions_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit Action'),
    '#name' => 'actions_submit',
    '#submit' => array('lingotek_grid_action_submit'),
  );

  $form['actions_fieldset']['sync_all'] = array(
    '#type' => 'submit',
    '#value' => t('Update Status for \'In Progress\' Nodes'),
    '#name' => 'sync_all',
    '#submit' => array('lingotek_grid_action_submit'),
  );

  $last_updated = variable_get('lingotek_pending_last_updated', NULL);
  $form['actions_fieldset']['last_synced_time'] = array(
    '#markup' => t('Last run @time', array('@time' => $last_updated ? lingotek_human_readable_timestamp($last_updated) . ' ago' : 'Never')),
  );

  // div container for the table and pager
  $form['grid_container'] = array(
    '#type' => 'container',
    '#prefix' => '<div id="grid-div">',
    '#suffix' => '</div>',
  );

  if (!empty($table_data)) {
    // Count of returned results
    $page = pager_find_page();
    if (isset($GLOBALS['pager_page_array'])) {
      $page = $GLOBALS['pager_page_array'][0];
    }
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
        $nest = 'grid_filters';
      }
      elseif (strpos($key, '__custom')) {
        $add_key_to_session = TRUE;
        $nest = 'grid_custom';
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

function lingotek_grid_action_submit($form, $form_state) {
  $nids = array();
  if (isset($form_state['clicked_button']) && $form_state['clicked_button']['#name'] == 'actions_submit') {
    foreach ($form_state['values']['the_grid'] as $value) {
      if ($value != 0) {
        $nids[] = $value;
      }
    }
    $language = str_replace('s__', '', $form_state['values']['source_target_select'], $removed);
    $source = $removed !== 0;
    if (isset($form_state['values']['actions_select'])) {
      $action = $form_state['values']['actions_select'];
      if (count($nids) <= 0) {
          drupal_set_message(t('You must select a node to @action.', array('@action' => $action)), 'warning');
      }
      elseif ($action == 'upload') {
        $_SESSION['lingotek_upload_nodes'] = count($nids);
        $batch = array(
          'title' => t('Uploading Nodes To Lingotek'),
        );
        $operations = lingotek_get_sync_upload_batch_elements($nids);
        $batch['operations'] = $operations;
        $redirect = 'admin/settings/lingotek/grid';

        batch_set($batch);
        batch_process($redirect);
      }
      elseif ($action == 'download_all') {
        $_SESSION['lingotek_download_nodes'] = count($nids);
        $target_locales = lingotek_get_target_locales();
        $document_ids = LingotekSync::getDocIdsFromNodeIds($nids);
        $complete_targets = array();
        $incomplete_targets = array();

        foreach ($target_locales as $language) {
          $complete_nids = LingotekSync::getNodeIdSubsetByTargetProgress($nids, $language);
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
        $batch['operations'] += lingotek_get_sync_download_batch_elements($complete_targets, LingotekSync::STATUS_CURRENT);
        $batch['operations'] += lingotek_get_sync_download_batch_elements($incomplete_targets, LingotekSync::STATUS_PENDING);
        $redirect = 'admin/settings/lingotek/grid';

        batch_set($batch);
        batch_process($redirect);
      }
      elseif ($action == 'reset') {
        $_SESSION['lingotek_reset_nodes'] = $nids;
        drupal_goto('admin/settings/lingotek/grid/reset/');
      }
      elseif ($action == 'edit') {
        $_SESSION['lingotek_edit_nodes'] = $nids;
        drupal_goto('admin/settings/lingotek/grid/edit/');
      }
      elseif ($action == 'download') {
        $_SESSION['lingotek_download_nodes'] = count($nids);
        lingotek_download_node_by_target_batch_create($nids, $language);
      }
      elseif ($action == 'sync') {
        $_SESSION['lingotek_sync_nodes'] = count($nids);
        lingotek_update_target_progress_batch_create($nids);
      }
    }
  }
  elseif (isset($form_state['clicked_button']) && $form_state['clicked_button']['#name'] == 'sync_all') {
    $nids = LingotekSync::getNodeIdsByStatusAndTarget(LingotekSync::STATUS_PENDING);
    $_SESSION['lingotek_sync_nodes'] = count($nids);
    variable_set('lingotek_pending_last_updated', time());
    lingotek_update_target_progress_batch_create($nids);
  }
}

function lingotek_grid_build_filters($form_state) {
  $custom = $form_state['values']['columns'];
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
        LingotekSync::STATUS_LOCKED => t('Locked'),
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
        'in_sync' => t('In Sync')
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
        'complete' => t('Complete')
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
      '#default_value' => isset($_SESSION['grid_filters']['auto_download']) ? $_SESSION['grid_filters']['auto_download'] : 'all',
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
      '#default_value' => isset($_SESSION['grid_filters']['crowdsourcing']) ? $_SESSION['grid_filters']['crowdsourcing'] : 'all',
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
      '#default_value' => isset($_SESSION['grid_filters']['url_alias_translation']) ? $_SESSION['grid_filters']['url_alias_translation'] : 'all',
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
      '#default_value' => isset($_SESSION['grid_filters']['last_uploaded']) ? $_SESSION['grid_filters']['last_uploaded'] : 'all',
      '#title' => t('Last Uploaded'),
      '#options' => array(
        'all' => t('All'),
        '1 day' => t('Less than 1 day ago'),
        '< 1 week' => t('Less than 1 week ago'),
        '>= 1 week' => t('1 week or more ago'),
        'unknown' => 'Unknown'
      ),
      '#multiple' => FALSE
    ),
    'target_progress_percent' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters']['target_progress_percent']) ? $_SESSION['grid_filters']['target_progress_percent'] : 'all',
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
        'unknown' => 'Unknown'
      ),
      '#multiple' => FALSE
    ),
    'translation_progress_percent' => array(
      '#type' => 'select',
      '#default_value' => isset($_SESSION['grid_filters']['translation_progress_percent']) ? $_SESSION['grid_filters']['translation_progress_percent'] : 'all',
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
      '#default_value' => isset($_SESSION['grid_filters']['last_downloaded']) ? $_SESSION['grid_filters']['last_downloaded'] : 'all',
      '#title' => t('Last Downloaded'),
      '#options' => array(
        'all' => t('All'),
        '1 day' => t('Less than 1 day ago'),
        '< 1 week' => t('Less than 1 week ago'),
        '>= 1 week' => t('1 week or more ago'),
        'unknown' => 'Unknown'
      ),
      '#multiple' => FALSE
    ),
  );
  $filtered_filters = array();
  foreach ($filters as $filter => $innards) {
    if (array_key_exists($prefix . $filter, $custom) && $custom[$prefix . $filter]) {
      $filtered_filters[$filter] = $innards;
    }
  }

  return lingotek_grid_process_elements($filtered_filters, $prefix, $suffix);
}

function lingotek_grid_get_filters() {
  $filters = array();
  if (isset($_SESSION['grid_filters'])) {
    foreach ($_SESSION['grid_filters'] as $key => $value) {
      $filters[str_replace('__filter', '', $key)] = $value;
    }
  }
  return $filters;
}

function lingotek_grid_clear_filters() {
  if (isset($_SESSION['grid_filters'])) {
    unset($_SESSION['grid_filters']);
  }
}

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
        'target_progress_percent',
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

function lingotek_grid_build_custom_columns($form_state) {
  $prefix = $form_state['values']['source'] ? 'source_' : 'target_';
  $suffix = '__custom';
  $columns = lingotek_grid_define_columns($form_state['values']['source']);
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
    'target_progress_percent' => array(
      '#type' => 'checkbox',
      '#title' => t('Target Progress Percentage'),
      '#default_value' => isset($_SESSION['grid_custom'][$prefix . 'target_progress_percent' . $suffix]) ? $_SESSION['grid_custom'][$prefix . 'target_progress_percent' . $suffix] : in_array('target_progress_percent', $columns['defaults']),
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
  $column_elements = array_intersect_key($column_elements, $columns['columns']);

  return lingotek_grid_process_elements($column_elements, $prefix, $suffix);
}

function lingotek_grid_process_elements($elements, $prefix, $suffix) {
  $new_keys = array();
  foreach ($elements as $element => $innards) {
    $new_keys[$prefix . $element . $suffix] = $innards;
  }
  return $new_keys;
}

function lingotek_grid_get_custom_columns() {
  $filters = array();

  if (!isset($_SESSION['grid_custom'])) {
    lingotek_grid_reset_custom();
  }

  foreach ($_SESSION['grid_custom'] as $key => $value) {
    $custom[str_replace('__custom', '', $key)] = $value;
  }

  return $custom;
}

function lingotek_grid_reset_custom() {
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

function lingotek_grid_fill_custom() {

}

/**
 * Dynamic query processing function for the source language grids
 *
 * @return array $table_data
 *    Returns array of rows that match the given header.
 *    Populates The Grid
 */
function lingotek_get_source_rows($form, &$form_state) {

  $table_data = array();
  $language = Lingotek::convertLingotek2Drupal(isset($form_state['values']['source_target_select']) ? $form_state['values']['source_target_select'] : 'en_US');
  $limit = isset($form_state['values']['limit_select']) ? $form_state['values']['limit_select'] : 10;
  $filters = isset($form_state['values']['filters']) ? $form_state['values']['filters'] : array();
  $columns = isset($form_state['values']['columns']) ? $form_state['values']['columns'] : array();
  $nids = LingotekSync::GetNodeIdsBySource($language);

  if (!empty($nids)) {

    $header = array( // Define the tentative source header
      'nid' => array('data' => 'Node ID', 'field' => 'n.nid'),
      'title' => array('data' => 'Title', 'field' => 'n.title', 'sort' => 'asc'),
      'document_id' => array('data' => 'Doc ID', 'field' => 'document_id'),
      'upload_status' => array('data' => 'Upload Status', 'field' => 'upload_status'),
      'content_type' => array('data' => 'Content Type', 'field' => 'n.type'),
      'auto_upload' => array('data' => t('Auto Upload'), 'field' => 'auto_upload'),
      'auto_download' => array('data' => t('Auto Download'), 'field' => 'auto_download'),
      'crowdsourcing' => array('data' => t('Crowdsourcing'), 'field' => 'crowdsourcing'),
      'url_alias_translation' => array('data' => t('URL Alias'), 'field' => 'url_alias'),
      'last_uploaded' => array('data' => 'Last Uploaded', 'field' => 'last_uploaded'),
      'translation_progress_percent' => array('data' => 'Translation Progress', 'field' => 'translation_progress'),
      'edit_settings' => array('data' => t('Edit')),
    );

    foreach ($header as $title => $data) {
      if (array_key_exists('source_' . $title, $columns) && $columns['source_' . $title]) {
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
      $query->condition('n.language', $language);
      $query->condition('n.nid', $nids, 'IN');

    // left joins are necessary here because some lingotek table keys might not exist
    // Lingotek Document ID
    $query->leftJoin('lingotek', 'lingo_document_id', 'lingo_document_id.nid = n.nid and lingo_document_id.lingokey = \'document_id\'');
      $query->addField('lingo_document_id', 'lingovalue', 'document_id');

    // Node Upload Status
    $query->leftJoin('lingotek', 'lingo_upload_status', 'lingo_upload_status.nid = n.nid and lingo_upload_status.lingokey = \'node_sync_status\'');
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
      
    // Target Progress Average
    $query->leftJoin('lingotek', 'lingo_translation_progress', 'lingo_translation_progress.nid = n.nid and lingo_translation_progress.lingokey = \'translation_progress\'');
      $query->addField('lingo_translation_progress', 'lingovalue', 'translation_progress');

    // Filters
      //  Node ID
      if (isset($columns['source_nid']) && $columns['source_nid'] && isset($filters['source_nid']) && $filters['source_nid'] != '') {
        $query->condition('n.nid', $filters['source_nid']);
      }
      //  Title Field
      if (isset($columns['source_title']) && $columns['source_title'] && isset($filters['source_title']) && $filters['source_title'] != '') {
        $query->condition('n.title', '%' . $filters['source_title'] . '%', 'LIKE');
      }
      // Lingotek Document ID
      if (isset($columns['source_document_id']) && $columns['source_document_id'] && isset($filters['source_document_id']) && $filters['source_document_id'] != '') {
        if ($filters['source_document_id'] == 'None') {
          $query->condition('lingo_document_id.lingovalue', NULL);
        }
        else {
          $query->condition('lingo_document_id.lingovalue', $filters['source_document_id']);
        }
      }
      // Upload Status
      if (isset($columns['source_upload_status']) && $columns['source_upload_status'] && isset($filters['source_upload_status']) && !in_array('all', $filters['source_upload_status'])) {
        $query->condition('lingo_upload_status.lingovalue', $filters['source_upload_status'], 'IN');
      }
      //  Content Type
      if (isset($columns['source_content_type']) && $columns['source_content_type'] && isset($filters['source_content_type']) && !in_array('all', $filters['source_content_type'])) {
        $query->condition('n.type', $filters['source_content_type'], 'IN');
      }
      // Auto Upload
      if (isset($columns['source_auto_upload']) && $columns['source_auto_upload'] && isset($filters['source_auto_upload']) && !in_array('all', $filters['source_auto_upload'])) {
        $or = db_or();
        if (in_array('default', $filters['source_auto_upload'])) {
          $or->condition('lingo_auto_upload.lingovalue', NULL);
        }
        $or->condition('lingo_auto_upload.lingovalue', $filters['source_auto_upload'], 'IN');
        $query->condition($or);
      }
      // Auto Download
      if (isset($columns['source_auto_download']) && $columns['source_auto_download'] && isset($filters['source_auto_download']) && !in_array('all', $filters['source_auto_download'])) {
        $or = db_or();
        if (in_array('default', $filters['source_auto_download'])) {
          $or->condition('lingo_auto_download.lingovalue', NULL);
        }
        $or->condition('lingo_auto_download.lingovalue', $filters['source_auto_download'], 'IN');
        $query->condition($or);
      }
      // Community Translation
      if (isset($columns['source_crowdsourcing']) && $columns['source_crowdsourcing'] && isset($filters['source_crowdsourcing']) && !in_array('all', $filters['source_crowdsourcing'])) {
        $or = db_or();
        if (in_array('default', $filters['source_crowdsourcing'])) {
          $or->condition('lingo_community.lingovalue', $filters['source_crowdsourcing'], 'IN');
        }
        $or->condition('lingo_community.lingovalue', NULL);
        $query->condition($or);
      }
      // URL Alias Translation
      if (isset($columns['source_url_alias']) && $columns['source_url_alias'] && isset($filters['source_url_alias']) && !in_array('all', $filters['source_url_alias'])) {
        $or = db_or();
        if (in_array('default', $filters['source_url_alias'])) {
          $or->condition('lingo_url_alias.lingovalue', $filters['source_url_alias'], 'IN');
        }
        $or->condition('lingo_url_alias.lingovalue', NULL);
        $query->condition($or);
      }
      // Last Uploaded
      if (isset($columns['source_last_uploaded']) && $columns['source_last_uploaded'] && isset($filters['source_last_uploaded']) && $filters['source_last_uploaded'] != 'all') {
        if ($filters['source_last_uploaded'] == '1 day') {
          $query->condition('lingo_last_uploaded.lingovalue', strToTime($filters['source_last_uploaded']), '<');
        }
        elseif ($filters['source_last_uploaded'] == 'unknown') {
          $query->condition('lingo_last_uploaded.lingovalue', NULL);
        }
        else {
          $params = explode(' ', $filters['source_last_uploaded'], 2); // string formatted like '< 1 week', so explode with a limit of two gives us array(0 => '<', 1 => '1 week')
          $query->condition('lingo_last_uploaded.lingovalue', strToTime($params[1]), $params[0]);
        }
      }
      // Translation Progress Percentage
      if (isset($columns['source_translation_progress']) && $columns['source_translation_progress'] && isset($filters['source_translation_progress']) && !in_array('all', $filters['source_translation_progress'])) {
        $or = db_or();
        foreach ($filters['source_translation_progress'] as $percent) {
          if ($percent == 0) {
            $or->condition('lingo_translation_progress.lingovalue', $percent);
            $or->condition('lingo_translation_progress.lingovalue', NULL);
          }
          else {
            $range = array((int)$percent - 24, (int)$percent);
            $or->where('CAST(lingo_translate_progress.lingovalue as UNSIGNED) BETWEEN ' . $range[0] . '  AND ' . $range[1]);
          }
        }
        $query->condition($or);
      }

    $table_data_raw = $query->execute()->fetchAllAssoc('nid');

      $upload_default = t('Default (@default)', array('@default' => variable_get('lingotek_create_documents_by_default', 0) ? 'On' : 'Off'));
      $download_default = t('Default (@default)', array('@default' => variable_get('lingotek_sync', 1) ? 'On' : 'Off'));
      $crowdsourcing_default = t('Default (@default)', array('@default' => variable_get('lingotek_allow_community_translation', 0) ? 'On' : 'Off'));
      $url_alias_default = t('Default (@default)', array('@default' => variable_get('lingotek_url_alias_translation', 0) ? (variable_get('lingotek_url_alias_translation', 0) == 1 ? 'Translate' : 'Use Title') : 'Off'));
    // Parse returned objects and make them arrays keyed by the Node ID for clean use in The Grid.
    foreach ($table_data_raw as $row) {
      //RENAMING
      if (is_null($row->document_id)) {
        $row->document_id = 'None';
      }
      if (isset($row->upload_status)) {
        if ($row->upload_status == LingotekSync::STATUS_EDITED) {
          $row->upload_status = l('Out of Sync', '', array('attributes' => array('onclick' => 'window.open("' . '/node/' . $row->nid . '/lingotek_pm"); return false;')));
        }
        elseif ($row->upload_status == LingotekSync::STATUS_CURRENT) {
          $row->upload_status = 'In Sync';
        }
      }
      else {
        $row->upload_status = 'N/A';
      }

      $row->auto_upload = $row->auto_upload ? t('On') : (is_null($row->auto_upload) ? $upload_default : 'Off');
      $row->auto_download = $row->auto_download ? t('On') : (is_null($row->auto_download) ? $download_default : 'Off');
      $row->crowdsourcing = $row->crowdsourcing ? t('On') : (is_null($row->crowdsourcing) ? $crowdsourcing_default : 'Off');
      $row->url_alias = $row->url_alias ? ($row->url_alias == 1 ? t('Translate') : t('Use Title')) : (is_null($row->url_alias) ? $url_alias_default : 'Off');
      if (is_null($row->last_uploaded)) {
        $row->last_uploaded = 'Unknown';
      }
      else {
        $row->last_uploaded = lingotek_human_readable_timestamp($row->last_uploaded) . ' ago';
      }

      if (is_null($row->translation_progress)) {
        $row->translation_progress = 0;
      }
      $title_cap = 70;
      if (strlen($row->title) > $title_cap) { // very long title names make The Grid look messy, so we cap them.
        $row->title = substr($row->title, 0, $title_cap) . '...';
      }
      //END RENAMING
      $data = array(
        'nid' => $row->nid,
        'title' => l($row->title, '', array('attributes' => array('onclick' => 'window.open("' . '/node/' . $row->nid . '"); return false;'))),
        'document_id' => $row->document_id,
        'upload_status' => $row->upload_status,
        'content_type' => $row->type,
        'auto_upload' => $row->auto_upload,
        'auto_download' => $row->auto_download,
        'crowdsourcing' => $row->crowdsourcing,
        'url_alias_translation' => $row->url_alias,
        'last_uploaded' => $row->last_uploaded,
        'translation_progress_percent' => lingotek_grid_create_progress_bar($row->translation_progress),
        'edit_settings' => l('Edit', '', array('attributes' => array('onclick' => 'window.open("' . '/admin/settings/lingotek/grid/edit/' . $row->nid . '"); return false;'))),
      );
      $table_data[$row->nid] = $data;
    }
  }
  return $table_data;
}

/**
 * Dynamic query processing function for the target language grids
 *
 * @return array $table_data
 *    Returns array of rows that match the given header.
 *    Populates The Grid
 */
function lingotek_get_target_rows($form, &$form_state) {

  $table_data = array();
  $language = isset($form_state['values']['source_target_select']) ? $form_state['values']['source_target_select'] : NULL;
  $limit = isset($form_state['values']['limit_select']) ? $form_state['values']['limit_select'] : 10;
  $filters = isset($form_state['values']['filters']) ? $form_state['values']['filters'] : array();
  $columns = isset($form_state['values']['columns']) ? $form_state['values']['columns'] : array();

  if (!is_null($language)) {
    $nids = LingotekSync::getNodeIdsByTarget($language);
  }
  else {
    drupal_set_message(t('You must select a target language.'), 'error');
    return $table_data;
  }

  if (!empty($nids)) {
    $form_state['values']['grid_header'] = array( // Define the target header
      'nid' => array('data' => 'Node ID', 'field' => 'n.nid'),
      'title' => array('data' => 'Title', 'field' => 'title', 'sort' => 'asc'),
      'document_id' => array('data' => 'Doc ID', 'field' => 'document_id'),
      'type' => array('data' => 'Content Type', 'field' => 'type'),
  //    'workflow' => array('data' => 'Workflow', 'field' => 'workflow'),
      'translation_progress' => array('data' => 'Translation Progress'),
//      'target_progress' => array('data' => 'Target Progress', 'field' => 'target_progress'),
//      'progress_updated' => array('data' => 'Progress Updated', 'field' => 'progress_updated'),
//      'last_downloaded' => array('data' => 'Last Downloaded', 'field' => 'last_downloaded'),
      'workbench_link' => array('data' => t('Translate')),

    );

    // Initialize query and extend pager and tablesort
    $query = db_select('node', 'n')
      ->extend('PagerDefault')
      ->extend('TableSort');
    $query->limit($limit);
    $query->orderByHeader($form_state['values']['grid_header']);
    $query->fields('n', array('nid', 'title', 'type'));
    $query->condition('n.nid', $nids, 'IN');

    // Add translation status to the query
    $query->leftJoin('lingotek', 'lingo_translation_status', 'n.nid = lingo_translation_status.nid and lingo_translation_status.lingokey LIKE \'target_sync_status_' . $language . '\'');
      $query->addField('lingo_translation_status', 'lingovalue', 'translation_status');

    // Add upload status to the query
    $query->leftJoin('lingotek', 'lingo_upload_status', 'n.nid = lingo_upload_status.nid and lingo_upload_status.lingokey = \'node_sync_status\'');
//      $query->condition('lingo_upload_status.lingovalue', LingotekSync::STATUS_TARGET, '<>');
      $query->addField('lingo_upload_status', 'lingovalue', 'upload_status');

    // Add document id to the query
    $query->leftJoin('lingotek', 'lingo_document_id', 'n.nid = lingo_document_id.nid and lingo_document_id.lingokey = \'document_id\'');
      $query->addField('lingo_document_id', 'lingovalue', 'document_id');

    // Add workflow to the query
    $query->leftJoin('lingotek', 'lingo_workflow', 'n.nid = lingo_workflow.nid and lingo_workflow.lingokey = \'workflow_id\'');
      $query->addField('lingo_workflow', 'lingovalue', 'workflow');

    // Add target workflow progress to the query
    $query->leftJoin('lingotek', 'lingo_target_progress', 'n.nid = lingo_target_progress.nid and lingo_target_progress.lingokey LIKE \'target_sync_progress_' . $language . '\'');
      $query->addField('lingo_target_progress', 'lingovalue', 'target_progress');

    // Add last updated timestamp to the query
    $query->leftJoin('lingotek', 'lingo_progress_updated', 'n.nid = lingo_progress_updated.nid and lingo_progress_updated.lingokey LIKE \'target_sync_last_progress_updated_' . $language . '\'');
      $query->addField('lingo_progress_updated', 'lingovalue', 'progress_updated');

    // Add last downloaded timestamp to the query
    $query->leftJoin('lingotek', 'lingo_last_downloaded', 'n.nid = lingo_last_downloaded.nid and lingo_last_downloaded.lingokey LIKE \'target_last_downloaded_' . $language . '\'');
      $query->addField('lingo_last_downloaded', 'lingovalue', 'last_downloaded');

    // Filters
      //  Node ID
      if (isset($filters['target_nid']) && $filters['target_nid'] != '') {
        $query->condition('n.nid', $filters['target_nid']);
      }
      //  Title Field
      if (isset($filters['target_title']) && $filters['target_title'] != '') {
        $query->condition('n.title', '%' . $filters['target_title'] . '%', 'LIKE');
      }
      // Lingotek Document ID
      if (isset($filters['target_document_id']) && $filters['target_document_id'] != '') {
        if ($filters['target_document_id'] == 'None') {
          $query->condition('lingo_document_id.lingovalue', NULL);
        }
        else {
          $query->condition('lingo_document_id.lingovalue', $filters['target_document_id']);
        }
      }
      // Translation Status
      if (isset($filters['target_translation_status']) && !empty($filters['target_translation_status']) && !in_array('all', $filters['target_translation_status'])) {
        $or = db_or();
        if (in_array('out_of_sync', $filters['target_translation_status'])) {
          $or->condition('lingo_upload_status.lingovalue', LingotekSync::STATUS_EDITED);
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_PENDING);
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_READY);
        }
        if (in_array('in_sync', $filters['target_translation_status'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_CURRENT);
        }
        $query->condition($or);
      }
      // Translation Progress
      if (isset($filters['target_translation_progress']) && !empty($filters['target_translation_progress']) && !in_array('all', $filters['target_translation_progress'])) {
        $or = db_or();
        if (in_array('needs_upload', $filters['target_translation_progress'])) {
          $or->condition('lingo_upload_status.lingovalue', LingotekSync::STATUS_EDITED);
        }
        if (in_array('in_progress', $filters['target_translation_progress'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_PENDING);
        }
        if (in_array('download', $filters['target_translation_progress'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_READY);
        }
        if (in_array('complete', $filters['target_translation_progress'])) {
          $or->condition('lingo_translation_status.lingovalue', LingotekSync::STATUS_CURRENT);
        }
        $query->condition($or);
      }
      // Content Type
      if (isset($filters['target_content_type']) && !empty($filters['target_content_type']) && !in_array('all', $filters['target_content_type'])) {
        $query->condition('n.type', $filters['target_content_type'], 'IN');
      }
    /*  // Target Progress Percentage
      if (isset($filters['target_target_progress']) && !in_array('all', $filters['target_target_progress'])) {
        $or = db_or();
        foreach ($filters['target_target_progress'] as $percent) {
          if ($percent == 0) {
            $or->condition('lingo_target_progress.lingovalue', $percent);
            $or->condition('lingo_target_progress.lingovalue', NULL);
          }
          else {
            $range = array((int)$percent - 24, (int)$percent);
            $or->where('CAST(lingo_target_progress.lingovalue as UNSIGNED) BETWEEN ' . $range[0] . '  AND ' . $range[1]);
          }
        }
        $query->condition($or);
      }*/
      // Progress Last Updated
      if (isset($filters['target_progress_updated']) && $filters['target_progress_updated'] != 'all') {
        if ($filters['target_progress_updated'] == '1 day') {
          $query->condition('lingo_progress_updated.lingovalue', strToTime($filters['target_progress_updated']), '<');
        }
        elseif ($filters['target_progress_updated'] == 'unknown') {
          $query->condition('lingo_progress_updated.lingovalue', NULL);
        }
        else {
          $params = explode(' ', $filters['target_progress_updated'], 2); // string formatted like '< 1 week', so explode with a limit of two gives us array(0 => '<', 1 => '1 week')
          $query->condition('lingo_progress_updated.lingovalue', strToTime($params[1]), $params[0]);
        }
      }
      // Last Downloaded
      if (isset($filters['target_last_downloaded']) && $filters['target_last_downloaded'] != 'all') {
        if ($filters['target_last_downloaded'] == '1 day') {
          $query->condition('lingo_last_downloaded.lingovalue', strToTime($filters['target_last_downloaded']), '<');
        }
        elseif ($filters['target_last_downloaded'] == 'unknown') {
          $query->condition('lingo_last_downloaded.lingovalue', NULL);
        }
        else {
          $params = explode(' ', $filters['target_last_downloaded'], 2); // string formatted like '< 1 week', so explode with a limit of two gives us array(0 => '<', 1 => '1 week')
          $query->condition('lingo_last_downloaded.lingovalue', strToTime($params[1]), $params[0]);
        }
      }

    $query->groupBy('n.nid');

    $table_data_raw = $query->execute()->fetchAllAssoc('nid');

    foreach ($table_data_raw as $row) {
      $title_cap = 70;
      if (strlen($row->title) > $title_cap) { // very long title names make The Grid look messy, so we cap them.
        $row->title = substr($row->title, 0, $title_cap) . '...';
      }
      // Rename NULL values to make the table more user friendly
      if (is_null($row->workflow)) {
        $row->workflow = variable_get('lingotek_workflow', 'None');
      }
      if (is_null($row->target_progress)) {
        $row->target_progress = 0;
      }
      if (is_null($row->progress_updated)) {
        $row->progress_updated = 'Never';
      }
      else {
        $row->progress_updated = lingotek_human_readable_timestamp($row->progress_updated) . ' ago';
      }
      if (is_null($row->last_downloaded)) {
        $row->last_downloaded = 'Never';
      }
      else {
        $row->last_downloaded = lingotek_human_readable_timestamp($row->last_downloaded) . ' ago';
      }
      $translation_progress = 'N/A';
      if (!is_null($row->upload_status)) {
        if ($row->upload_status == LingotekSync::STATUS_EDITED) {
          $translation_progress = 'Source Needs Upload';
        }
        elseif (!is_null($row->translation_status)) {
          if ($row->translation_status == LingotekSync::STATUS_PENDING) {
            $translation_progress = l('In Progress', 'node/' . $row->nid . '/lingotek_pm', array('attributes' => array('target' => '_blank')));
          }
          elseif ($row->translation_status == LingotekSync::STATUS_READY) {
            $translation_progress = 'Ready for Download';
          }
          elseif ($row->translation_status == LingotekSync::STATUS_CURRENT) {
            $translation_progress = 'Complete';
          }
        }
      }
      $data = array(
        'nid' => $row->nid,
        'title' => l($row->title, 'node/' . $row->nid, array('attributes' => array('target' => '_blank'))),
        'document_id' => $row->document_id,
        'type' => $row->type,
  //      'workflow' => $row->workflow,
        'translation_progress' => $translation_progress,
        'target_progress' => lingotek_grid_create_progress_bar($row->target_progress),
//        'progress_updated' => $row->progress_updated,
//        'last_downloaded' => $row->last_downloaded,
        'workbench_link' => l('Translate', 'node/' . $row->nid . '/lingotekworkbench/' . $language, array('attributes' => array('target' => '_blank'))),
      );
      $table_data[$row->nid] = $data;
    }
  }
  return $table_data;
}

function lingotek_grid_create_progress_bar($target_progress) {
  $style = "width:" . round($target_progress) . "%;";
  $html = '<div class="lingotek-progress"><div class="bar" style="' . $style . '"></div><div class="percent">' . $target_progress . '%' . '</div></div>';
  return $html;
}

function lingotek_get_source_ignored_rows($form, &$form_state) {
  $table_data = array();
  $language = $form_state['values']['source_target_select'];
  $limit = $form_state['values']['limit_select'];
  $button = $form_state['values']['button'];

  $form_state['values']['grid_header'] = array( // Define the source header
    'title' => array('data' => 'Title', 'field' => 'n.title', 'sort' => 'asc'),
    'type' => array('data' => 'Content Type', 'field' => 'n.type'),
  );

  $nids = LingotekSync::getAllNodeIds();
  $query = db_select('node', 'n')
    ->extend('PagerDefault')
    ->extend('TableSort');
  $query->limit($limit);
  $query->orderByHeader($form_state['values']['grid_header']);
  $query->fields('n', array('nid', 'title', 'type'));
  $query->condition('nid', $nids, 'NOT IN');

  $table_data_raw = $query->execute()->fetchAllAssoc('nid');

  foreach ($table_data_raw as $row) {
    $title_cap = 150;
    if (strlen($row->title) > $title_cap) { // very long title names make The Grid look messy, so we cap them.
      $row->title = substr($row->title, 0, $title_cap) . '...';
    }
    $data = array(
      'title' => l($row->title, 'node/' . $row->nid),
      'type' => $row->type,
    );
    $table_data[$row->nid] = $data;
  }
  return $table_data;
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
