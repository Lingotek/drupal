<?php
/**
 * @file
 * Utilities
 */

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
  if (is_numeric($nid) && $nid) {
    //Return an array with all of the keys and values.
    if ($key == "") {
      $lingo_node = array();

      $result = db_query('SELECT n.lingokey, n.lingovalue FROM {lingotek} n WHERE n.nid = %d', $nid);
      while ($row = db_fetch_object($result)) {
        $lingo_node[$row->lingokey] = $row->lingovalue;
      }

      return $lingo_node;
    }
    //Get value for the specified key, only returns the first match
    elseif ($value == "") {
      $result = db_query("SELECT n.lingovalue FROM {lingotek} n WHERE n.nid = %d AND n.lingokey = '%s'", $nid, $key);
      $row = db_fetch_object($result);

      if ($row) {
        return $row->lingovalue;
      }
      else {
        return FALSE;
      }
    }
    //Insert the specified key and value
    else {
      //insert or update
      if (lingotek_lingonode($nid, $key) === FALSE) { //Insert
        db_query("INSERT INTO {lingotek} VALUES(%d, '%s', '%s')", $nid, $key, $value);
      }
      else { //Update
        db_query("UPDATE {lingotek} SET lingovalue = '%s' WHERE nid = %d AND lingokey = '%s'", $value, $nid, $key);
      }
    }
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
  $text = str_replace("&lt;", "<", $text);
  $text = str_replace("&gt;", ">", $text);
  $text = str_replace("&apos;", "'", $text);
  $text = str_replace("&quot;", "\"", $text);
  $text = str_replace("&amp;", "&", $text);
  return $text;
}

/*
 * Match Lingotek's language code to what drupal uses
 *
 * @param $string
 *  Language Code returned from Lingotek
 * @return
 *  Language Code as used by drupal (only uses default, not dynamic)
 */
function lingotek_language_matching($string) {
  switch ($string) {
    case 'pt_BR':
      return "pt-br";
    case 'pt_PT':
      return "pt-pt";
    case 'zh_CN':
      return "zh-hans";
    case 'zh_TW':
      return "zh-hant";
    default:
      return preg_replace("/_.*$/", "", $string);
  }
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
  $backtrace = debug_backtrace();
  $location = $backtrace[$depth]['file'] . ':' . $backtrace[$depth]['line'];
  $function = $backtrace[$depth + 1]['function'];
  $args = json_encode($backtrace[$depth + 1]['args']);

  $data_output = "";
  if (isset($data)) {
    $data_output = json_encode($data);
  }

  watchdog
  (
    'lingotek',
    '<span style="word-break: break-all;"><b>MESSAGE:</b> %msg <br /><b>DATA:</b> %data <br /><b>FILE:</b> %location<br /><b>FUNCTION:</b>%function<br /><b>ARGS:</b> %args</span>',
    array
    (
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
