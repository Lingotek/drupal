<?php
// All the stuff for the new config menu goes in here
function lingotek_config_filter_inline_submit($form, $form_state) {
  unset($_SESSION['grid_filters']['textgroup']);
  $_SESSION['grid_filters']['textgroup'] = $form_state['values']['search_type'];
  if (!empty($form_state['values']['search'])) {
    $_SESSION['grid_filters']['search'] = $form_state['values']['search'];
  }
  else {
    unset($_SESSION['grid_filters']['search']);
  }

  if (isset($form_state['values']['limit_select'])) {
    $_SESSION['limit_select'] = $form_state['values']['limit_select'];
  }
}

function lingotek_config_header() {
  $header = array(// Define the tentative source header
    'lid' => array('data' => t('ID'), 'field' => 'lid', 'width' => '2%'),
    'set_name' => array('data' => t('Config Set Name'), 'field' => 'set_id', 'width' => '15%'),
    'source' => array('data' => t('Source'), 'field' => 'source', 'width' => '25%'),
    'source_uploaded' => array('data' => t('Source Uploaded')),
    'translations' => array('data' => t('Translations'), 'field' => 'current_target_count'),
    'location' => array('data' => t('Location'), 'field' => 'location'),
    'context' => array('data' => t('Context'), 'field' => 'context'),
    'doc_id' => array('data' => t('Doc ID'), 'field' => 'document_id', 'display' => 'none'),
    'textgroup' => array('data' => t('Textgroup'), 'field' => 'textgroup'),
  );

  return $header;
}

function lingotek_config_get_rows($entity_type, $form, &$form_state, $count_only = FALSE) {
  $columns = isset($form_state['values']['columns']) ? $form_state['values']['columns'] : array();
  $header = lingotek_config_header();
  foreach ($header as $title => $data) { // Refine the source header using the selected columns
    if (array_key_exists($title, $columns) && $columns[$title]) {
      $form_state['values']['grid_header'][$title] = $data;
    }
  }

  $filters = isset($_SESSION['grid_filters']) ? $_SESSION['grid_filters'] : array();
  $query = lingotek_config_start_query($form_state);
  lingotek_config_add_query_filters($query, $filters);

  if ($count_only) {
    $result = $query->execute();
    return $result->rowCount();
  }

  $limit = isset($_SESSION['limit_select']) ? $_SESSION['limit_select'] : 10;
  $table_data_raw = $query->extend('PagerDefault')
      ->limit($limit)
      ->execute()
      ->fetchAllAssoc('lid');

  $languages = language_list();
  $lingotek_languages = Lingotek::getLanguages();
  $global_profile = lingotek_get_global_profile();
  $list_statuses = array('pending', 'ready', 'current', 'edited', 'untracked');

  // If there are no results, this will make sure there's no error.
  $table_data = isset($table_data) ? $table_data : array();
  $lids = array_keys($table_data_raw);
  $existing_translations = !empty($lids) ? lingotek_config_add_existing_translations($lids, $lingotek_languages) : array();
  //parse the queried data and put it into different cells
  foreach ($table_data_raw as $row) {
    $lid = $row->{'lid'};
    $row->lingotek = $global_profile;
    $row->lingotek['profile'] = 1;
    $row->language = 'en';

    $locales = array();
    $statuses = array();
    $locale_statuses = array();
    $current = LingotekConfigSet::isLidCurrent($lid);

    foreach ($list_statuses as $status) {
      foreach (explode(',', $row->$status) as $locale) {
        if (!empty($locale)) {
          $locale_statuses[$locale] = $current ? 'current' : $status;
        }
      }
    }

    $dirty = $row->dirty ? explode(',', $row->dirty) : array();
    // If a config string changes, it gets marked as not current (dirty) in locales_target but remains current in lingotek_config_map. $edited checks for this combo.
    $edited = $current && $dirty;
    if ($edited) {
      $row->upload_status = LingotekSync::STATUS_EDITED;
      foreach ($dirty as $key => $lang) {
        $locale = Lingotek::convertDrupal2Lingotek($lang);
        $locale_statuses[$locale] = 'edited';
      }
    }

    ksort($locale_statuses);
    //if there are no translations in locales, check if there are existing translations in locales_target
    if (empty($locale_statuses) && array_key_exists($lid, $existing_translations)) {
      foreach ($existing_translations[$lid] as $existing_lang) {
        $locale_statuses[$existing_lang] = 'untracked';
      }
    }

    $escaped_source = htmlentities($row->{'source'});
    $source_text = lingotek_truncate_grid_text($escaped_source, 55);

    $translation_icons = lingotek_lang_icons($entity_type, $languages, $lid, $locale_statuses, FALSE, $row->language); // show translation statuses
    $target_icons_str = implode('', array_values($translation_icons));
    $icon = lingotek_source_uploaded_icon($row);
    $source_uploaded = lingotek_render_source($entity_type, $row, $icon, $languages, 'language');
    $set_id = LingotekConfigSet::getSetId($lid, FALSE);
    $set_name = $set_id !== FALSE ? LingotekConfigSet::getTitleBySetId($set_id) : t('N/A');

    $data = array(
      'lid' => $lid ? : t('??'),
      'source' => $source_text,
      'source_uploaded' => $source_uploaded,
      'location' => $row->{'location'},
      'context' => $row->{'context'},
      'textgroup' => $row->{'textgroup'} == 'default' ? 'built-in interface' : $row->{'textgroup'},
      'translations' => $target_icons_str,
      'doc_id' => $row->document_id ? $row->document_id : t('N/A'),
      'set_name' => '<span class="set_name">' . $set_name . '</span>'
    );

    $table_data[$lid] = $data;
  }

  return $table_data;
}

