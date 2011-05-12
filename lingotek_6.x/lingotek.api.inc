<?php
// $Id:lingotek.api.inc,LingoTek$

/**
 * @file
 * Call the Lingotek APIs using CURL
 */

$GLOBALS['_lingotek_client'] = new LingotekSession();

/*
 * Get the Lingotek user's current project
 */
function lingotek_get_projects() {
  global $_lingotek_client;

  $options = array();

  if (!$_lingotek_client->isLoggedIn()) {
    return $options;
  }

  $list_projects = $_lingotek_client->request("listAssignedProjects", array());
  if ($list_projects->results == "success") {
    foreach ($list_projects->projectAssignments as $project) {
      $options[$project->projectId] = t($project->projectName);
    }
  }

  return $options;
}

/*
 * Get the Lingotek user's current vaults
 */
function lingotek_get_vaults() {
  global $_lingotek_client;

  $options = array();

  if (!$_lingotek_client->isLoggedIn()) {
    return $options;
  }

  $list_TM_vaults = $_lingotek_client->request("listTMVaults", array());
  if ($list_TM_vaults->results == "success") {
    foreach ($list_TM_vaults->personalVaults as $vault) {
      $options["Personal Vaults"][$vault->id] = t($vault->name);
    }
    foreach ($list_TM_vaults->publicVaults as $vault) {
      $options["Public Vaults"][$vault->id] = t($vault->name);
    }
  }

  return $options;
}

/*
 * Get the Lingotek user's phase templates
 */
function lingotek_get_phase_templates() {
  global $_lingotek_client;

  $options = array();
  $options[1] = t("Translation Only");
  $options[2] = t("Translation + 1 review");
  $options[3] = t("Translation + 2 reviews");
  $options[4] = t("Translation + 3 reviews");

  if (!$_lingotek_client->isLoggedIn()) {
    return $options;
  }

  //Add custom phase templates to the list:
  $list_phase_templates = $_lingotek_client->request("listPhaseTemplates", array());
  if ($list_phase_templates->results == "success") {
    foreach ($list_phase_templates->phaseTemplates as $phase_template) {
      $options[$phase_template->id] = t($phase_template->name);
    }
  }

  return $options;
}

/*
 * Get currently supported machine translation engines
 */
function lingotek_get_machine_translation_engines() {
  $engines = array();
  $engines[FALSE] = t("Disabled");
  $engines['google'] = t("Google MT");
  $engines['microsoft'] = t("Microsoft MT");
  return $engines;
}

/*
 * Retrieve a translation set from the database for the defined $tnid.
 *
 * @param $tnid
 *  Translation Node Id
 *
 * @return
 *  Return an associative array of the nodes translated, keyed by language
 */
function lingotek_node_get_translations($tnid) {
  static $translations = array();

  if (is_numeric($tnid) && $tnid) {
    if (!isset($translations[$tnid])) {
      $translations[$tnid] = array();

      $query = 'SELECT n.nid, n.title, n.language FROM {node} n LEFT JOIN lingotek ln ON n.nid = ln.nid AND ln.{lingokey} = \'tnid\' WHERE n.tnid = %d OR ln.lingovalue = \'%d\'';
      $result = db_query($query, $tnid, $tnid);
      while ($node = db_fetch_object($result)) {
        $translations[$tnid][$node->language] = $node;
      }
    }
    $source = node_load(array('nid' => $tnid));
    unset($translations[$tnid][$source->language]);
    return $translations[$tnid];
  }
}

/*
 * Retrieve a translation set from the database for the defined $tnid.
 *
 * @param $lang_prefix
 *  Language code for the wanted node
 * @param $tnid
 *  Translation Node Id
 *
 * @return
 *  Node object
 */
function lingotek_get_node($lang_prefix, $tnid) {
  //If it was published with MT, then the tnid was set:
  if (lingotek_lingonode($tnid, "publishWithMt") && lingotek_lingonode($tnid, "publishWithMt") != 'deferred') {
    return node_load(array('language' => $lang_prefix, 'tnid' => $tnid));
  }
  //Otherwise, we need to look it up in the lingonode table:
  else {
    $result = db_query("SELECT n.nid FROM {node} n INNER JOIN {lingotek} ln ON n.nid = ln.nid AND ln.lingokey = 'tnid' WHERE n.language = '%s' AND ln.lingovalue = '%s'", $lang_prefix, $tnid);
    $row = db_fetch_object($result);

    if ($row) {
      return node_load(array('nid' => $row->nid));
    }
  }
}

