<?php
/**
 * @file
 * Call the Lingotek APIs
 */

$GLOBALS['_lingotek_client'] = new LingotekSession();

/*
 * Update the document on the Lingotek platform with changes made on drupal
 *
 * This adds/removes content from the source document in the Lingotek platform.
 *
 * @param $node
 *  Node being updated
 */
function lingotek_update_document($node) {
  global $_lingotek_client;

  $param = array
  (
    'documentId' => lingotek_lingonode($node->nid, 'document_id_' . $node->language),
    'content' => lingotek_xml_node_body($node)
  );

  $_lingotek_client->request('updateContentDocument', $param);
}

/*
 * Download the translations from the Lingotek platform
 *
 * This updates a node's content with translations for target languages
 *
 * @param $node
 *  Node being updated/synchronized
 * @param $target_language
 *  Target language to be updated/synchronized
 */
function lingotek_download_document(&$node, $target_language) {
  global $_lingotek_client, $_lingotek_locale;

  $document_id = lingotek_lingonode($node->nid, 'document_id_' . $node->language);
  lingotek_trace('lingotek_download_document', array("document_id_" . $node->language => $document_id, "language" => $target_language));

  //CAREFUL of alternate values for $use_source, must be string 'true' for api, not boolean
  $use_source = lingotek_lingonode($node->nid, 'use_source');
  if ($use_source === FALSE) {
    $use_source = variable_get('lingotek_use_source', TRUE);
  }
  if ($use_source == '1') {
    $use_source = 'TRUE';
  }

  //Handle the language being passed as the value lingotek uses already:
  if (!array_key_exists($target_language, $_lingotek_locale)) {
    $target_language = lingotek_drupal_language($target_language);
  }

  $params = array
  (
    'documentId' => $document_id,
    'targetLanguage' => $_lingotek_locale[$target_language],
    'useSource' => $use_source,
  );

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
  $title = decode_entities(lingotek_xml_decode($titles[0]));
  $body = lingotek_xml_decode($bodies[0]);

  node_object_prepare($node);
  lingotek_lingonode($node->nid, $target_language . '_title', $title);

  $node->body[$target_language][0]['value'] = $body;
  if (array_key_exists($node->language, $node->body)) {
    $node->body[$target_language][0]['format'] = $node->body[$node->language][0]['format'];
    $node->body[$target_language][0]['summary'] = $node->body[$node->language][0]['summary'];
  }

  node_save($node);
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
    "overwrite" => 0,
  );
  $save_segment = $_lingotek_client->request("saveSegment", $param);
  return ($save_segment->results == "success");
}

#API ADDERS

/*
 * Add a document to the Lingotek platform
 *
 * This saves a node's source language to the Lingotek platform.
 *
 * @param $node
 *  Node being added
 */
function lingotek_add_document($node) {
  global $_lingotek_client, $_lingotek_locale;

  $param = array
  (
    'projectId' => variable_get('lingotek_project', ''),
    'documentName' => $node->title,
    'documentDesc' => $node->title,
    'format' => 'XML',
    'sourceLanguage' => $_lingotek_locale[$node->language],
    'tmVaultId' => variable_get('lingotek_vault', 1),
    'content' => lingotek_xml_node_body($node),
  );

  $output = $_lingotek_client->request('addContentDocument', $param);
  if ($output->results == "success") {
    lingotek_lingonode($node->nid, 'document_id_' . $node->language, $output->id);
    lingotek_trace('lingotek_add_document', array('nid' => $node->nid, 'document_id_' . $node->language => $output->id));
  }
}

/*
 * Add target languages
 *
 * This saves any missing target languages to the Lingotek platform.
 *
 * @param $node
 *  Node being checked
 */
