<?php

/**
 * @file
 * Utility functions.
 */

/**
 * Drupal JSON Output - CORS - allows cross domain requests
 * (adapted from: drupal_json_output)
 * @param type $var
 */
function lingotek_json_output_cors($var = NULL, $status = "200", $params = array()) {
  // We are returning JSON, so tell the browser.
  $methods_allowed = isset($params['methods_allowed']) ? $params['methods_allowed'] : 'GET,PUT,POST,DELETE';
  drupal_add_http_header('Status', $status);
  drupal_add_http_header('Content-Type', 'application/json');
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', $methods_allowed);
  drupal_add_http_header('Access-Control-Allow-Headers', 'Content-Type');
  drupal_add_http_header('X-Powered-By', 'Lingotek');
  if (isset($var)) {
    echo drupal_json_encode($var);
  }
}

/*
 * Helper function, for storing additional information with a Node.
 * Example usage (GET):
 *    lingotek_lingonode('all') - returns all nodes and properties
 *    lingotek_lingonode(5) - returns all properties for the specified NodeId (i.e., 5)
 *    lingotek_lingonode(5,'node_sync_status') - returns the value for the specified property (i.e., node_sync_status) for the specified NodeId (i.e., 5)
 * Example usage (SET):
 *     lingotek_lingonode(5,'node_sync_status','CURRENT') - sets the value to 'CURRENT' for the property 'node_sync_status' of NodeId 5
 *
 * @param $nid
 *  NodeId.
 * @param $key
 *  (optional) "" Key to look up in the database.  If no key is specified, then
 *  every key for the Node is returned with it's value.
 * @param $value
 *  (optional) "" Value to save.  If "" or no value is given for $value, then
 *  it will return the $value of the first found instance of the specified $key
 *  in the database.  Returns FALSE if no value is found.
 */

function lingotek_lingonode($nid, $key = "", $value = "") {
  if ($nid == 'all') {
    $lingo_node = array();
    $result = db_select('lingotek', 'n')->fields('n', array(db_escape_field('nid'), db_escape_field('lingokey'), db_escape_field('lingovalue')))->execute();
    foreach ($result as $row) {
      $lingo_node[$row->nid][$row->lingokey] = check_plain($row->lingovalue);
    }
    return $lingo_node;
  }
  if ($nid == -1) {
    LingotekLog::error("Invalid -1 nid passed to lingotek_lingonode().", array('@nid' => $nid, '@key' => $key, '@value' => $value));
    return FALSE;
  }
  if (is_numeric($nid) && $nid) {
    //Return an array with all of the keys and values.
    if ($key === "") {
      $lingo_node = array();

      $result = db_select('lingotek', 'n')->fields('n', array(db_escape_field('lingokey'), db_escape_field('lingovalue')))->condition(db_escape_field('nid'), $nid)->execute();
      foreach ($result as $row) {
        $lingo_node[$row->lingokey] = check_plain($row->lingovalue);
      }

      return $lingo_node;
    }
    //Get value for the specified key, only returns the first match
    elseif ($value === "") {
      $result = db_select('lingotek', 'n')->fields('n', array(db_escape_field('lingovalue')))->condition(db_escape_field('nid'), $nid)->condition(db_escape_field('lingokey'), $key)->execute();
      $row = $result->fetchObject();

      if ($row) {
        return check_plain($row->lingovalue);
      }
      else {
        return FALSE;
      }
    }
    //Insert/Update the specified key and value
    else {
      $row = array(
        db_escape_field('nid') => $nid,
        db_escape_field('lingokey') => $key,
        db_escape_field('lingovalue') => $value,
      );

      if (lingotek_lingonode($nid, $key) === FALSE) { //Insert
        drupal_write_record('lingotek', $row);
        return "$nid : $key => $value INSERTED";
      }
      else { //Update
        drupal_write_record('lingotek', $row, array(db_escape_field('nid'), db_escape_field('lingokey')));
        return "$nid : $key => $value UPDATED";
      }
    }
  }
  else {
    LingotekLog::error("Invalid nid (@nid) passed to lingotek_lingonode().", array('@nid' => $nid, '@key' => $key, '@value' => $value));
    return FALSE;
  }
}

/*
 * Helper function to delete a specified variable from the Lingotek table
 *
 * @param int $nid
 *    node id for the variable that needs to be deleted
 *
 * @param string $lingokey
 *    variable name to be deleted
 *
 */

function lingotek_lingonode_variable_delete($nid, $lingokey) {
  $query = db_delete('lingotek');
  $query->condition('nid', $nid);
  $query->condition('lingokey', $lingokey);
  $query->execute();
}

/**
 * Helper function to delete a specified variable from multiple nodes in the Lingotek table
 *
 * @param int array $nids
 *    array of node ids for the variable that needs to be deleted
 *    if a single nid is passed in, it will be converted to an array before processing
 *
 * @param string $lingokey
 *    variable name to be deleted
 *
 * @param string $condition
 *    additional condition checking (i.e. 'LIKE', '<' '=' '>', etc...)
 *    defaults to '='
 */
