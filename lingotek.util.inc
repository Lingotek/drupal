<?php
/**
 * @file
 * Utility functions.
 */

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
    lingotek_error("Invalid -1 nid passed to lingotek_lingonode().", array('nid' => $nid, 'key' => $key, 'value' => $value));
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
    lingotek_error("Invalid nid passed to lingotek_lingonode().", array('nid' => $nid, 'key' => $key, 'value' => $value), 1);
    return FALSE;
  }
}

/*
 * When a node is deleted, this is called to clean out the extra data from that node in the Lingotek table.
 *
 * @param $nid
 *  NodeId.
 */
function lingotek_lingonode_delete( $nid ) {
  db_delete('lingotek')->condition('nid', $nid)->execute();
}

/*
 * Revert from having been embedded in XML
 *
 * @param $text
 *  Text to be unescaped
 * @return
 *  Text that has been unescaped
 */
function lingotek_xml_decode($text) {
  //return htmlspecialchars_decode($text);
  $text = str_replace("&lt;", "<", $text);
  $text = str_replace("&gt;", ">", $text);
  $text = str_replace("&apos;", "'", $text);
  $text = str_replace("&quot;", "\"", $text);
  $text = str_replace("&amp;", "&", $text);
  return $text;
}

/**
 * Run this on nodes you aren't sure if they've been initialized yet for use with Lingotek.
 */
function lingotek_node_init_default($node) {
  if (lingotek_lingonode($node->nid, 'document_id') === FALSE) {
    lingotek_trace("lingotek_node_init_default", array('nid' => $node->nid, 'language' => $node->language));
    LingotekApi::instance()->addContentDocument($node, TRUE);
    lingotek_lingonode($node->nid, 'phase_template_id', variable_get('lingotek_phase_template', 2));
  }
}

/**
 * Synchronize the node's content with current translations as stored on the Lingotek platform.
 *
 * @param object $node
 *   A Drupal node object.
 */