function lingotek_add_missing_targets($node) {
  global $_lingotek_client, $_lingotek_locale;

  $phase_template_id = lingotek_lingonode($node->nid, 'phase_template_id');
  $document_id = lingotek_lingonode($node->nid, 'document_id_' . $node->language);

  $languages_already_added = array();

  $params = array('documentId' => $document_id);
  $output = $_lingotek_client->request('getDocument', $params);
  if ($output->results == "success") {
    foreach ($output->translationTargets as $target) {
      array_push($languages_already_added, $target->language);
    }
  }
  else {
    return FALSE;
  }

  foreach (language_list() as $target_language) {
    if ($target_language->enabled && lingotek_supported_language($target_language->language) && !in_array($_lingotek_locale[$target_language->language], $languages_already_added) && $node->language != $target_language->language) {
      $translation_target_id = lingotek_add_target_language($document_id, $target_language->language);
      lingotek_add_phase_template($translation_target_id, $phase_template_id);
    }
    else {
      lingotek_trace("lingotek_add_missing_targets skipping language", array('language' => $target_language->language, 'enabled' => $target_language->enabled, 'supported' => lingotek_supported_language($target_language->language), 'added' => lingotek_supported_language($target_language->language) && in_array($_lingotek_locale[$target_language->language], $languages_already_added), 'isTarget' => $node->language != $target_language->language));
    }
  }
  return TRUE;
}

/*
 * Apply the phase template to the Lingotek document
 *
 * This saves the chosen workflow to the Lingotek platform.
 *
 * @param $translation_target_id
 *  Translation Target Id (Id for the target language stored with the document associated with a node)
 * @param $phase_template_id
 *  Workflow Id to be added
 */
function lingotek_add_phase_template($translation_target_id, $phase_template_id) {
  global $_lingotek_client;

  $params = array('translationTargetId' => $translation_target_id, 'phaseTemplateId' => $phase_template_id);
  $_lingotek_client->request("applyPhaseTemplate", $params);
}

/*
 * Create a project and return it's id.
 * @param $name
 *  Project name being created
 */
function lingotek_add_project($name) {
  global $_lingotek_client;
  $output = $_lingotek_client->request('addProject', array('projectName' => $name));
  if ($output->results == "success") {
    variable_set('lingotek_project', $output->id);
    return $output->id;
  }
}

/*
 * Add target language
 *
 * This saves a target languages to the Lingotek platform.
 *
 * @param $document_id
 *  Document Id as stored in the Lingotek platform for a specific drupal node.
 * @param $target_language
 *  Language to be added
 * @return translation target id
 */
function lingotek_add_target_language($document_id, $target_language) {
  global $_lingotek_client, $_lingotek_locale;
  lingotek_trace('lingotek_add_target_language', array('document_id' => $document_id, 'target_language' => $target_language));

  $add_translation_target = $_lingotek_client->request("addTranslationTarget", array('documentId' => $document_id, 'targetLanguage' => $_lingotek_locale[$target_language]));
  if ($add_translation_target->results == "success") {
    return $add_translation_target->id;
  }
  else {
    return -1;
  }
}

/*
 * Create a vault, and return it's id.
 * @param $name
 *  Vault name being created
 */
function lingotek_add_vault($name) {
  global $_lingotek_client;

  $output = $_lingotek_client->request('addTMVault', array('tmVaultName' => $name));
  if ($output->results == "success") {
    variable_set('lingotek_vault', $output->id);
    return $output->id;
  }
}

/*
 * Add the current vault to the current project.  It doesn't hurt to call this more than once.
 */
function lingotek_add_vault_to_project() {
  global $_lingotek_client;

  $vault_id = variable_get('lingotek_vault', '');
  $project_id = variable_get('lingotek_project', '');
  if ($vault_id != '' && $project_id != '') {
    $param = array
    (
      'project_id' => $project_id,
      'index_id' => $vault_id
    );
    $_lingotek_client->request('addProjectTMVault', $param);
  }
}

#GETTERS

/*
 * Get the Lingotek user's cms key for the community they are currently logged in with
 */
function lingotek_get_cms_key() {
  global $_lingotek_client;

  $output = $_lingotek_client->request("getCMSKey");
  if ($output->results == "success") {
    variable_del('lingotek_password');
    return $output->cms;
  }
  else {
    return "";
  }
}