function lingotek_lingonode_variable_delete_multiple($nids = array(), $lingokey, $condition = '=') {
  if (!is_array($nids)) {
    $nids = array($nids);
  }

  $query = db_select('lingotek', 'l') // Make sure that the variable exists in the table for every input nid
      ->fields('l', array('nid'))
      ->condition('nid', $nids, 'IN')
      ->condition('lingokey', $lingokey, $condition);
  $delete_nids = $query->execute()->fetchCol();

  if (!empty($delete_nids)) {
    $delete = db_delete('lingotek') // delete for every input nid where the variable exists
        ->condition('nid', $delete_nids, 'IN')
        ->condition('lingokey', $lingokey, $condition)
        ->execute();
  }
}

/*
 * When a node is deleted, this is called to clean out the extra data from that node in the Lingotek table.
 *
 * @param $nid
 *  NodeId.
 */

function lingotek_lingonode_delete($nid) {
  db_delete('lingotek')->condition('nid', $nid)->execute();
}

/*
 * Filter for removing unchecked checkboxes from an array for drupal forms
 */

function lingotek_unselected($var) {
  return ($var != "0");
}

/*
 * Create and return an empty default node
 */

function lingotek_empty_node() {
  $node = new stdClass();
  $node->nid = -2;
  $node->language = LANGUAGE_NONE;
  return $node;
}

/*
 * COALESCE(LingotekVariable, DrupalVariable, Default)
 */

function lingotek_variable_get($var, $drupal, $default) {
  if ($var === FALSE) {
    return variable_get($drupal, $default);
  }
  else {
    return $var;
  }
}

/*
 * Get a string representation of an object
 *
 * @param $obj
 *  Object to be var_dump'ed
 * @return
 *  String with the output of var_dump
 */

function lingotek_dump($obj) {
  ob_start();
  var_dump($obj);
  $string = ob_get_contents();
  ob_end_clean();
  return $string;
}

/**
 * Formats a complex object for presentation in a watchdog message.
 */
function watchdog_format_object($object) {
  return '<pre>' . htmlspecialchars(var_export($object, TRUE)) . '</pre>';
}

function lingotek_get_translatable_field_types() {
  // What types of fields DO we translate?
  $included_fields = array('text', 'text_long', 'text_textfield', 'text_textarea', 'text_textarea_with_summary', 'field_collection_embed');
  if (module_exists('link')) {
    $included_fields[] = 'link_field';
  }
  return $included_fields;
}

function lingotek_get_translatable_fields_by_content_type($type) {
  $all_fields = field_info_instances('node', $type);
  $translatable_field_types = lingotek_get_translatable_field_types();
  $desired_fields = array();
  foreach ($all_fields as $field_name => $field_info) {
    if (in_array($field_info['widget']['type'], $translatable_field_types)) {
      $desired_fields[$field_name] = $field_name;
    }
  }
  return $desired_fields;
}

function lingotek_get_enabled_fields($entity_type, $bundle) {
  $translate = variable_get('lingotek_enabled_fields', array());
  $fields_desired = isset($translate[$entity_type][$bundle]) ? $translate[$entity_type][$bundle] : array();
  if (empty($fields_desired)) {
    //this won't work for anything more than nodes yet
    $fields_desired = lingotek_get_translatable_fields_by_content_type($bundle);
  }

  return $fields_desired;
}

/**
 * Return the xml representation of the source content for a node.
 *
 * @param object $node
 *   A Drupal node.
 *
 * @return string
 *   The XML representation of the node in Lingotek format.
 */
function lingotek_xml_node_body($node) {
  $translatable = array();

  $fields_desired = lingotek_get_enabled_fields('node', $node->type);

  foreach ($fields_desired as $value) {
    $field = field_info_field($value);
    if (isset($field)) {
      array_push($translatable, $value);
    }
  }

  $content = lingotek_xml_fields($node, $translatable, $node->language);

  /* deprecated with config translation
    //Menus related to the page:
    // Do we still want this? Config translation translates these items
    $menu = menu_link_get_preferred('node/' . $node->nid);
    $txt = $menu['link_title'];
    if ($txt != "") {
    $content = $content . "<menu_title><![CDATA[$txt]]></menu_title>\n";
    } */

  //URL Alias related to the page:
  $url_alias_translation = $node->lingotek['url_alias_translation'];
  if ($url_alias_translation == 1) {
    $conditions = array('source' => 'node/' . $node->nid);
    if ($node->language != LANGUAGE_NONE) {
      $conditions['language'] = $node->language;
    }
    $path = path_load($conditions);
    if ($path !== FALSE) {
      $url = $path['alias'];
      $content = $content . "<url_alias><![CDATA[$url]]></url_alias>\n";
    }
  }

  return "<?xml version=\"1.0\" encoding=\"UTF-8\"?><contents>$content</contents>";
}