function lingotek_config_add_existing_translations($lids, $lang_codes) {
  $query = db_select('{locales_target}', 'lt')
      ->fields('lt', array('lid', 'language'));
  $query->condition('lid', $lids, 'IN');
  $result = $query->execute()->fetchAll();

  $existing_translations = array();
  foreach ($result as $key => $value) {
    $lang = Lingotek::convertDrupal2Lingotek($value->language);
    // This check is performed in lang_icons anyway so you might remove it
    if (array_key_exists($lang, $lang_codes)) {
      $existing_translations[$value->lid][] = Lingotek::convertDrupal2Lingotek($value->language);
    }
  }
  return $existing_translations;
}

function lingotek_config_start_query($form_state) {
  $query = db_select('locales_source', 's')
      ->extend('TableSort')
      ->orderByHeader($form_state['values']['grid_header']);

  // Gets sync statuses for each language.
  $query->addExpression("(SELECT COUNT(*) FROM {locales_target} target LEFT JOIN {lingotek_config_map} map ON map.lid = target.lid WHERE map.lid = s.lid AND map.current = 1 AND target.i18n_status = 0)", 'current_target_count');
  $query->addExpression("(SELECT GROUP_CONCAT(SUBSTRING(config_key, 20, 10)) FROM {lingotek_config_metadata} WHERE id = (SELECT set_id FROM lingotek_config_map WHERE lid = s.lid) AND config_key LIKE 'target_sync_status_%' AND value='PENDING')", 'pending');
  $query->addExpression("(SELECT GROUP_CONCAT(SUBSTRING(config_key, 20, 10)) FROM {lingotek_config_metadata} WHERE id = (SELECT set_id FROM lingotek_config_map WHERE lid = s.lid) AND config_key LIKE 'target_sync_status_%' AND value='READY')", 'ready');
  $query->addExpression("(SELECT GROUP_CONCAT(SUBSTRING(config_key, 20, 10)) FROM {lingotek_config_metadata} WHERE id = (SELECT set_id FROM lingotek_config_map WHERE lid = s.lid) AND config_key LIKE 'target_sync_status_%' AND value='CURRENT')", 'current');
  $query->addExpression("(SELECT GROUP_CONCAT(SUBSTRING(config_key, 20, 10)) FROM {lingotek_config_metadata} WHERE id = (SELECT set_id FROM lingotek_config_map WHERE lid = s.lid) AND config_key LIKE 'target_sync_status_%' AND value='EDITED')", 'edited');
  $query->addExpression("(SELECT GROUP_CONCAT(SUBSTRING(config_key, 20, 10)) FROM {lingotek_config_metadata} WHERE id = (SELECT set_id FROM lingotek_config_map WHERE lid = s.lid) AND config_key LIKE 'target_sync_status_%' AND value='UNTRACKED')", 'untracked');
  $query->addExpression("(SELECT GROUP_CONCAT(language) FROM {locales_target} WHERE lid = s.lid AND i18n_status = 1)", 'dirty');
  $query->addExpression("(SELECT set_id FROM {lingotek_config_map} lcm WHERE lcm.lid = s.lid)", 'set_id');

  // Lingotek Document ID
  $query->leftJoin('{lingotek_config_metadata}', 'lingo_document_id', 'lingo_document_id.id = (SELECT set_id FROM lingotek_config_map WHERE lid = s.lid) AND lingo_document_id.config_key = \'document_id\'');
  $query->addField('lingo_document_id', 'value', 'document_id');

  // Entity Upload Status
  $query->leftJoin('{lingotek_config_metadata}', 'lingo_upload_status', 'lingo_upload_status.id = (SELECT set_id FROM lingotek_config_map WHERE lid = s.lid) AND lingo_upload_status.config_key = \'upload_status\' AND lingo_upload_status.value <> \'' . LingotekSync::STATUS_TARGET . '\'');
  $query->addField('lingo_upload_status', 'value', 'upload_status');
  $query->fields('s', array('source', 'location', 'context', 'lid', 'textgroup'));
  // Only show items in textgroups that are selected for translation on Config Settings page.
  $translatable_textgroups = LingotekConfigSet::getTextgroupsForTranslation() ? : array('no textgroups selected');
  $query->condition('s.textgroup', $translatable_textgroups, 'IN');
  
  return $query;
}