/*
 * Get the Lingotek user's current communities
 */
function lingotek_get_communities() {
  global $_lingotek_client;

  $options = array();

  if (!$_lingotek_client->canLogIn()) {
    return $options;
  }

  $list_communities = $_lingotek_client->request("listCommunities", array());
  if ($list_communities->results == "success") {
    foreach ($list_communities->communities as $community) {
      $options[$community->id] = t($community->name);
    }
  }

  return $options;
}

/*
 * Get the target language objects for a Lingotek document associated with a node
 */
function lingotek_get_document_targets($document_id, $flush_cache = FALSE) {
  global $_lingotek_client;
  $results = array();
  if (lingotek_do_cache() && !$flush_cache && ($cache = cache_get('lingotek_targets_' . $document_id)) && !empty($cache->data) && time() < $cache->expire) {
    lingotek_trace("lingotek_get_document_targets USING CACHE", array('document_id' => $document_id, 'flushCache' => $flush_cache));
    $results = $cache->data;
  }
  else {
    $params = array('documentId' => $document_id);
    $output = $_lingotek_client->request("getDocument", $params);
    if ($output->results == "success") {
      foreach ($output->translationTargets as $target) {
        $results[lingotek_drupal_language($target->language)] = $target;
      }
    }
    lingotek_trace("lingotek_get_document_targets GENERATING NEW CACHE DATA getDocument", array('document_id' => $document_id, 'flushCache' => $flush_cache));
    cache_set('lingotek_targets_' . $document_id, $results, 'cache', time() + 900);
  }
  return $results;
}

/*
 * Get machine translation engines available to this module
 */
function lingotek_get_machine_translation_engines() {
  $engines = array();
  $engines[FALSE] = t("Disabled");
  $engines['google'] = t("Google MT");
  $engines['microsoft'] = t("Microsoft MT");
  return $engines;
}

/*
 * Get the phase name
 *
 * This fetches a workflow step's name (known as a Phase in the Lingotek platform)
 *
 * @param $phase_id
 *  Id for the workflow step
 * @return
 *  name for the workflow step (phase name)
 */
function lingotek_get_phase_name($phase_id) {
  global $_lingotek_client;

  $params = array('phaseId' => $phase_id);
  $output = $_lingotek_client->request("getPhase", $params);
  if ($output->results == "success") {
    return $output->name;
  }
  else {
    return "";
  }
}

/*
 * Get the Lingotek user's phase (workflow) templates
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

  //Add custom phase templates:
  $list_phase_templates = $_lingotek_client->request("listPhaseTemplates");
  if ($list_phase_templates->results == "success") {
    foreach ($list_phase_templates->phaseTemplates as $phase_template) {
      $options[$phase_template->id] = t($phase_template->name);
    }
  }

  return $options;
}

/*
 * Get the Lingotek user's current projects
 */
function lingotek_get_projects() {
  global $_lingotek_client;

  $options = array();

  if (!$_lingotek_client->canLogIn()) {
    return $options;
  }

  $list_projects = $_lingotek_client->request("listProjects");
  if ($list_projects->results == "success") {
    foreach ($list_projects->projects as $project) {
      $options[$project->id] = t($project->name);
    }
  }

  return $options;
}

/*
 * Get available synchronization methods for keeping nodes up-to-date
 */
function lingotek_get_sync_methods() {
  $methods = array();
  $methods[0] = t("Never");
  $methods[1] = t("Always");
  $methods[100] = t("100%");
  return $methods;
}

/*
 * Get the translation target
 *
 * This fetches an target language object for a specific document.
 *
 * @param $translation_target_id
 *  Id for the target language object
 * @return
 *  Object representing a target language for a specific document in the lingotek platform
 */