function lingotek_xml_fields($node, $translatable, $language) {
  $content = '';
  
  foreach ($translatable as $field) {
    $text = & $node->$field;

    $field_language = array_key_exists($language, $node->$field) ? $language : LANGUAGE_NONE;

    // Deal with not being initialized right, such as pre-existing titles.
    if (!array_key_exists($field_language, $node->$field) || !array_key_exists(0, $text[$field_language])) {
      continue;
    }

    // We may split compound Drupal fields into several Lingotek fields.
    $target_keys = array(
      'value' => '', // Most text fields
      'summary' => 'summary', // "Long text with summary" fields have this sub-field value as well.
    );
    if (module_exists('link')) {
      $target_keys['link'] = array(
        'url' => '',
        'title' => '',
      );
    }

    // Create fields from all target keys.
    foreach ($target_keys as $target_key => $element_suffix) {
      $array_key = NULL;
      if (is_array($element_suffix)) {
        foreach ($element_suffix as $tarkey => $val) {
          $array_key = $tarkey;
          continue;
        }
      }
      foreach ($text[$field_language] as $delta) {
        if (!empty($delta[$target_key]) || (isset($array_key) && !empty($delta[$array_key]))) {
          $element_name = $field;
          if ($target_key == 'value' && isset($delta['revision_id']) && module_exists('field_collection')) {
            $current_field = '<' . $element_name . '>';
            $enabled_fields = variable_get('lingotek_enabled_fields');
            $entity = lingotek_entity_load_single('field_collection_item', $delta['value']);
            $sub_fields = field_info_instances('field_collection_item', $field);
            $translatable_field_types = lingotek_get_translatable_field_types();
            $translatable_sub_fields = array();
            foreach ($sub_fields as $sub_field => $f) {
              if (in_array($f['widget']['type'], $translatable_field_types) && isset($enabled_fields['field_collection_item'][$field]) && in_array($sub_field, $enabled_fields['field_collection_item'][$field])) {
                $translatable_sub_fields[] = $sub_field;
              }
            }
            $current_field .= lingotek_xml_fields($entity, $translatable_sub_fields, $field_language);
            $current_field .= '</' . $element_name . '>';
            $content .= $current_field;
            continue;
          }
          if (!is_array($element_suffix) && !empty($element_suffix)) {
            $element_name .= '__' . $element_suffix;
          }
          $current_field = '<' . $element_name . '>';
          /* if (!array_key_exists('value', $value)) {
            dpm($value);
            //TODO add TAGs to be translatable
            $terms = $node->$field;
            foreach ($terms[$language] as $term) {
            // Do something.
            }
            continue;
            } */
          if (is_array($element_suffix)) {
            foreach ($element_suffix as $t_key => $t_val) {
              $current_field .= '<element><![CDATA[' . $delta[$t_key] . ']]></element>' . "\n";
            }
          }
          else {
            $current_field .= '<element><![CDATA[' . $delta[$target_key] . ']]></element>' . "\n";
          }
          $current_field .= '</' . $element_name . '>';
          $content .= $current_field;
        }
      }
    }
  }

  return $content;
}

/**
 * Outputs the support footer renderable array.
 */
function lingotek_support_footer() {
  return array(
    '#type' => 'markup',
    '#markup' => theme('table', array('header' => array(), 'rows' => array(
        array(t('<strong>Support Hours:</strong><br>9am - 6pm MDT'),
          t('<strong>Phone:</strong><br> (801) 331-7777'),
          t('<strong>Email:</strong><br> <a href="mailto:support@lingotek.com">support@lingotek.com</a>')
        )),
      'attributes' => array(
        'style' => 'width:500px; margin-top: 20px;'
      )
    ))
  );
}

/**
 * Menu access callback.
 *
 * Only display Lingotek tab for node types, which have translation enabled
 * and where the current node is not language neutral (which should span
 * all languages).
 */
function lingotek_access($node, $permission) {
  if (Lingotek::isSupportedLanguage($node->language) && node_access('update', $node) && $node->lingotek['profile'] != LingotekSync::PROFILE_DISABLED) {
    return user_access($permission);
  }
  return FALSE;
}

/*
 * Top-level menu item
 */

function lingotek_access_tlmi($permission) {
  $hide_top_level_menu_item = variable_get('lingotek_hide_tlmi', 0);
  if ($hide_top_level_menu_item)
    return FALSE;
  else
    return user_access($permission);
}

function lingotek_access_by_plan_type($types, $permission = NULL) {
  $account = LingotekAccount::instance();
  $access = FALSE;
  if (is_string($types)) {
    $types = array($types);
  }
  if (!is_array($types)) {
    return $access;
  }
  foreach ($types as $type) {
    $access = TRUE;
    if (!$account->isPlanType($type)) {
      return FALSE;
    }
  }
  return is_null($permission) ? $access : user_access($permission);
}

function lingotek_access_dev_tools($node, $permission) {
  // Special case:  hide the Lingotek Developer Tools when the node is managed by entity translation and has not been pushed to Lingotek
  // OR when not Enterprise

  $user_access = user_access($permission);
  if (($user_access && module_exists('entity_translation') && entity_translation_node_supported_type($node->type) && !lingotek_node_pushed($node)) || !LingotekAccount::instance()->isEnterprise()) {
    return FALSE;
  }
  // Default: Standard user access
  return $user_access;
}

