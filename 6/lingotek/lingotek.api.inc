<?php
/**
 * @file
 * Call the Lingotek APIs using CURL
 */

$GLOBALS['_lingotek_client'] = new LingotekSession();

/*
 * Get the Lingotek user's current projects
 */
function lingotek_get_projects() {
  global $_lingotek_client;

  $options = array();

  if (!$_lingotek_client->canLogIn()) {
    return $options;
  }

  $list_projects = $_lingotek_client->request("listAssignedProjects");
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

  if (!$_lingotek_client->canLogIn()) {
    return $options;
  }

  $list_TM_vaults = $_lingotek_client->request("listTMVaults");
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

  if (!$_lingotek_client->canLogIn()) {
    return $options;
  }

  //Add custom phase templates to the list:
  $list_phase_templates = $_lingotek_client->request("listPhaseTemplates");
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
  $result = db_query("SELECT n.nid FROM {node} n INNER JOIN {lingotek} ln ON n.nid = ln.nid AND ln.lingokey = 'tnid' WHERE n.language = '%s' AND ln.lingovalue = '%s'", $lang_prefix, $tnid);
  $row = db_fetch_object($result);

  if ($row) {
    return node_load(array('nid' => $row->nid));
  }
  //If it was published with MT, then the tnid was set:
  else {
    return node_load(array('language' => $lang_prefix, 'tnid' => $tnid));
  }
}

/*
 * Retrieve xliff for a document
 *
 * @param $doc_id
 *  Document Id as used by Lingotek
 *
 * @return
 *  xlf source text
 */
function lingotek_get_xliff($doc_id) {
  global $_lingotek_client;

  $xliff_text = "";
  $params = array('documentId' => $doc_id);
  return $_lingotek_client->download("downloadDocumentAsXliff", $params);
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
 * Save a segment
 *
 * Helper method so that if a node has the tnid deferred until later, it will
 * get it from the database instead.
 *
 * @param $source_text
 *  Source text
 * @param $target_text
 *  Target text that should be saved in the segment
 * @param $target_language
 *  Target language as used by lingotek (locale_country)
 * @param $doc_id
 *  Document Id
 * @return
 *  boolean, true if the api call was successful
 */
function lingotek_save_segment($source_text, $target_text, $target_language, $doc_id) {
  global $_lingotek_client;
  $param = array
  (
    "sourceText" => $source_text,
    "targetText" => $target_text,
    "targetLanguage" => $target_language,
    "documentId" => $doc_id,
    "overwrite" => FALSE,
  );
  $save_segment = $_lingotek_client->request("saveSegment", $param);
  return ($save_segment->results == "success");
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
  global $_lingotek_client, $_lingotek_locale;

  $document_id = lingotek_lingonode($source_node->nid, 'document_id');
  $target_language = $_lingotek_locale[$target_node->language];
  $params = array('documentId' => $document_id, 'targetLanguage' => $target_language);

  if (!isset($target_language) || !isset($document_id)) {
    lingotek_error('downloadDocument was not called because it was missing parameters', $params);
    return;
  }
  //Get the finished document
  $text = $_lingotek_client->download("downloadDocument", $params);

  try {
    $xml = new SimpleXMLElement($text);
  }
  catch (Exception $e) {
    lingotek_error("downloadDocument FAILED", array('xml' => $text, 'error' => $e->getMessage()));
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
 * Can we log in and are the credentials right for logging in?
 */
function lingotek_can_log_in() {
  global $_lingotek_client;
  if ($_lingotek_client->canLogIn()) {
    return t("True");
  }
  else {
    return t("False");
  }
}