function lingotek_get_translation_target($translation_target_id) {
  global $_lingotek_client;

  $params = array('translationTargetId' => $translation_target_id);
  $output = $_lingotek_client->request("getTranslationTarget", $params);
  if ($output->results == "success") {
    return $output;
  }
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
 * Get the url to open the translation workbench
 *
 * This fetches a link
 *
 * @param $node
 *  Node being translated
 * @param $language
 *  Target language being translated to
 * @param $label
 *  default FALSE
 *  TRUE | FALSE | String used as a label for the link.
 * @return
 *  Either a link pointing the the url, or the url itself if $label is FALSE
 */
function lingotek_get_workbench_url($node, $language, $label = FALSE) {
  global $_lingotek_client, $_lingotek_locale;

  if (!isset($_lingotek_locale[$language])) {
    return "";
  }

  $link = array();
  if (lingotek_do_cache() && ($cache = cache_get('lingotek_link_' . $node->nid . '-' . $node->language . "-$label")) && !empty($cache->data) && time() < $cache->expire && array_key_exists($language, $cache->data)) {
    lingotek_trace("lingotek_get_workbench_url USING CACHE", array('nid' => $node->nid, 'source' => $node->language, 'target' => $language, 'label' => $label));
    $link = $cache->data;
  }
  else {
    lingotek_trace("lingotek_get_workbench_url GENERATING NEW CACHE", array('nid' => $node->nid, 'source' => $node->language, 'target' => $language, 'label' => $label));
    $document_id = lingotek_lingonode($node->nid, 'document_id_' . $node->language);
    $targets = lingotek_get_document_targets($document_id, TRUE); //Make sure we get the current phases for the links and not out of date ones (so caches don't combine)
    if (count($targets) == 0) {
      return "";
    }

    //We're getting all links now, and other calls will pull the cached ones:
    foreach ($targets as $lang => $translation_target) {
    	lingotek_trace("lingotek_get_workbench_url TARGETS", array("lang" => $lang, "translation_target" => $translation_target));
      $phases = lingotek_get_translation_target($translation_target->id)->phases;

      $phase_id = -1;
      $which_phase = 0;
      foreach ($phases as $phase) {
        if ($phase->percentComplete < 100) {
          $phase_id = $phase->id;
          break;
        }
        $which_phase++;
      }

      $l = "";
      if ($phase_id != -1) {
        if ($label === FALSE) {
          $l = lingotek_workbench_url($node, $phase_id);
        }
        else {
          $path = lingotek_workbench_url($node, $phase_id);
          if ($label === TRUE) {
            $label = lingotek_get_phase_name($phase_id);
          }
          $l = l(t($label), '#', array('attributes' => array(
          	'onclick' => 'window.open(\'' . $path . '\'); return false;',
          	'onmouseover' => 'jQuery("#node-' . $node->nid . '").addClass("highlight");',
          	'onmouseout' => 'jQuery("#node-' . $node->nid . '").removeClass("highlight");',
          )));
        }
      }

      $link[$lang] = array
      (
        'which_phase' => $which_phase,
        'link' => $l,
      );
    }
    $cache = 'lingotek_link_' . $node->nid . '-' . $node->language . "-$label";
    lingotek_trace("lingotek_get_workbench_url cache_set", array("links" => $link, "cache" => $cache));
    cache_set($cache, $link, 'cache', time() + 900);
  }

  if ($link[$language]['which_phase'] == 0 && !user_access('translation')) {
    return "";
  }
  if ($link[$language]['which_phase'] > 0 && !user_access('review')) {
    return "";
  }
  lingotek_trace("lingotek_get_workbench_url return", array('language' => $language, 'link' => $link));
  return $link[$language]['link'];
}

/*
 * Get the xliff information of the node
 *
 * This fetches an xliff representation of the source document.
 *
 * @param $doc_id
 *  Document id that associates the node to the Lingotek platform
 * @return
 *  xml text of the xliff
 */
function lingotek_get_xliff($doc_id) {
  global $_lingotek_client;

  $xliff_text = "";
  $params = array('documentId' => $doc_id);
  return $_lingotek_client->download("downloadDocumentAsXliff", $params);
}