/**
 * Returns whether the given node type has support for translations.
 *
 * @return
 *   Boolean value.
 */
function lingotek_supported_type($type) {
  $lingotek_supported_explicitly = variable_get('language_content_type_' . $type, NULL) != '0';
  $setting = variable_get('lingotek_entity_profiles');
  $lingotek_supported_setting = isset($setting['node'][$type]) && $setting['node'][$type] != LingotekSync::PROFILE_DISABLED;
  return ($lingotek_supported_explicitly && $lingotek_supported_setting);
}

function lingotek_supported_node($node) {
  return $node->lingotek['profile'] != LingotekSync::PROFILE_DISABLED;
}

/**
 * Returns if the node type an entity_translation managed node
 *
 * @return
 *   Boolean value.
 */
function lingotek_managed_by_entity_translation($type) {
  return module_exists('entity_translation') ? entity_translation_node_supported_type($type) : FALSE;
}

/**
 * Returns whether the given node type is an entity_translation node and has been pushed to lingotek.
 *
 * @return
 *   Boolean value.
 */
function lingotek_node_pushed($node) {
  return isset($node->lingotek['document_id']) && !empty($node->lingotek['document_id']);
}

/**
 * Returns whether the given field type has support for translations.
 *
 * @return
 *   Boolean value.
 */
function lingotek_supported_field_type($type) {
  return in_array($type, array('text_long', 'text_with_summary', 'text')); //'taxonomy_term_reference'));
}

/**
 * Returns whether caching is enabled.
 *
 * @return
 *   Boolean value.
 */
function lingotek_do_cache() {
  return !(variable_get('lingotek_flush_cache', FALSE) && user_access('use lingotek developer tools'));
}

/**
 * Gets the phase ID of the "current" phase for a translation target.
 *
 * @param array $phases
 *   An array of phase data from the result of a getTranslationTarget Lingotek API call.
 *
 * @return int
 *   The Phase ID of the current phase. Note that if all phases are marked as complete,
 *   the ID of the last phase will be returned.
 */
function lingotek_current_phase($phases) {
  $phase_id = -1;

  $current_phase = 0;

  foreach ($phases as $phase) {
    if (!$phase->isMarkedComplete) {
      $phase_id = $phase->id;
      break;
    }
    $current_phase++;
  }

  // All phases are complete, use last phase as current.
  if (!empty($phases) && $phase_id == -1) {
    $last_phase = end($phases);
    $phase_id = $last_phase->id;
  }

  return $phase_id;
}

/**
 * Gets the Lingotek Source Language ( Drupal variable: lingotek_source_language).
 * Returns a language code (ie:  en / es / de)
 * Uses the Drupal default language as a fallback.
 */
function lingotek_get_source_language() {

  $source_language = variable_get('lingotek_source_language', NULL);
  if (empty($source_language)) {
    $drupal_default_language = language_default();
    $source_language = $drupal_default_language->language;
  }

  return $source_language;
}

// END:  lingotek_get_source_language()


function lingotek_managed_entity_types() {
  $types = entity_get_info();
//  $exclude = array('i18n_translation', 'file', 'user', 'taxonomy_vocabulary', 'taxonomy_term');
//  $exclude = array_flip($exclude);
//  return array_diff_key($types, $exclude);
  $enabled = array('node', 'field_collection_item');
  $enabled = array_flip($enabled);

  if (module_exists('field_collection') && $types['field_collection_item']) {
    $types['field_collection_item']['label'] = 'Field Collection';
  }

  return array_intersect_key($types, $enabled);
}

/**
 * Content node types linked to 'translatable' fields.
 */
function lingotek_translatable_node_types() {

  $types = array();

  $fields = lingotek_translatable_node_field_details();
  foreach ($fields as $field) {
    foreach ($field['bundles'] as $bundle) {
      $types[$bundle] = $bundle;
    }
  }

  if (count($types) > 0) {
    $types = array_keys($types);
  }

  return $types;
}

/**
 * Goes though ALL the fields in the system and gets the details about the ones that are marked 'translatable'.
 */
