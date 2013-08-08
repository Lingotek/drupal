<?php

/**
 * @file
 * Bulk Grid form
 */

include_once('lingotek.module');

function lingotek_bulk_grid_form($form, $form_state) {

  $path_to_lingotek = drupal_get_path('module', 'lingotek');
  lingotek_is_module_setup();
  
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

  if (isset($_SESSION['lingotek_edit_nodes'])) {
    drupal_set_message(t('Settings changed for @number node(s).', array('@number' => count($_SESSION['lingotek_edit_nodes']))));
    unset($_SESSION['lingotek_edit_nodes']);
  }
  if (isset($_SESSION['lingotek_upload_nodes'])) {
    drupal_set_message(t('@number node(s) uploaded successfully.', array('@number' => $_SESSION['lingotek_upload_nodes'])));
    unset($_SESSION['lingotek_upload_nodes']);
  }

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
      'Content Type' => node_type_get_names(),
      'Upload Status' => array(LingotekSync::STATUS_EDITED => t('Edited'), LingotekSync::STATUS_CURRENT => t('Current'), LingotekSync::STATUS_LOCKED => t('Locked')),
      'Auto Upload' => array(1 => t('On'), 0 => t('Off'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_create_documents_by_default', 0) ? 'On' : 'Off')))),
      'Auto Download' => array(1 => t('On'), 0 => t('Off'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_sync', 0) ? 'On' : 'Off')))),
      'Crowdsourcing' => array(1 => t('On'), 0 => t('Off'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_allow_community_translation', 0) ? 'On' : 'Off')))),
      'URL Alias Translation' => array(t('None'), t('Translate'), t('Use Title'), 'default' => t('Default (@default)', array('@default' => (variable_get('lingotek_url_alias_translation', 0) == 2 ? 'Use Title' : variable_get('lingotek_url_alias_translation', 0) ? 'Translate' : 'None')))),
    );
    $action_options = array(
      'upload' => t('Upload for Translation'),
      'edit' => t('Edit Lingotek Node Settings'),
    );

    $table_data = lingotek_get_source_rows($form, $form_state);
  }
  else {
    $form['filter_fieldset']['filters']['target_title__filter'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($_SESSION['grid_filters']['target_title__filter']) ? $_SESSION['grid_filters']['target_title__filter'] : '',
      '#title' => t('Title Includes'),
      '#size' => 30,
    );
    $filters = array(
      'Content Type' => node_type_get_names(),
//      'Workflow' =>
      'Overall Progress' => array(0 => t('0 Percent'), 25 => t('Between 1 and 25 Percent'), 50 => t('Between 26 and 50 Percent'), 75 => t('Between 51 and 75 Percent'), 100 => t('Between 76 and 100 Percent')),
      'Download Status' => array(LingotekSync::STATUS_PENDING => t('Pending'), LingotekSync::STATUS_CURRENT => t('Current'), LingotekSync::STATUS_LOCKED => t('Locked')),
    );
    $action_options = array(
      'download' => t('Download Translation'),
      'mark_current' => t('Mark as CURRENT'),
      'mark_pending' => t('Mark as PENDING'),
      'sync' => t('Sync Translation Progress with Lingotek'),
    );
    //$docs = LingotekSync::getDocIdsByTarget($selected);
    //$docs_source = LingotekSync::getDocIdsBySource($selected);
    //$docs_status = LingotekSync::getDocIdsByStatus(LingotekSync::insert status here);
    //$docs = array_intersect($docs_source, $docs_status);
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

/*  $form['grid_container']['link'] = array(
   // '#markup' => '<a id="modal-link" href="javascript:TINY.box.show({url:\'/admin/settings/lingotek/grid/edit/1\',width:1200,height:823})">modal </a>',
    '#markup' => '<a id="modal-link" href="/admin/settings/lingotek/grid/edit/1">modal </a>',
  );
*/
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
    elseif ($action == 'edit') {
      $_SESSION['lingotek_edit_nodes'] = $nids;
      drupal_goto('admin/settings/lingotek/grid/edit/');
    }
  }
}

function lingotek_grid_clear_filters() {
  if (isset($_SESSION['grid_filters'])) {
    unset($_SESSION['grid_filters']);
  }
}

function lingotek_grid_get_filters() {
  $filters = array();
  if (isset($_SESSION['grid_filters'])) {
    foreach ($_SESSION['grid_filters'] as $key => $value) {
  //    if (strpos($key, '__filter')) {
      $filters[str_replace('__filter', '', $key)] = $value;
  //    }
    }
  }
  return $filters;
}

function lingotek_grid_create_submit_button($button) {
  $exploditron = explode('_', $button);
  $imploditron = array();
  foreach ($exploditron as $exploditronite) {
    $imploditron[] = ucfirst($exploditronite);
  }
  $machine_name = implode(' ', $imploditron);
  $form_element = array(
    '#type' => 'submit',
    '#name' => $button,
    '#value' => t($machine_name),
    '#submit' => array('lingotek_grid_header_submit'),
  );
  return $form_element;
}

function lingotek_grid_create_filters($filter_names, $source_or_target) {
  $filters = array();
  foreach ($filter_names as $filter_name => $filter_options) {
    $you_will_be_assimilated = str_replace(' ', '_', $filter_name);
    $you_will_be_assimilated = strtolower($you_will_be_assimilated);
    $you_will_be_assimilated = ($source_or_target ? 'source' : 'target') . '_' . $you_will_be_assimilated . '__filter';
    $filters[$you_will_be_assimilated] = lingotek_grid_create_filter($you_will_be_assimilated, $filter_options, $filter_name);
  }
  return $filters;
}

function lingotek_grid_create_filter($machine_name, $options, $title) {
  $new_options = array(
    'all' => 'All',
  );
  $new_options = $new_options + $options;
  $default = 'all';
  if (isset($_SESSION['grid_filters'][$machine_name])) {
    $default = $_SESSION['grid_filters'][$machine_name];
  }
  $filter = array(
    '#type' => 'select',
    '#title' => $title,
    '#options' => $new_options,
    '#default_value' => $default,
    '#multiple' => TRUE,
  );
  return $filter;
}

function lingotek_get_source_rows($form, &$form_state) {

  $table_data = array();
  $language = Lingotek::convertLingotek2Drupal($form_state['values']['source_target_select']);
  $limit = $form_state['values']['limit_select'];
  $filters = $form_state['values']['filters'];

dpm($filters);
  $nids = LingotekSync::GetNodeIdsBySource($language);
//  $docs = LingotekSync::getDocIdsBySource(Lingotek::convertDrupal2Lingotek($language));
//  $nids = LingotekSync::getNodeIdsFromDocIds($docs);

  // Get global defaults and string replacements for 0, 1, 2 and NULL
  $auto_upload_default = variable_get('lingotek_create_documents_by_default', 0);
  $auto_download_default = variable_get('lingotek_sync', 0);
  $url_alias_default = variable_get('lingotek_url_alias_translation', 0);
  $aUpd_string = $auto_upload_default ? 'Auto' : 'Manual';
  $aDownd_string = $auto_download_default ? 'Auto' : 'Manual';
  $uAliasd_string = $url_alias_default > 0 ? ($url_alias_default == 1 ? 'Translate' : 'Use Title') : 'None';

  $form_state['values']['grid_header'] = array( // Define the source header
    'title' => array('data' => 'Title', 'field' => 'n.title', 'sort' => 'asc'),
    'document_id' => array('data' => 'Doc ID', 'field' => 'document_id'),
    'type' => array('data' => 'Content Type', 'field' => 'n.type'),
//    'targets' => array('data' => 'Targets', 'field' => 'targets'),
//    'auto_upload' => array('data' => 'Auto Upload', 'field' => 'auto_upload'),
//    'auto_download' => array('data' => 'Auto Download', 'field' => 'auto_download'),
//    'url_alias' => array('data' => 'URL Alias', 'field' => 'url_alias'),
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
//    $query->condition('n.language', $language);
    $query->condition('n.nid', $nids, 'IN');

  // Number of Target Languages  // Not only do we not need this, but it doesn't let items without target statuses show up in the table
/*  $query->leftJoin('lingotek', 'lingo_targets', 'lingo_targets.nid = n.nid');
    $query->addExpression('COUNT(lingo_targets.lingokey)', 'targets');
    $query->condition('lingo_targets.lingokey', 'target_sync_status_%', 'LIKE');*/

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

  // Parse returned objects and make them arrays keyed by the Entity ID for clean use in The Grid.
  foreach ($table_data_raw as $row) { 
    // RENAMING - auto upload and download -> NULL set to 'Default ([global])', 0 set to 'Manual', 1 set to 'Auto'. // These items are no longer in the table, so they don't need to be renamed.
    //$row->auto_upload = $row->auto_upload == NULL ? 'Default (' . $aUpd_string . ')' : ($row->auto_upload == 1 ? 'Auto' : 'Manual');
    //$row->auto_download = $row->auto_download == NULL ? 'Default (' . $aDownd_string . ')' : ($row->auto_download == 1 ? 'Auto' : 'Manual');
    // RENAMING - url aliases -> NULL set to 'Default ([global])', 0 set to 'None', 1 set to 'Translate', 2 set to 'Use Title'.
    //if ($row->url_alias) {
    //  $row->url_alias = $row->url_alias == 1 ? 'Translate' : 'Use Title';
    //}
    //elseif (is_null($row->url_alias)) { // If this is null and the default is set, follow same rule.
    //  $row->url_alias = 'Default (' . $uAliasd_string . ')';
    //}
    //else { // It is 0 and default is 0 (or not set), no url alias translation
    //  $row->url_alias = 'None';
    //}
    //$row->upload_status = $row->upload_status == LingotekSync::STATUS_EDITED ? "Needs Upload" : ($row->upload_status == LingotekSync::STATUS_CURRENT ? "Up to Date" : "Locked");
    if (is_null($row->document_id)) {
      $row->document_id = 'None';
    }
    $title_cap = 100;
    if (strlen($row->title) > $title_cap) { // very long title names make The Grid look messy, so we cap them.
      $row->title = substr($row->title, 0, $title_cap) . '...';
    }
    $data = array(
      'title' => l($row->title, 'node/' . $row->nid),
      'document_id' => $row->document_id,
      'type' => $row->type,
//      'targets' => $row->targets,
//      'auto_upload' => $row->auto_upload,
//      'auto_download' => $row->auto_download,
//      'url_alias' => $row->url_alias,
      'upload_status' => $row->upload_status,
    );
    $table_data[$row->nid] = $data;
  }

  return $table_data;

}

function lingotek_get_target_rows($form, &$form_state) {
  $table_data = array();
  $language = $form_state['values']['source_target_select'];
  $limit = $form_state['values']['limit_select'];
  $filters = $form_state['values']['filters'];

  $form_state['values']['grid_header'] = array(
    'title' => array('data' => 'Title', 'field' => 'title', 'sort' => 'asc'),
    'type' => array('data' => 'Content Type', 'field' => 'type'),
    'workflow' => array('data' => 'Workflow', 'field' => 'workflow'),
    'overall_progress' => array('data' => 'Overall Progress', 'field' => 'overall_progress'),
  );

  $nids = LingotekSync::getNodeIdsByTarget($language);
  $query = db_select('node', 'n')
    ->extend('PagerDefault')
    ->extend('TableSort');
  $query->limit($limit);
  $query->orderByHeader($form_state['values']['grid_header']);
  $query->fields('n', array('nid', 'title', 'type'));
  $query->condition('n.nid', $nids, 'IN');

  $query->leftJoin('lingotek', 'lingo_workflow', 'n.nid = lingo_workflow.nid and lingo_workflow.lingokey = \'workflow_id\'');
    $query->addField('lingo_workflow', 'lingovalue', 'workflow');

  $query->leftJoin('lingotek', 'lingo_overall_progress', 'n.nid = lingo_overall_progress.nid and lingo_overall_progress.lingokey LIKE \'target_sync_progress_' . $language . '\'');
    $query->addField('lingo_overall_progress', 'lingovalue', 'overall_progress');

  $query->leftJoin('lingotek', 'lingo_download_status', 'n.nid = lingo_download_status.nid and lingo_download_status.lingokey LIKE \'target_sync_status_' . $language . '\'');
    $query->addField('lingo_download_status', 'lingovalue', 'download_status');

  // Filters
    //  Title Field
    if (isset($filters['target_title']) && $filters['target_title'] != '') {
      $query->condition('n.title', '%' . $filters['target_title'] . '%', 'LIKE');
    }
    // Content Type
    if (isset($filters['target_content_type']) && !in_array('all', $filters['target_content_type'])) {
      $query->condition('n.type', $filters['target_content_type'], 'IN');
    }
    // Overall Progress Percentage
    if (isset($filters['target_overall_progress']) && !in_array('all', $filters['target_overall_progress'])) {
      $or = db_or();
      foreach ($filters['target_overall_progress'] as $percent) {
        if ($percent == 0) {
          $or->condition('lingo_overall_progress.lingovalue', $percent);
        }
        else {
          $range = array((int)$percent - 24, (int)$percent);
          $or->where('CAST(lingo_overall_progress.lingovalue as UNSIGNED) BETWEEN ' . $range[0] . '  AND ' . $range[1]);
        }
      }
      $query->condition($or);
    }
    // Download Status
    if (isset($filters['target_download_status']) && !in_array('all', $filters['target_download_status'])) {
      $query->condition('lingo_download_status.lingovalue', $filters['target_download_status'], 'IN');
    }


  $query->groupBy('n.nid');

  $table_data_raw = $query->execute();
  $table_data_raw = $table_data_raw->fetchAllAssoc('nid');

  foreach ($table_data_raw as $row) {
    $title_cap = 150;
    if (strlen($row->title) > $title_cap) { // very long title names make The Grid look messy, so we cap them.
      $row->title = substr($row->title, 0, $title_cap) . '...';
    }
    if ($row->workflow == NULL) {
      $row->workflow = variable_get('lingotek_workflow', 'None');
    }
    $data = array(
      'title' => l($row->title, 'node/' . $row->nid),
      'type' => $row->type,
      'workflow' => $row->workflow,
      'overall_progress' => $row->overall_progress,
    );
    $table_data[$row->nid] = $data;
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
/*      'targets' => array('data' => 'Targets', 'field' => 'targets'),
    'auto_upload' => array('data' => 'Auto Upload', 'field' => 'auto_upload'),
    'auto_download' => array('data' => 'Auto Download', 'field' => 'auto_download'),
    'url_alias' => array('data' => 'URL Alias', 'field' => 'url_alias'),*/
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

function lingotek_edit_nodes() {

  if (isset($_SESSION['lingotek_edit_nodes'])) {
    $nids = $_SESSION['lingotek_edit_nodes'];

    if (!is_array($nids)) {
      $nids = array($nids);
    }

    return drupal_render(drupal_get_form('lingotek_get_node_settings_form', $nids));
  }
  else {
    drupal_set_message(t('No nodes were selected'), 'error');
    return;
  }
}

function lingotek_replace_grid_callback($form, $form_state) {
    return $form['grid_container'];
}