function lingotek_node_sync(&$node) {
  // Keep synchronized?
  $sync_method = lingotek_variable_get(lingotek_lingonode($node->nid, 'sync_method'), 'lingotek_sync', 100);
  $lingotek_document_id = lingotek_lingonode($node->nid, 'document_id');
  lingotek_trace("lingotek_node_sync", array("nid" => $node->nid, 'sync_method' => $sync_method));
  if ($sync_method != "0" && $lingotek_document_id) {
    $progress = lingotek_get_document_targets(lingotek_lingonode($node->nid, 'document_id'));
    // Check for changes in % complete.
    foreach ($progress as $lang => $target) {
      $key = 'percent_complete_' . $lang;
      $before = lingotek_lingonode($node->nid, $key);
      if ($before == "") {
        $before = "0";
      }
      $after = $target->percentComplete;
      if (
        ($sync_method == "1" && $before !== (string)$after) ||
        ($sync_method == "100" && $after == "100" && $before !== (string)$after)
      ) {
        lingotek_trace("lingotek_node_sync percent complete changed", array('target_language' => $lang, 'nid' => $node->nid, 'percent' => $after));
        lingotek_lingonode($node->nid, $key, $after);
        lingotek_download_document($node, $lang);
      }
      else {
        lingotek_trace("lingotek_node_sync percent complete stayed the same", array('target_language' => $lang, 'nid' => $node->nid, 'percent' => $after));
      }
    }
  }
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
 * Debug/Trace error logging
 */
function lingotek_trace($msg, $data = NULL) {
  if (variable_get('lingotek_trace_log', TRUE)) {
    return;
  }
  lingotek_error($msg, $data, $depth = 1, WATCHDOG_DEBUG);
}

/*
 * Error output
 *
 * @param $msg
 *  Text to display in the error as the cause
 * @param $data
 *  default = NULL, Additional useful data as an associative array to print for diagnosing the problem.
 * @param $depth
 *  default = 0, How far to look for the calling function and it's parameters
 * @param $severity
 *  default = WATCHDOG_ERROR, watchdog severity
 */
function lingotek_error($msg, $data = NULL, $depth = 0, $severity = WATCHDOG_ERROR) {
  if ($severity == WATCHDOG_WARNING && variable_get('lingotek_warning_log', FALSE)) {
    return;
  }

  $backtrace = debug_backtrace();
  $location = $backtrace[$depth]['file'] . ':' . $backtrace[$depth]['line'];
  $function = $backtrace[$depth + 1]['function'];
  $args = @json_encode($backtrace[$depth + 1]['args']);

  $data_output = "";
  if (isset($data)) {
    $data_output = json_encode($data);
  }

  watchdog
  (
    'lingotek',
    '<span style="word-break: break-all;"><b>MESSAGE:</b> %msg <br /><b>DATA:</b> %data <br /><b>FILE:</b> %location<br /><b>FUNCTION:</b>%function<br /><b>ARGS:</b> %args</span>',
    array(
      '%msg' => $msg,
      '%data' => $data_output,
      '%location' =>  $location,
      '%function' => $function,
      '%args' => $args,
    ),
    $severity
  );

  if (variable_get('lingotek_error_log', FALSE)) {
    error_log("MESSAGE: $msg DATA: $data_output FILE: $location FUNCTION: $function ARGS: $args");
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

  foreach ($node as $key => $value) {
    $field = field_info_field($key);
    if (isset($field) && array_key_exists('lingotek_translatable', $field) && $field['lingotek_translatable'] == 1) {
      array_push($translatable, $key);
    }
  }

  $content = '';
  foreach ($translatable as $field) {
    $language = $node->language;
    if (!array_key_exists($language, $node->$field)) {
      $language = LANGUAGE_NONE;
    }
    $text = & $node->$field;
    // Deal with not being initialized right, such as pre-existing titles.
    if (!array_key_exists($language, $node->$field) || !array_key_exists(0, $text[$language])) {
      continue;
    }

    // We may split compound Drupal fields into several Lingotek fields.
    $target_keys = array(
      'value' => '', // Most text fields
      'summary' => 'summary' // "Long text with summary" fields have this sub-field value as well.
    );

    // Create fields from all target keys.
    foreach ($target_keys as $target_key => $element_suffix) {
      if (!empty($text[$language][0][$target_key])) {
        $element_name = $field;
        if (!empty($element_suffix)) {
          $element_name .= '__' . $element_suffix;
        }

        $current_field = '<' . $element_name . '>';

        foreach ($text[$language] as $key => $value) {
          if (!array_key_exists('value', $value)) {
            //TODO add TAGs to be translatable
            /*
            $terms = $node->$field;
            foreach ($terms[$language] as $term) {
              // Do something.
            }
            */
            continue;
          }

          $current_field .= '<element><![CDATA[' . $value[$target_key] . ']]></element>' . "\n";
        }

        $current_field .= '</' . $element_name . '>';
        $content .= $current_field;
      }
    }
  }

  //Menus related to the page:
  $menu = menu_link_get_preferred('node/' . $node->nid);
  $txt = $menu['link_title'];
  if ($txt != "") {
    $content = $content . "<menu_title><![CDATA[$txt]]></menu_title>\n";
  }

  //URL Alias related to the page:
  $url_alias_translation = lingotek_variable_get(lingotek_lingonode($node->nid, 'url_alias_translation'), 'lingotek_url_alias_translation', 1);
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

  if (lingotek_supported_language($node->language) && lingotek_supported_type($node->type) && node_access('update', $node)) {
    if (lingotek_managed_by_entity_translation($node->type) && !lingotek_node_pushed($node)) {
      return FALSE;
    }
    return user_access($permission);
  }
  return FALSE;
}


/**
 * Returns whether the given node type has support for translations.
 *
 * @return
 *   Boolean value.
 */
function lingotek_supported_type($type) {
  $lingotek_supported_explicitly = variable_get('language_content_type_' . $type, NULL) == LINGOTEK_ENABLED;
  $entity_translation_managed = lingotek_managed_by_entity_translation($type);
  return $lingotek_supported_explicitly || $entity_translation_managed;//we also support entity_translation managed content
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
  $lingonode = lingotek_lingonode($node->nid, 'document_id');
  $pushed_to_lingotek = $lingonode !== FALSE;//!empty($lingonode);
  return $pushed_to_lingotek;
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
 * Returns whether the given language is supported.
 *
 * @return
 *   Boolean value.
 */
function lingotek_supported_language($language) {
  global $_lingotek_locale;
  $supported = $language != LANGUAGE_NONE && array_key_exists($language, $_lingotek_locale);
  if (!$supported) {
    lingotek_error(
      "Unsupported language detected",
      array('language' => $language),
      1,
      WATCHDOG_WARNING
    );
  }
  return $supported;
}

/**
 * Returns whether caching is enabled.
 *
 * @return
 *   Boolean value.
 */
function lingotek_do_cache() {
  return !(variable_get('lingotek_flush_cache', FALSE) && user_access('dev'));
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

  $source_language = variable_get( 'lingotek_source_language', NULL );
  if ( empty( $source_language ) ) {
    $drupal_default_language = language_default();
    $source_language = $drupal_default_language->language;
  }

  return $source_language;

} // END:  lingotek_get_source_language()


/**
 * Content node types linked to 'translatable' fields.
 */
function lingotek_translatable_node_types() {

  $types = array();

  $fields = lingotek_translatable_node_field_details();
  foreach ( $fields as $field ) {
    foreach ( $field['bundles'] as $bundle ) {
      $types[$bundle] = $bundle;
    }
  }

  if ( count( $types ) > 0 ) {
    $types = array_keys( $types );
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
        $field_db_table = array_keys( $field['storage']['details']['sql']['FIELD_LOAD_CURRENT'] );
        $field_db_table = array_shift( $field_db_table );
        $translatable_fields[] =  array( 
          'entity_type' => $type,
          'machine_name' => $field['field_name'],
          'db_table' => $field_db_table ,
          'bundles' => $field['bundles'][ $type ],
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

} // END:  lingotek_translatable_node_field_details()



// --- Active and Target Language management Functions

/**
 * Get the currently active languages for this Drupal installation.
 */
function lingotek_get_active_languages( $get_details = FALSE ) {
  $active_languages = current(language_list('enabled'));

  if ( $get_details === FALSE ) {
    $active_languages = array_keys( $active_languages );
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

} // END:  lingotek_get_active_languages()


 /**
 * Flags a target language as active:FALSE in the Target Language tracking.
 */
function lingotek_delete_target_language($drupal_language_code) {
  $result = FALSE;

  if (is_string($drupal_language_code) && strlen($drupal_language_code)) {

    //remove drupal_language_code from target_languages system variable
    $target_languages = lingotek_get_target_languages();
    if (($key = array_search($drupal_language_code, $target_languages)) !== false) {
      unset($target_languages[$key]);
      watchdog("lingotek", "Target language removed: @drupal_language_code", array('@drupal_language_code' => $drupal_language_code));
    }
    lingotek_set_target_languages($target_languages);

    // Remove the Target Language from the Lingotek Project.
    $project_id = variable_get('lingotek_project', '');
    $api = LingotekApi::instance();
    $result = $api->removeTranslationTarget(NULL, $project_id, $drupal_language_code);
  }

  return $result;
}

/**
 * Adds a target language to the local Target Language tracking, and adds the Target Langauge to the Lingotek Project.
 */
function lingotek_add_target_language($drupal_language_code) {
  $result = FALSE;

  if (is_string($drupal_language_code) && strlen($drupal_language_code)) {

    $target_languages = lingotek_get_target_languages();
    $target_languages[] = $drupal_language_code;
    lingotek_set_target_languages(array_unique($target_languages));

    // Add the Target Language to the Lingotek Project.
    $project_id = variable_get('lingotek_project', '');
    $api = LingotekApi::instance();
    $result = $api->addTranslationTarget(NULL, $project_id, $drupal_language_code);
  }

  return $result;
}

/**
 * Save the Targeted Languages data.
 */
function lingotek_set_target_languages($target_languages) {
  if (isset($target_languages) && is_array($target_languages)) {
    variable_set('lingotek_target_languages', implode('|',$target_languages));
  } 
}

// END:  lingotek_set_target_languages()

/**
 * Get the languages we are going to translate into.  These are ONLY those added on the Dashboard.
 * These are Drupal language codes.  This is how we track lingotek translations vs active Drupal languages.
 * pass in active == TRUE, to get ONLY the active languages.
 */
function lingotek_get_target_languages($active_only = FALSE) {
  $target_languages = explode('|',variable_get('lingotek_target_languages', ''));
  return $target_languages;
}

/*
 * Uses lingotek_get_target_languages to build a Drupal style format language_list()
 * So this list ONLY includes languages listed in the dashboard.
 */ 
function lingotek_get_target_language_list() {

  $paid_targets = array();

  $active_languages = lingotek_get_active_languages( TRUE );// Languages activated with Drupal locale
  $managed_languages = lingotek_get_target_languages( TRUE ); // Simple language list of Paid Target Languages
  foreach ( $managed_languages as $code ) {
    if(isset($active_languages[ $code ])) {
      $paid_targets[ $code ] = $active_languages[ $code ];
    }
  }
  
  return $paid_targets;
}

/*
 * Get the Lingotek Content Types - returns the content types OR ids only
 *
 * @param $ids_only
 *  Boolean - return ids only rather than associative array of content type definitions
 * @return
 *  Mixed - associative array (or an array of keys when ids_only is TRUE)
 */
function lingotek_get_content_types($ids_only = FALSE) {
  $raw_types = node_type_get_types();
  $types = array();
  foreach ($raw_types as $raw_type_id => $raw_type) {
    $lingotek_supported_explicitly = variable_get('language_content_type_' . $raw_type_id, NULL) == LINGOTEK_ENABLED;
    if ($lingotek_supported_explicitly) {
      $types[$raw_type_id] = $raw_type;
    }
  }
  return $ids_only ? array_keys($types) : $types;
}

/**
 * Returns the site name, or base url if that isn't set.
**/
function lingotek_get_site_name() {
  $site_name = variable_get( 'site_name', NULL );
  if ( empty( $site_name ) ) {
    global $base_root;
    $site_url = parse_url( $base_root );
    $site_name = $site_url['host'];
  }
  return $site_name;
}
