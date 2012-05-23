<?php
/**
 * @file
 * Utilities
 */

/*
 * Get the raw url for the Lingotek Workbench for a given Node and phase (workflow step)
 */
function lingotek_workbench_url($node, $phase_id) {
  $document_id = lingotek_lingonode($node->nid, 'document_id_' . $node->language);

  $arr = array
  (
    'community' => variable_get('lingotek_community', ''),
    'id' => variable_get('lingotek_login_id', ''),
    'time' => time(),
    'document' => $document_id,
    'phase' => $phase_id
  );

  $mode = 'cmsWorkbench.action';
  $json_str = json_encode($arr);

  lingotek_trace("lingotek_workbench_url", $arr);

  return variable_get('lingotek_url', '') . "/lingopoint/portal/" . $mode . "?auth_json=" . urlencode($json_str) . "&hmac=" . urlencode(LingotekSession::create_mac($json_str));
}

/*
 * Helper function, for storing additional information with a Node.
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
    if ($key == "") {
      $lingo_node = array();

      $result = db_select('lingotek', 'n')->fields('n', array(db_escape_field('lingokey'), db_escape_field('lingovalue')))->condition(db_escape_field('nid'), $nid)->execute();
      foreach ($result as $row) {
        $lingo_node[$row->lingokey] = check_plain($row->lingovalue);
      }

      return $lingo_node;
    }
    //Get value for the specified key, only returns the first match
    elseif ($value == "") {
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

/*
 * Run this on nodes you aren't sure if they've been initialized yet for use with Lingotek.
 */
function lingotek_node_init_default($node) {
  if (lingotek_lingonode($node->nid, 'document_id_' . $node->language) === FALSE) {
    lingotek_trace("lingotek_node_init_default", array('nid' => $node->nid, 'language' => $node->language));
    lingotek_add_document($node);
    lingotek_lingonode($node->nid, 'phase_template_id', variable_get('lingotek_phase_template', 2));
  }
  #Add any missing languages and their phase templates
  lingotek_add_missing_targets($node);
}

/*
 * Synchonize the node's content with current translations as stored on the Lingotek platform
 */
function lingotek_node_sync(&$node) {
  //Keep syncronized?
  $sync_method = lingotek_variable_get(lingotek_lingonode($node->nid, 'sync_method'), 'lingotek_sync', 0);
  lingotek_trace("lingotek_node_sync", array("nid" => $node->nid, 'sync_method' => $sync_method));
  if ($sync_method != "0") {
    $progress = lingotek_get_document_targets(lingotek_lingonode($node->nid, 'document_id_' . $node->language));
    //Check for changes in % complete
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

/*
 * Return the xml representation of the source content for a node.
 */
function lingotek_xml_node_body($node) {

  $translatable = array();
  foreach($node as $key => $value) {
    $field = field_info_field($key);
    if (isset($field) && array_key_exists('lingotek_translatable', $field) && $field['lingotek_translatable'] == 1) {
      array_push($translatable, $key);
    }
  }

  $content = "";
  foreach($translatable as $field) {
    $language = $node->language;
    if (!array_key_exists($language, $node->$field)) {
      $language = LANGUAGE_NONE;
    }
    $text = & $node->$field;
    //Deal with not being initialized right, such as pre-existing titles.
    if (!array_key_exists($language, $node->$field) || !array_key_exists(0, $text[$language])) {
      continue;
    }
    $content = $content . "<$field>";
    foreach($text[$language] as $key => $value) {
      if (!array_key_exists('value', $value)) {
        //TODO add TAGs to be translatable
        /*
        $terms = $node->$field;
        foreach ($terms[$language] as $term) {
          dpm($term->name);
        }
        */
        continue;
      }
      $txt = $value['value'];
      $content = $content . "<element><![CDATA[$txt]]></element>\n";
    }
    $content = $content . "</$field>\n";
  }

  //Menus related to the page:
  $menu = menu_link_get_preferred('node/' . $node->nid);
  $txt = $menu['link_title'];
  if($txt != "") {
    $content = $content . "<menu_title><![CDATA[$txt]]></menu_title>\n";
  }

  //URL Alias related to the page:
  $url_alias_translation = lingotek_variable_get(lingotek_lingonode($node->nid, 'url_alias_translation'), 'lingotek_url_alias_translation', 0);
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
 * Menu access callback.
 *
 * Only display Lingotek tab for node types, which have translation enabled
 * and where the current node is not language neutral (which should span
 * all languages).
 */
function lingotek_access($node, $permission) {
  if (lingotek_supported_language($node->language) && lingotek_supported_type($node->type) && node_access('update', $node)) {
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
  return variable_get('language_content_type_' . $type, 0) == LINGOTEK_ENABLED;
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
