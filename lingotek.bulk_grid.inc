<?php

/**
 * @file
 * Bulk Grid form
 */

include_once('lingotek.module');

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
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
    '#attached' => array(
      'js' => array( // We get php errors when TableSort and AJAX are combined (see https://drupal.org/node/1833746).
                     // So we are faking it with a hidden submit button and some jQuery.
        $path_to_lingotek . '/js/lingotek.bulk_grid.js',
        $path_to_lingotek . '/js/lingotek.form.js',
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
    $target_languages_base[$target_raw->lingotek_locale] = $target_raw->name;
    foreach ($target_raw as $key => $value) {
      $target_languages[$target_raw->lingotek_locale][$key] = $value;
    }
  }
  foreach ($source_languages_base as $source_language_base) {
    if (isset($target_languages[$source_language_base])) {
      // s__ added to source languages to distinguish between source and target (i.e. if English is both a source and a target)
      $source_languages_base['s__' . $source_language_base] = $target_languages[$source_language_base]['name'];
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
    '#submit' => array('lingotek_grid_header_submit'),
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
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
  );

  $form['filter_fieldset']['filters'] = array(
    '#type' => 'container',
    '#group' => 'filters',
    '#collapsible' => FALSE,
  );

  $form['filter_fieldset']['filter_buttons'] = array(
    '#type' => 'container',
    '#group' => 'buttons',
    '#collapsible' => FALSE,
  );
  
  // Filter submit button
  $form['filter_fieldset']['filter_buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => 'Submit Filters',
    '#submit' => array('lingotek_grid_header_submit'),
  );

  $form['filter_fieldset']['filter_buttons']['reset'] = array(
    '#type' => 'submit',
    '#value' => 'Clear Filters',
    '#submit' => array('lingotek_grid_clear_filters'),
  );

  $form['actions_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Actions'),
    '#group' => 'translation_grid',
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
  );

  // Populate form_state with filter values so the query can use them
  $form_state['values']['filters'] = lingotek_grid_get_filters();

  $table_data = array();
  $form_state['values']['grid_header'] = array();
  $action_options = array();

  // Get values based on source or target
  if ($form_state['values']['source']) {
    $form['filter_fieldset']['filters']['source_nid__filter'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters']['source_nid__filter']) ? $_SESSION['grid_filters']['source_nid__filter'] : '',
      '#title' => t('Node ID is'),
      '#size' => 8,
    );
    $form['filter_fieldset']['filters']['source_title__filter'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters']['source_title__filter']) ? $_SESSION['grid_filters']['source_title__filter'] : '',
      '#title' => t('Title Includes'),
      '#size' => 30,
    );
    $form['filter_fieldset']['filters']['source_document_id__filter'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters']['source_document_id__filter']) ? $_SESSION['grid_filters']['source_document_id__filter'] : '',
      '#title' => t('Doc ID is'),
      '#size' => 10,
    );
    $filters = array(
      'Content Type' => array('options' => node_type_get_names(), 'multiple' => TRUE),
      'Upload Status' => array('options' => array(LingotekSync::STATUS_EDITED => t('Edited'), LingotekSync::STATUS_CURRENT => t('Current'), LingotekSync::STATUS_LOCKED => t('Locked')), 'multiple' => TRUE),
      'Auto Upload' => array('options' => array(1 => t('On'), 0 => t('Off'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_create_documents_by_default', 0) ? 'On' : 'Off')))), 'multiple' => TRUE),
      'Auto Download' => array('options' => array(1 => t('On'), 0 => t('Off'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_sync', 0) ? 'On' : 'Off')))), 'multiple' => TRUE),
      'Crowdsourcing' => array('options' => array(1 => t('On'), 0 => t('Off'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_allow_community_translation', 0) ? 'On' : 'Off')))), 'multiple' => TRUE),
      'URL Alias Translation' => array('options' => array(t('None'), t('Translate'), t('Use Title'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_url_alias_translation', 0) == 2 ? 'Use Title' : variable_get('lingotek_url_alias_translation', 0) ? 'Translate' : 'None')))), 'multiple' => TRUE),
    );
    $action_options = array(
      'upload' => t('Upload for Translation'),
      'download_all' => t('Download Translations for all Targets'),
      'reset' => t('Reset Translations'),
      'edit' => t('Edit Lingotek Node Settings'),
    );

    $table_data = lingotek_get_source_rows($form, $form_state);
  }
  else {
    $form['filter_fieldset']['filters']['target_nid__filter'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters']['target_nid__filter']) ? $_SESSION['grid_filters']['target_nid__filter'] : '',
      '#title' => t('Node ID is'),
      '#size' => 8,
    );
    $form['filter_fieldset']['filters']['target_title__filter'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters']['target_title__filter']) ? $_SESSION['grid_filters']['target_title__filter'] : '',
      '#title' => t('Title Includes'),
      '#size' => 30,
    );
    $form['filter_fieldset']['filters']['target_document_id__filter'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters']['target_document_id__filter']) ? $_SESSION['grid_filters']['target_document_id__filter'] : '',
      '#title' => t('Doc ID is'),
      '#size' => 10,
    );
    $filters = array(
      'Content Type' => array('options' => node_type_get_names(), 'multiple' => TRUE),
//      'Workflow' =>
      'Target Progress' => array('options' => array(0 => t('0 Percent'), 25 => t('Between 1 and 25 Percent'), 50 => t('Between 26 and 50 Percent'), 75 => t('Between 51 and 75 Percent'), 100 => t('Between 76 and 100 Percent')), 'multiple' => TRUE),
      'Download Status' => array('options' => array(LingotekSync::STATUS_PENDING => t('Out of Sync'), LingotekSync::STATUS_CURRENT => t('In Sync'), LingotekSync::STATUS_LOCKED => t('Locked')), 'multiple' => TRUE),
      'Progress Updated' => array('options' => array('1 day' => t('Less than 1 day ago'), '< 1 week' => t('Less than 1 week ago'), '>= 1 week' => t('1 week or more ago'), 'unknown' => 'Unknown'), 'multiple' => FALSE),
      'Last Downloaded' => array('options' => array('1 day' => t('Less than 1 day ago'), '< 1 week' => t('Less than 1 week ago'), '>= 1 week' => t('1 week or more ago'), 'unknown' => 'Unknown'), 'multiple' => FALSE),
    );
    $action_options = array(
      'sync' => t('Sync Target Progress with Lingotek'),
      'download' => t('Download Translations'),
    );
    $table_data = lingotek_get_target_rows($form, $form_state);
  }

  $form['filter_fieldset']['filters'] = array_merge($form['filter_fieldset']['filters'], lingotek_grid_create_filters($filters, $form_state['values']['source']));

  $form['actions_fieldset']['actions_select'] = array(
    '#type' => 'select',
    '#options' => $action_options,
  );

  $form['actions_fieldset']['actions_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit Action'),
    '#submit' => array('lingotek_grid_action_submit'),
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

function lingotek_grid_header_submit($form, $form_state) {
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

function lingotek_grid_create_filters($filter_names, $source_or_target) {
  $filters = array();
  foreach ($filter_names as $filter_name => $filter_info) {
    $you_will_be_assimilated = str_replace(' ', '_', $filter_name);
    $you_will_be_assimilated = strtolower($you_will_be_assimilated);
    $you_will_be_assimilated = ($source_or_target ? 'source' : 'target') . '_' . $you_will_be_assimilated . '__filter';
    $filters[$you_will_be_assimilated] = lingotek_grid_create_filter($you_will_be_assimilated, $filter_info, $filter_name);
  }
  return $filters;
}

function lingotek_grid_create_filter($machine_name, $info, $title) {
  $new_options = array(
    'all' => 'All',
  );
  $new_options = $new_options + $info['options'];
  $default = 'all';
  if (isset($_SESSION['grid_filters'][$machine_name])) {
    $default = $_SESSION['grid_filters'][$machine_name];
  }
  $filter = array(
    '#type' => 'select',
    '#title' => $title,
    '#options' => $new_options,
    '#default_value' => $default,
    '#multiple' => $info['multiple'],
  );
  return $filter;
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
  $nids = LingotekSync::GetNodeIdsBySource($language);

  if (!empty($nids)) {

    $form_state['values']['grid_header'] = array( // Define the source header
      'nid' => array('data' => 'Node ID', 'field' => 'n.nid'),
      'title' => array('data' => 'Title', 'field' => 'n.title', 'sort' => 'asc'),
      'document_id' => array('data' => 'Doc ID', 'field' => 'document_id'),
      'type' => array('data' => 'Content Type', 'field' => 'n.type'),
      'upload_status' => array('data' => 'Upload Status', 'field' => 'upload_status'),
    );

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

    // Auto Upload Settings
    $query->leftJoin('lingotek', 'lingo_auto_upload', 'lingo_auto_upload.nid = n.nid and lingo_auto_upload.lingokey = \'create_lingotek_document\'');
      $query->addField('lingo_auto_upload', 'lingovalue', 'auto_upload');

    // Auto Download Settings
    $query->leftJoin('lingotek', 'lingo_auto_download', 'lingo_auto_download.nid = n.nid and lingo_auto_download.lingokey = \'sync_method\'');
      $query->addField('lingo_auto_download', 'lingovalue', 'auto_download');

    // Community Translation Settings
    $query->leftJoin('lingotek', 'lingo_community', 'lingo_community.nid = n.nid and lingo_community.lingokey = \'allow_community_translation\'');
      $query->addField('lingo_community', 'lingovalue', 'community');

    // URL Alias Translation Settings
    $query->leftJoin('lingotek', 'lingo_url_alias', 'lingo_url_alias.nid = n.nid and lingo_url_alias.lingokey = \'url_alias_translation\'');
      $query->addField('lingo_url_alias', 'lingovalue', 'url_alias');

    // Node Upload Status
    $query->leftJoin('lingotek', 'lingo_upload_status', 'lingo_upload_status.nid = n.nid and lingo_upload_status.lingokey = \'node_sync_status\'');
      $query->addField('lingo_upload_status', 'lingovalue', 'upload_status');

    // Filters
      //  Node ID
      if (isset($filters['source_nid']) && $filters['source_nid'] != '') {
        $query->condition('n.nid', $filters['source_nid']);
      }
      //  Title Field
      if (isset($filters['source_title']) && $filters['source_title'] != '') {
        $query->condition('n.title', '%' . $filters['source_title'] . '%', 'LIKE');
      }
      // Lingotek Document ID
      if (isset($filters['source_document_id']) && $filters['source_document_id'] != '') {
        if ($filters['source_document_id'] == 'None') {
          $query->condition('lingo_document_id.lingovalue', NULL);
        }
        else {
          $query->condition('lingo_document_id.lingovalue', $filters['source_document_id']);
        }
      }
      //  Content Type
      if (isset($filters['source_content_type']) && !in_array('all', $filters['source_content_type'])) {
        $query->condition('n.type', $filters['source_content_type'], 'IN');
      }
      // Upload Status
      if (isset($filters['source_upload_status']) && !in_array('all', $filters['source_upload_status'])) {
        $query->condition('lingo_upload_status.lingovalue', $filters['source_upload_status'], 'IN');
      }
      // Auto Upload
      if (isset($filters['source_auto_upload']) && !in_array('all', $filters['source_auto_upload'])) {
        $or = db_or();
        if (in_array('default', $filters['source_auto_upload'])) {
          $or->condition('lingo_auto_upload.lingovalue', NULL);
        }
        $or->condition('lingo_auto_upload.lingovalue', $filters['source_auto_upload'], 'IN');
        $query->condition($or);
      }
      // Auto Download
      if (isset($filters['source_auto_download']) && !in_array('all', $filters['source_auto_download'])) {
        $or = db_or();
        if (in_array('default', $filters['source_auto_download'])) {
          $or->condition('lingo_auto_download.lingovalue', NULL);
        }
        $or->condition('lingo_auto_download.lingovalue', $filters['source_auto_download'], 'IN');
        $query->condition($or);
      }
      // Community Translation
      if (isset($filters['source_community_translation']) && !in_array('all', $filters['source_community_translation'])) {
        $or = db_or();
        if (in_array('default', $filters['source_community_translation'])) {
          $or->condition('lingo_community.lingovalue', $filters['source_community_translation'], 'IN');
        }
        $or->condition('lingo_community.lingovalue', NULL);
        $query->condition($or);
      }
      // URL Alias Translation
      if (isset($filters['source_url_alias']) && !in_array('all', $filters['source_url_alias'])) {
        $or = db_or();
        if (in_array('default', $filters['source_url_alias'])) {
          $or->condition('lingo_url_alias.lingovalue', $filters['source_url_alias'], 'IN');
        }
        $or->condition('lingo_url_alias.lingovalue', NULL);
        $query->condition($or);
      }

    $query->groupBy('n.nid');
    $table_data_raw = $query->execute()->fetchAllAssoc('nid');

    // Parse returned objects and make them arrays keyed by the Node ID for clean use in The Grid.
    foreach ($table_data_raw as $row) {
      if (is_null($row->document_id)) {
        $row->document_id = 'None';
      }
      $title_cap = 100;
      if (strlen($row->title) > $title_cap) { // very long title names make The Grid look messy, so we cap them.
        $row->title = substr($row->title, 0, $title_cap) . '...';
      }
      $data = array(
        'nid' => $row->nid,
        'title' => l($row->title, 'node/' . $row->nid),
        'document_id' => $row->document_id,
        'type' => $row->type,
        'upload_status' => $row->upload_status,
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
      'target_progress' => array('data' => 'Target Progress', 'field' => 'target_progress'),
      'progress_updated' => array('data' => 'Progress Updated', 'field' => 'progress_updated'),
      'last_downloaded' => array('data' => 'Last Downloaded', 'field' => 'last_downloaded'),
    );

    // Initialize query and extend pager and tablesort
    $query = db_select('node', 'n')
      ->extend('PagerDefault')
      ->extend('TableSort');
    $query->limit($limit);
    $query->orderByHeader($form_state['values']['grid_header']);
    $query->fields('n', array('nid', 'title', 'type'));
    $query->condition('n.nid', $nids, 'IN');

    // Add document id to the query
    $query->leftJoin('lingotek', 'lingo_document_id', 'n.nid = lingo_document_id.nid and lingo_document_id.lingokey = \'document_id\'');
      $query->addField('lingo_document_id', 'lingovalue', 'document_id');

    // Add workflow to the query
    $query->leftJoin('lingotek', 'lingo_workflow', 'n.nid = lingo_workflow.nid and lingo_workflow.lingokey = \'workflow_id\'');
      $query->addField('lingo_workflow', 'lingovalue', 'workflow');

    // Add target workflow progress to the query
    $query->leftJoin('lingotek', 'lingo_target_progress', 'n.nid = lingo_target_progress.nid and lingo_target_progress.lingokey LIKE \'target_sync_progress_' . $language . '\'');
      $query->addField('lingo_target_progress', 'lingovalue', 'target_progress');

    // Add download status to the query
    $query->leftJoin('lingotek', 'lingo_download_status', 'n.nid = lingo_download_status.nid and lingo_download_status.lingokey LIKE \'target_sync_status_' . $language . '\'');
      $query->addField('lingo_download_status', 'lingovalue', 'download_status');

    // Add last updated timestamp to the query
    $query->leftJoin('lingotek', 'lingo_progress_updated', 'n.nid = lingo_progress_updated.nid and lingo_progress_updated.lingokey LIKE \'target_sync_progress_updated_' . $language . '\'');
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
      // Content Type
      if (isset($filters['target_content_type']) && !in_array('all', $filters['target_content_type'])) {
        $query->condition('n.type', $filters['target_content_type'], 'IN');
      }
      // Target Progress Percentage
      if (isset($filters['target_target_progress']) && !in_array('all', $filters['target_target_progress'])) {
        $or = db_or();
        foreach ($filters['target_target_progress'] as $percent) {
          if ($percent == 0) {
            $or->condition('lingo_target_progress.lingovalue', $percent);
          }
          else {
            $range = array((int)$percent - 24, (int)$percent);
            $or->where('CAST(lingo_target_progress.lingovalue as UNSIGNED) BETWEEN ' . $range[0] . '  AND ' . $range[1]);
          }
        }
        $query->condition($or);
      }
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
      // Download Status
      if (isset($filters['target_download_status']) && !in_array('all', $filters['target_download_status'])) {
        $query->condition('lingo_download_status.lingovalue', $filters['target_download_status'], 'IN');
      }

    $query->groupBy('n.nid');

    $table_data_raw = $query->execute()->fetchAllAssoc('nid');

    foreach ($table_data_raw as $row) {
      $title_cap = 100;
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
        $row->progress_updated = 'Unknown';
      }
      else {
        $row->progress_updated = lingotek_human_readable_timestamp($row->progress_updated) . ' ago';
      }
      if (is_null($row->last_downloaded)) {
        $row->last_downloaded = 'Unknown';
      }
      else {
        $row->last_downloaded = lingotek_human_readable_timestamp($row->last_downloaded) . ' ago';
      }
      $style = "width:" . round($row->target_progress) . "%;";
      $data = array(
        'nid' => $row->nid,
        'title' => l($row->title, 'node/' . $row->nid),
        'document_id' => $row->document_id,
        'type' => $row->type,
  //      'workflow' => $row->workflow,
        'target_progress' => '<div class="progress" title="' . 'Last checked ' . $row->progress_updated . '"><div class="bar" style="' . $style . '"></div><div class="percent">' . $row->target_progress . '%' . '</div></div>',
        'progress_updated' => $row->progress_updated,
        'last_downloaded' => $row->last_downloaded,
      );
      $table_data[$row->nid] = $data;
    }
  }
  return $table_data;
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
function lingotek_edit_nodes() {

  if (isset($_SESSION['lingotek_edit_nodes'])) {
    $nids = $_SESSION['lingotek_edit_nodes'];

    if (!is_array($nids)) {
      $nids = array($nids);
    }
    $form = drupal_get_form('lingotek_get_node_settings_form', $nids);
    return drupal_render($form);
  }
  else {
    drupal_set_message(t('No nodes were selected'), 'error');
    return;
  }
}
