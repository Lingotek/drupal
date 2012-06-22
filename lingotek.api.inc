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
    return;
  }

   $url_alias_translation = lingotek_variable_get(lingotek_lingonode($node->nid, 'url_alias_translation'), 'lingotek_url_alias_translation', 0);

  foreach ($xml as $tag => $content) {
    if ($tag == 'menu_title') {
      //TODO handle menu translation
    }
    else if ($tag == 'url_alias' && $url_alias_translation == 1) {
      $target = check_plain($content);

      //URL Alias related to the page:
      $conditions = array('source' => 'node/' . $node->nid);
      if ($node->language != LANGUAGE_NONE) {
        $conditions['language'] = $node->language;
      }
      $path = path_load($conditions);
      if ($path !== FALSE) {
        $conditions['language'] = $target_language;
        if ($path['alias'] != $target) {
          $original = path_load($conditions);
          $conditions['alias'] = $target;
          if ($original === FALSE) {
            path_save($conditions);
          }
          else {
            path_delete($original);
            path_save($conditions);
          }
        }
      }
    }
    else {
      $field = field_info_field($tag);
      if (isset($field) && array_key_exists('lingotek_translatable', $field) && $field['lingotek_translatable'] == 1) {
        $data = & $node->$tag;
        $index = 0;
        foreach ($content as $text) {
          $data[$target_language][$index]['value'] = decode_entities(lingotek_xml_decode($text));
          if (array_key_exists($node->language, $data)) {
            if(array_key_exists('format', $data[$node->language][0])) {
              $data[$target_language][$index]['format'] = $data[$node->language][0]['format'];
            }
            if(array_key_exists('summary', $data[$node->language][0])) {
              $data[$target_language][$index]['summary'] = $data[$node->language][0]['summary'];
            }
          }
          $index++;
        }
      }

      //Set URL alias
      $url_alias_translation = lingotek_variable_get(lingotek_lingonode($node->nid, 'url_alias_translation'), 'lingotek_url_alias_translation', 0);
      if ($tag == 'title_field' && $url_alias_translation == 2 && module_exists('pathauto') && $node->language != LANGUAGE_NONE) {
        module_load_include('inc', 'pathauto');
        $uri = entity_uri('node', $node);
        pathauto_create_alias('node', 'update', $uri['path'], array('node' => clone $node), $node->type, $target_language);
      }
    }
  }

  //Fix for pathauto expecting the form:
  $node->path = path_load(array('source' => 'node/' . $node->nid, 'language' => $node->language));
  $node->path['pathauto'] = 0;
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

/**
 * Add a document to the Lingotek platform.
 *
 * This saves a node's source language to the Lingotek platform.
 *
 * @param object $node
 *   The Drupal node being added to Lingotek.
 */
function lingotek_add_document($node) {
  global $_lingotek_client, $_lingotek_locale;

  $param = array(
    'projectId' => $node->lingotek_project_id,
    'documentName' => $node->title,
    'documentDesc' => $node->title,
    'format' => 'XML',
    'sourceLanguage' => $_lingotek_locale[$node->language],
    'tmVaultId' => (!empty($node->lingotek_vault_id)) ? $node->lingotek_vault_id : variable_get('lingotek_vault', 1),
    'content' => lingotek_xml_node_body($node),
    'note' => url('node/' . $node->nid, array('absolute' => TRUE, 'alias' => TRUE))
  );

  $output = $_lingotek_client->request('addContentDocument', $param);
  if ($output->results == 'success') {
    lingotek_lingonode($node->nid, 'document_id_' . $node->language, $output->id);
    lingotek_trace(__FUNCTION__, array('nid' => $node->nid, 'document_id_' . $node->language => $output->id));
  }
}

/**
 * Add target languages to the Lingotek document associated with a node.
 *
 * This saves any missing target languages to the Lingotek platform.
 *
 * @param $node
 *   Node being checked.
 *
 * @return bool
 *   TRUE on success, FALSE on error.
 */
function lingotek_add_missing_targets($node) {
  global $_lingotek_client, $_lingotek_locale;

  $lingotek_workflow_id = lingotek_lingonode($node->nid, 'workflow_id');
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
    if ($target_language->enabled && lingotek_supported_language($target_language->language) && 
      !in_array($_lingotek_locale[$target_language->language], $languages_already_added) && 
      $node->language != $target_language->language) {
      
      $translation_target_id = LingotekApi::instance()->addTranslationTarget($document_id, $target_language->language);
    }
    else {
      lingotek_trace("lingotek_add_missing_targets skipping language", 
        array(
          'language' => $target_language->language,
          'enabled' => $target_language->enabled,
          'supported' => lingotek_supported_language($target_language->language),
          'added' => lingotek_supported_language($target_language->language) && in_array($_lingotek_locale[$target_language->language], $languages_already_added),
          'isTarget' => $node->language != $target_language->language
        )
      );
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
    $param = array(
      'project_id' => $project_id,
      'index_id' => $vault_id
    );
    $_lingotek_client->request('addProjectTMVault', $param);
  }
}

/*
 * Analyze the Project
 */
function lingotek_analyze_project() {
  global $_lingotek_client;
  $_lingotek_client->request("analyzeProject", array('projectId' => variable_get('lingotek_project', -1)));
}

#GETTERS

/*
 * Get available URL alias methods
 */
function lingotek_get_url_alias_translations() {
  $methods = array();
  $methods[0] = t("Don't translate");
  $methods[1] = t("Translate the URL alias");
  $methods[2] = t("Use the translated page title");
  return $methods;
}

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
function lingotek_get_workbench_url($node, $language, $label = FALSE, $force = FALSE) {
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
        if ($phase->percentComplete < 100 || $force) {
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

      $link[$lang] = array(
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