function lingotek_translatable_node_field_details() {

  $fields = field_info_fields();
  $translatable_fields = array();

  foreach ($fields as $field_id => $field) {
    foreach ($field['bundles'] as $type => $instance) {
      /*
        echo '<br>FieldID: ' . $field_id;
        echo '<br>Field: ' . $field;
        echo '<br>Type: ' . $type;
        echo '<br>Instance: ' . $instance;
       */

      if (field_is_translatable($type, $field)) {
        //echo '<br>Translatable: YES!' ;
        $field_db_table = array_keys($field['storage']['details']['sql']['FIELD_LOAD_CURRENT']);
        $field_db_table = array_shift($field_db_table);
        $translatable_fields[] = array(
          'entity_type' => $type,
          'machine_name' => $field['field_name'],
          'db_table' => $field_db_table,
          'bundles' => $field['bundles'][$type],
        );
      }
      //echo '<br>';
    }
  }

  /* Return data format
    array (
    0 =>
    array (
    'entity_type' => 'node',
    'machine_name' => 'body',
    'db_table' => 'field_data_body',
    'bundles' =>
    array (
    0 => 'page',
    1 => 'article',
    ),
    ),
    1 =>
    array (
    'entity_type' => 'node',
    'machine_name' => 'title_field',
    'db_table' => 'field_data_title_field',
    'bundles' =>
    array (
    0 => 'article',
    1 => 'page',
    ),
    ),
    )
   */
  return $translatable_fields;
}

// END:  lingotek_translatable_node_field_details()
// --- Active and Target Language management Functions

/**
 * Get the currently active languages for this Drupal installation.
 */
function lingotek_get_active_languages($get_details = FALSE) {
  $active_languages = current(language_list('enabled'));

  if ($get_details === FALSE) {
    $active_languages = array_keys($active_languages);
  }
  /*
    Get_Details -> FALSE
    array (
    0 => 'en',
    1 => 'es',
    )

    Get_Details -> TRUE
    array (
    'en' =>
    stdClass::__set_state(array(
    'language' => 'en',
    'name' => 'English',
    'native' => 'English',
    'direction' => '0',
    'enabled' => '1',
    'plurals' => '0',
    'formula' => '',
    'domain' => '',
    'prefix' => '',
    'weight' => '0',
    'javascript' => '',
    )),
   */

  return $active_languages;
}

/**
 * Get numbered array with all node source languages
 */
function lingotek_get_node_source_languages() {
  $lingo_langs = array();
  $query = db_select('node', 'n')->distinct()
      ->fields('n', array('language'));
  $query = $query->execute();
  $result = $query->fetchCol(0);
  foreach ($result as $lang) {
    $lingo_lang = Lingotek::convertDrupal2Lingotek($lang);
    if ($lingo_lang) {
      $lingo_langs[$lingo_lang] = $lingo_lang;
    }
  }
  return $lingo_langs;
}

// END:  lingotek_get_active_languages()

/**
 * Flags a target language as active:FALSE in the Target Language tracking.
 */
function lingotek_delete_target_language($lingotek_locale) {
  $result = FALSE;

  if (is_string($lingotek_locale) && strlen($lingotek_locale)) {

    if (Lingotek::convertDrupal2Lingotek(language_default()->language, FALSE) <> $lingotek_locale) {
      db_update('languages')
          ->fields(array(
            'enabled' => 0,
            'lingotek_enabled' => 0,
          ))
          ->condition('lingotek_locale', $lingotek_locale) //->condition('language',  $drupal_language_code)
          ->execute();
      LingotekLog::info("Target language removed: @lingotek_locale"
          , array(
        '@lingotek_locale' => $lingotek_locale)
      );
    }

    // Removes the node sync target language entries from the lingotek table
    // and removes config chunk target language entries from lingotek_config_metadata.
    LingotekSync::deleteTargetEntriesForAllDocs($lingotek_locale);

    // Remove the Target Language from the Lingotek Project.
    $project_id = variable_get('lingotek_project', '');
    $api = LingotekApi::instance();
    $result = $api->removeTranslationTarget(NULL, $project_id, $lingotek_locale);
  }

  return $result;
}

/**
 * Sets the extended target language locale in the languages table and whether or not it is enabled
 * 
 * @param $drupal_language_code
 * @param $lingotek_enable whether or not to enable the language on TMS (default: 1)
 * @param $lingotek_locale the lingotek locale that the drupal code should be associated with (it will try to pick the right one when not passed in)
 * @param $api_add boolean whether or not to add the language to all documents in the project (default: TRUE)
 * 
 */
function lingotek_set_target_language($drupal_language_code, $lingotek_enable = 1, $lingotek_locale = NULL, $api_add = TRUE) {
  $result = FALSE;

  if (is_string($drupal_language_code) && strlen($drupal_language_code)) {
    $lingotek_locale = is_null($lingotek_locale) ? Lingotek::convertDrupal2Lingotek($drupal_language_code, FALSE) : $lingotek_locale;
    db_update('languages')
        ->fields(array(
          'enabled' => 1,
          'lingotek_enabled' => $lingotek_enable ? 1 : 0,
          'lingotek_locale' => $lingotek_locale
        ))
        ->condition('language', $drupal_language_code)
        ->execute();
    LingotekLog::info("Target language added: @drupal_language_code", array('@drupal_language_code' => $drupal_language_code));

    if ($lingotek_enable && $lingotek_locale && $api_add) { // Add the Target Language to the Lingotek Project.
      $api = LingotekApi::instance();
      $projects = LingotekSync::getSyncProjects();
      foreach ($projects as $project_id) {
        $result = $api->addTranslationTarget(NULL, $project_id, $lingotek_locale);
      }
    }
  }

  return $result;
}