function lingotek_config_add_query_filters($query, $filters) {
  if (isset($filters['search']) && strlen($filters['search'])) {
    $query->condition('source', '%' . $filters['search'] . '%', 'LIKE');
  }

  if (isset($filters['textgroup']) && $filters['textgroup'] == 'interface') {
    $query->condition('textgroup', 'default');
  }
  elseif (isset($filters['textgroup']) && $filters['textgroup'] == 'menu') {
    $query->condition('textgroup', 'menu');
  }
  elseif (isset($filters['textgroup']) && $filters['textgroup'] == 'taxonomy') {
    $query->condition('textgroup', 'taxonomy');
  }
  elseif (isset($filters['textgroup']) && $filters['textgroup'] == 'blocks') {
    $query->condition('textgroup', 'block');
  }

  // Lingotek Document ID
  if (isset($filters['document_id']) && $filters['document_id'] != '') {
    if ($filters['document_id'] == 'None') {
      $query->condition('lingo_document_id.value', NULL);
    }
    else {
      $query->condition('lingo_document_id.value', $filters['document_id']);
    }
  }

  // Upload Status
  if (isset($filters['upload_status']) && $filters['upload_status'] != 'all') {
    if ($filters['upload_status'] == 'never') {
      $query->isNull('lingo_upload_status.value');
    }
    else {
      $query->condition('lingo_upload_status.value', $filters['upload_status']);
    }
  }
}

/**
 * Submit function for The Grid's actions
 * The action corresponds to the key of the option selected
 * Often redirects to batch operations or to other pages entirely
 */