/*
 * Retrieve a tnid
 *
 * Helper method so that if a node has the tnid deferred until later, it will
 * get it from the database instead.
 *
 * @param $node
 *  Node object which we want the tnid for
 *
 * @return
 *  tnid
 */
function lingotek_tnid($node) {
  $tnid = lingotek_lingonode($node->nid, 'tnid');
  if ($tnid) {
    return $tnid;
  }
  else {
    return $node->tnid;
  }
}

/*
 * Link to the Workbench for the given phase
 *
 * @param $document_id
 *  Lingotek Document Id
 * @param $phase_id
 *  Lingotek Phase Id
 * @param $label
 *  Text to use for the link
 * @return
 *  link source
 */
function lingotek_workbench_phase_link($document_id, $phase_id, $label) {
  global $user;

  $arr = array
  (
    'community' => variable_get('lingotek_community', ''),
    'id' => $user->name,
    'time' => time(),
    'document' => $document_id,
    'phase' => $phase_id
  );

  $mode = 'cmsWorkbench.action';
  $json_str = json_encode($arr);
  $path = variable_get('lingotek_url', '') . "/lingopoint/portal/" . $mode . "?auth_json=" . urlencode($json_str) . "&hmac=" . urlencode(LingotekSession::create_mac($json_str));

  if (variable_get('lingotek_use_lightbox', FALSE)) {
    return l(t($label), $path, array('attributes' => array('rel' => 'lightframe')));
  }
  else {
    return l(t($label), '#', array('attributes' => array('onclick' => 'window.open(\'' . $path . '\'); return false;')));
  }
}

/*
 * Get the most recent export of the document in the target language
 *
 * @param $source_node
 *  Node containing the source language
 * @param $target_node
 *  Node containing the target language desired in the sync
 * @param $save
 *  (optional) TRUE
 *  Should the currently used target node be saved within this process?
 */
function lingotek_download_document($source_node, $target_node, $save = TRUE) {
  global $_lingotek_client;

  $locales = variable_get('lingotek_languages', array());

  $document_id = lingotek_lingonode($source_node->nid, 'document_id');
  $target_language = $locales[$target_node->language];

  if (!isset($target_language) || !isset($document_id)) {
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") downloadDocument NOT CALLED - (missing parameters) documentId: " . $document_id . " targetLanguage: " . $target_language);
    return;
  }
  //Get the finished document
  $params = array('documentId' => $document_id, 'targetLanguage' => $target_language);
  $file = $_lingotek_client->download("downloadDocument", $params);
  if (isset($file->results)) {
  	error_log("LINGOTEK: Error downloading document - " . __FILE__ . ":" . __LINE__);
    return;
  }
  $zip = new ZipArchive;
  $zip->open($file);
  $name = $zip->getNameIndex(0);
  $doc = $zip->getStream($name);
  $text = "";
  while (!feof($doc)) {
    $text .= fread($doc, 2);
  }
  fclose($doc);

  try {
    $xml = new SimpleXMLElement($text);
  }
  catch (Exception $e) {
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") downloadDocument FAILED - XML ERROR, text: " . $text);
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") downloadDocument FAILED - XML ERROR, Exception Message: " . $e->getMessage());
  }

  //Update the Node details to show the translated document
  $titles = $xml->xpath("title");
  $bodies = $xml->xpath("body");
  $target_node->title = decode_entities(lingotek_xml_decode($titles[0]));

  $target_node->body = lingotek_xml_decode($bodies[0]);
  $target_node->teaser = node_teaser($target_node->body, $source_node->format);

  $target_node->status = $source_node->status; //Publish the node if the source was published
  $target_node->promote = $source_node->promote;
  $target_node->sticky = $source_node->sticky;
  $target_node->tnid = $source_node->nid;

  if ($save) {
    node_save($target_node);
  }
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