function lingotek_lookup_language_by_locale($lingotek_locale) {
  $languages = language_list();
  foreach ($languages as $language) {
    if (isset($language->lingotek_locale) && strcmp($language->lingotek_locale, $lingotek_locale) == 0) {
      return $language;
    }
  }
  return FALSE;
}

function lingotek_lookup_locale_exists($drupal_language_code) {
  $languages = language_list();
  foreach ($languages as $target) {
    if (isset($target->language) && strcmp($target->language, $drupal_language_code) == 0) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Adds the target language as being enabled.
 */
function lingotek_add_target_language($lingotek_locale, $call_api = TRUE) {
  if (is_null($lingotek_locale)) {
    return FALSE;
  }
  lingotek_add_missing_locales(); // fills in any missing lingotek_locale values to the languages table
  $language = lingotek_lookup_language_by_locale($lingotek_locale);

  if ($language) {
    // ALREADY EXISTS IN LANGUAGE TABLE
    // If already in the languages table then just tack on the lingotek_locale and enable it
    $drupal_language_code = $language->language;
  }
  else {
    // DOES NOT EXIST, INSERT NEW INTO LANGUAGE TABLE
    // If not add it to the languages table first and then tack on the lingotek_locale and enable it
    $drupal_language_code = Lingotek::convertLingotek2Drupal($lingotek_locale, FALSE);
    if (lingotek_lookup_locale_exists($drupal_language_code)) { // drupal code is already being used, generate another
      $errors = array($drupal_language_code);
      $drupal_language_code = strtolower(str_replace("_", "-", $lingotek_locale));
      if (lingotek_lookup_locale_exists($drupal_language_code)) {
        $errors[] = $drupal_language_code;
        LingotekLog::error("Cannot add language code.  Attempted language codes already being used: !errors", array('!errors' => $errors));
        return FALSE; // do not add the language.
      }
    }

    $name = isset($_POST['language']) ? $_POST['language'] : NULL;
    $native = isset($_POST['native']) ? $_POST['native'] : NULL;
    $direction = isset($_POST['direction']) && (strcasecmp('RTL', $_POST['direction']) == 0 ) ? LANGUAGE_RTL : LANGUAGE_LTR;
    $domain = '';
    $prefix = '';
    locale_add_language($drupal_language_code, $name, $native, $direction, $domain, $prefix); // Function from the Locale module.
  }

  LingotekSync::insertTargetEntriesForAllDocs($lingotek_locale); // Add the node sync target language entries to the lingotek table.
  return lingotek_set_target_language($drupal_language_code, 1, $lingotek_locale, $call_api);
}

/**
 * Fills in any missing lingotek_locale values to the languages table
 */
function lingotek_add_missing_locales() {
  LingotekLog::trace(__METHOD__);
  $languages = language_list();
  $default_language = language_default();
  $update_static_language_list = FALSE;
  //$source_language = variable_get( 'lingotek_source_language', $default_language->language );
  foreach ($languages as $target) {
    if (isset($target->lingotek_locale) && !strlen($target->lingotek_locale)) {
      $drupal_language_code = $target->language;
      $lingotek_locale = Lingotek::convertDrupal2Lingotek($drupal_language_code, FALSE);
      lingotek_enable_language_by_code($drupal_language_code, $lingotek_locale);
      $update_static_language_list = TRUE;
    }
  }
  if ($update_static_language_list) {
    drupal_static_reset('language_list');
  }
}

/**
 * Add lingotek_locale and lingotek_enable to the language table for the passed in drupal_language_code 
 */
function lingotek_enable_language_by_code($drupal_language_code, $lingotek_locale = NULL) {
  $field_data = array(
    'enabled' => 1,
    //'lingotek_enabled' => $lingotek_enabled ? 1 : 0,
  );
  if (!is_null($lingotek_locale)) {
    $field_data['lingotek_locale'] = $lingotek_locale;
  }
  db_update('languages')
      ->fields($field_data)
      ->condition('language', $drupal_language_code)
      ->execute();
  return $lingotek_locale;
}

/**
 * Get only the languages that are enabled
 */
function lingotek_get_target_locales($codes_only = TRUE) {
  $target_languages = db_query("SELECT * FROM {languages} WHERE enabled = :enabled", array(':enabled' => 1))->fetchAll();
  $target_codes = array();
  foreach ($target_languages as $target_language) {
    $target_codes[] = $target_language->lingotek_locale;
  }
  return $codes_only ? $target_codes : $target_languages;
}

/**
 * Get only the languages that are enabled
 */
function lingotek_get_target_languages($codes_only = TRUE) {
  $target_languages = db_query("SELECT * FROM {languages} WHERE enabled = :enabled", array(':enabled' => 1))->fetchAll();
  $target_codes = array();
  foreach ($target_languages as $target_language) {
    $target_codes[] = $target_language->language;
  }
  return $codes_only ? $target_codes : $target_languages;
}

/*
 * Get the Lingotek Content Types - returns the content types OR ids only
 *
 * @param $ids_only
 *  Boolean - return ids only rather than associative array of content type definitions
 * @return
 *  Mixed - associative array (or an array of keys when ids_only is TRUE)
 */

function lingotek_get_content_types() {
  $fields = variable_get('lingotek_enabled_fields');
  return array_keys($fields['node']);
}

/*
 * Custom unix timestamp formatter
 *
 * @param int $unix_timestamp
 *    Unix timestamp to format
 *
 * @param bool $as_array
 *    if true, return string formatted like '[number] [time interval]'.  For example, '22 hours'.
 *    if false, returns number and time interval as an array where the array is ('number' => [number], 'interval' => [time interval]).
 */

function lingotek_human_readable_timestamp($unix_timestamp, $as_array = FALSE) {
  $time = time() - $unix_timestamp;

  $intervals = array(
    31536000 => 'year',
    2592000 => 'month',
    604800 => 'week',
    86400 => 'day',
    3600 => 'hour',
    60 => 'minute',
    1 => 'second'
  );

  foreach ($intervals as $unit => $text) {
    if ($time < $unit) {
      continue;
    }
    $number = floor($time / $unit);
    if ($as_array) {
      return array('number' => $number, 'interval' => $text . (($number > 1) ? 's' : ''));
    }
    else {
      return $number . ' ' . $text . (($number > 1) ? 's' : '');
    }
  }
  if ($as_array) {
    return array('number' => 0, 'interval' => 'seconds');
  }
  else {
    return '0 seconds';
  }
}

/**
 * Returns the site name, or base url if that isn't set.
 * */
function lingotek_get_site_name() {
  $site_name = variable_get('site_name', NULL);
  if (empty($site_name)) {
    global $base_root;
    $site_url = parse_url($base_root);
    $site_name = $site_url['host'];
  }
  return $site_name;
}

function lingotek_get_module_info($field = NULL) {
  $result = db_query("SELECT info FROM {system} WHERE type = :type AND name = :name", array(':type' => 'module', ':name' => 'lingotek'))->fetchObject();
  $info = unserialize(current($result));
  return is_null($field) ? $info : (isset($info[$field]) ? $info[$field] : NULL);
}

/**
 * Clean-up utility
 */
function lingotek_cleanup_utility($show_messages = TRUE, $autoset_batch = TRUE) {
  LingotekLog::trace(__METHOD__);

  lingotek_set_priority();
  lingotek_set_defaults();
  lingotek_migration_1();
  lingotek_migration_2();
  lingotek_migration_3();
  lingotek_add_missing_locales(); // fills in any missing lingotek_locale values to the languages table

  $cleanup_batch = lingotek_field_language_data_cleanup_batch_create($autoset_batch = $autoset_batch);
  if ($show_messages) {
    drupal_set_message(t('The field data cleanup utility completed.'));
  }

  lingotek_batch_identify_content(); // Identify pre-existing content (based on these new content-type settings)
  if ($show_messages) {
    drupal_set_message(t('Translatable content has been identified.'));
  }
  
  if ($cleanup_batch && is_array($cleanup_batch)) {
    return $cleanup_batch;
  }
}

/**
 * lingotek_refresh_api_cache utility
 */
function lingotek_refresh_api_cache($show_messages = TRUE) {
  LingotekLog::trace(__METHOD__);

  $api = LingotekApi::instance();

  // Call methods with $reset = TRUE to update our locally cached values.
  $api->listProjects(TRUE);
  $api->listWorkflows(TRUE);
  $api->listVaults(TRUE);

  if ($show_messages) {
    drupal_set_message(t('Project, workflow, and vault information has been refreshed.'));
  }
}

/**
 * Sets the priority of the Lingotek Translation module
 */
function lingotek_set_priority() {
  db_update('system')->fields(array('weight' => 12))->condition('name', 'lingotek')->execute();
}

/**
 * Gets the default profile info, mapped by entity type
 */
function lingotek_load_profile_defaults($entity_type) {
  $profile_defaults = lingotek_get_profiles();
  $entity_profile_defaults = variable_get('lingotek_entity_profiles', array());
  $profile_map = array();
  if (array_key_exists($entity_type, $entity_profile_defaults)) {
    foreach ($entity_profile_defaults[$entity_type] as $k => $v) {
      $profile_map[$k] = is_numeric($v) ? $profile_defaults[$v] : array();
      $profile_map[$k]['profile'] = $v;
      unset($profile_map[$k]['name']);
    }
    return $profile_map;
  }
  return array();
}

/**
 * Sets the global defaults for the Lingotek Translation module
 */
function lingotek_set_defaults() {
  LingotekLog::trace(__METHOD__);
  $defaults = array(
    'lingotek_sync' => 1, // auto-download
    'lingotek_create_documents_by_default' => 1, // auto-upload
    'lingotek_advanced_parsing' => TRUE
  );

  // Check if vars are set.  If so, use what is already set.  If not, set them using the defaults provided above.
  foreach ($defaults as $k => $default_value) {
    variable_set($k, variable_get($k, $default_value));
  }

  lingotek_set_default_advanced_xml();
}

/**
 * Migration 1
 */
function lingotek_migration_1() {
  $spec = array(
    'type' => 'int',
    'description' => "Lingotek enabled",
    'not null' => TRUE,
    'default' => 0
  );
  try {
    db_add_field('languages', 'lingotek_enabled', $spec);
  } catch (DatabaseSchemaObjectExistsException $e) {
    // already exists (no need to do anything)
  }

  // store lingotek enabled fields in languages table rather than variable table
  $target_languages = array_filter(explode('|', variable_get('lingotek_target_languages', '')));
  foreach ($target_languages as $drupal_language_code) {
    lingotek_enable_language_by_code($drupal_language_code);
  }
  variable_del('lingotek_target_languages');
  drupal_static_reset('language_list');
}

/**
 * Migration 2
 */
function lingotek_migration_2() {
  $spec = array(
    'type' => 'varchar',
    'description' => "Locale mapping",
    'length' => 10,
    'not null' => TRUE,
    'default' => ''
  );
  try {
    db_add_field('languages', 'lingotek_locale', $spec);
  } catch (DatabaseSchemaObjectExistsException $e) {
    // already exists (no need to do anything)
  }
  drupal_static_reset('language_list');
}

/**
 * Migration 3 - Upgrade lingotek table entries from drupal_codes to lingotek_locales (whenever applicable) 
 */
function lingotek_migration_3() {
  $ret = array();
  $field_name_prefix = 'target_sync_status_';
  $result = db_query("SELECT lingokey, COUNT(*) as num FROM {lingotek} WHERE lingokey LIKE :pattern GROUP BY lingokey"
      , array(':pattern' => db_like($field_name_prefix) . '%'));

  $total_affected_rows = 0;
  foreach ($result as $record) {
    $old_key = $record->lingokey;
    $code = @end(explode("_", $old_key));
    $lingotek_locale = Lingotek::convertDrupal2Lingotek($code, FALSE); //will return FALSE when lingotek_locales are passed in (so, they'll be skipped)
    if ($lingotek_locale) {
      $new_key = $field_name_prefix . $lingotek_locale;
      //dpm($old_key . " => " . $new_key);
      $query = db_update('lingotek', $ret)
          ->fields(array('lingokey' => $new_key))
          ->condition('lingokey', $old_key, '=');
      try {
        $affected_rows = $query->execute();
      } catch (PDOException $e) {
        // skip these:  manually delete for later rows that key violation constraint issues (if it already exists it need not succeed)
        $affected_rows = 0;
      }
      $total_affected_rows += $affected_rows;
    }
    else {
      //dpm("skip: ".$old_key);
    }
  }
  $ret['total_affected_rows'] = $total_affected_rows;
  //drupal_set_message("fields updated");
  return $ret;
}

function lingotek_profile_condition($node_table, $table_auto_upload, $table_profile, $profile_id) {
  $or = db_or();
  
  $fields = variable_get('lingotek_enabled_fields');
  $types = array();
  foreach(lingotek_load_profile_defaults('node') as $content_type => $profile) {
    if($profile['profile'] == (string)$profile_id && isset($fields['node'][$content_type])) {
      $types[] = $content_type;
    }
  }
  
  if(!empty($types)) {
    $an = db_and();
    $an->condition($node_table . '.type', $types, 'IN');
    $an->condition($table_auto_upload . '.lingovalue', NULL);
    $an->condition($table_profile . '.lingovalue', NULL);
    $or->condition($an);
  }
  $or->condition($table_profile . '.lingovalue', $profile_id);
  
  return $or;
}

function lingotek_workbench_icon($entity_type, $id, $lingotek_locale, $tooltip = 'Edit this translation') {
  drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/workbench-link.css', array('group' => CSS_DEFAULT));
  
  $text = '<img src="' . base_path() . drupal_get_path('module', 'lingotek') . '/images/ico_chevs_dd.png" >';
  $url = $entity_type . '/' . $id . '/lingotekworkbench/' . $lingotek_locale;
  return l($text, $url, array('attributes' => array('class' => array('translation-link'), 'target' => '_blank', 'title' => $tooltip), 'html' => TRUE));
}

function lingotekArrayRecursiveDiff($aArray1, $aArray2) {
  $aReturn = array();

  foreach ($aArray1 as $mKey => $mValue) {
    if ( (!is_array($mValue) && substr($mKey, 0, 4) == 'safe') || $mValue == NULL) {
      continue;
    }
      
    if (array_key_exists($mKey, $aArray2)) {
      if (is_array($mValue)) {
        $aRecursiveDiff = lingotekArrayRecursiveDiff($mValue, $aArray2[$mKey]);
        if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
      } else {
        if ($mValue != $aArray2[$mKey]) {
          $aReturn[$mKey] = $mValue;
        }
      }
    } else {
      $aReturn[$mKey] = $mValue;
    }
  }
  return $aReturn;
}