function lingotek_config_action_submit($form, $form_state) {
  $lids = array();

  if (isset($form_state['clicked_button']) && $form_state['clicked_button']['#name'] == 'actions_submit') { // If submitting an action
    foreach ($form_state['values']['the_grid'] as $value) {
      if ($value != 0) {
        $lids[] = $value;
      }
    }

    if (isset($form_state['values']['actions_select'])) { // If an action was selected (which it would be, I don't know if this could ever NOT occur with normal use)
      $action = $form_state['values']['actions_select']; // Get the action
      if (count($lids) <= 0) { // Select a node
        drupal_set_message(t('You must select at least one node to @action.', array('@action' => $action)), 'warning'); // Or pay the price
      }
      elseif ($action == 'upload') { // If uploading
        lingotek_config_upload_selected($lids);
      }
      elseif (substr($action, 0, 8) == 'download') { // If downloading all targets
        lingotek_config_download_selected($lids);
      }
      elseif ($action == 'sync') { // If syncing the progress
        lingotek_config_update_selected($lids); // Run batch operations to get the progress report from Lingotek
      }
      elseif ($action == 'delete' || $action == 'reset') {
        // ajax ctools modal employed (see lingotek_bulk_grid_form() and lingotek.bulk_grid.js)
      }
    }
  }
}

function lingotek_config_upload_selected($lids) {
  if (empty($lids)) {
    drupal_set_message(t('No config items have been edited and need to be uploaded at this time. To translate a config item for the first time, check the corresponding box and upload.'), 'status', FALSE);
    drupal_goto('admin/settings/lingotek/manage/config');
  }
  LingotekConfigSet::markLidsNotCurrent($lids);

  $batch = array(
    'title' => t('Uploading Content To Lingotek'),
    'finished' => 'lingotek_sync_upload_config_finished'
  );

  $set_ids = array();
  // You need to iterate through this (with multiple db queries) because this function will also assign the item to a new set and create a new set if necessary. With one query to get all set_ids, it might not do that.
  foreach ($lids as $lid) {
    $set_id = LingotekConfigSet::getSetId($lid);
    $set_ids[] = $set_id;
  }
  $unique_set_ids = array_unique($set_ids);

  $operations = lingotek_get_sync_upload_config_batch_elements($unique_set_ids);
  $batch['operations'] = $operations;
  $redirect = 'admin/settings/lingotek/manage/config';

  batch_set($batch);
  batch_process($redirect); // Run batch operations to upload all of the selected nodes to Lingotek
}

function lingotek_config_download_selected($lids) {
  $set_ids = array_unique(LingotekSync::getSetIdsFromLids($lids));
  if (empty($set_ids)) {
    drupal_set_message(t('No translations to download at this time. Make sure configuration items have been uploaded to Lingotek before trying to get translations for them.'), 'status', FALSE);
    drupal_goto('admin/settings/lingotek/manage/config');
  }
  LingotekConfigSet::markSetsCurrent($set_ids);
  $document_ids = LingotekSync::getConfigDocIdsFromSetIds($set_ids);
  $available_targets = Lingotek::getLanguages();
  unset($available_targets['en_US']); // all locales_source is assumed by i18n modules to be English
  $download_targets = array();
  foreach ($document_ids as $doc_id) {
    foreach (array_keys($available_targets) as $lang) {
      $target = new stdClass();
      $target->document_id = $doc_id;
      $target->locale = $lang;
      $download_targets[] = $target;
    }
  }
  $redirect = 'admin/settings/lingotek/manage/config';
  $current = lingotek_get_sync_download_batch_elements($download_targets, LingotekSync::STATUS_CURRENT);
  $pending = lingotek_get_sync_download_batch_elements($download_targets, LingotekSync::STATUS_PENDING);
  $operations = array_merge($current, $pending);
  $batch = array(
    'title' => t('Syncing Content and Translations'),
    'operations' => $operations,
    'file' => 'lingotek.batch.inc',
    'finished' => 'lingotek_sync_batch_finished'
  );
  batch_set($batch);
  batch_process($redirect);
}
