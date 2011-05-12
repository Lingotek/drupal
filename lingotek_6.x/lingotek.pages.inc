<?php
// $Id:lingotek.pages.inc,LingoTek$
/**
 * @file
 * Displays progress of the community translation
 */

require_once("lingotek.sync.inc");

drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/jquery.loadmask.css');
drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/progress.css');
drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/jquery.loadmask.min.js');
drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.js');

/*
 * Update the nodes and Upload the document to LingoTek.
 *
 * @param $source
 *  The source language's node
 *
 * @return
 *  HTML to display on the Lingotek tab
 */
function lingotek_summary($source) {
  global $_lingotek_client;

  if (!$_lingotek_client->isLoggedIn()) {
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") login FAILED message: " . $login->message);
    return t("Lingotek Login Failed") . ": " . $login->message;
  }

  $current_nid = $source->nid;

  //Make sure we are looking at targets relative to the source language.
  if ($source->tnid) {
    $tnid = $source->tnid;
    $source = node_load(array('nid' => $source->tnid));
  }
  else {
    $tnid = $source->nid;
  }
  //If the source Node has been deleted, prevent further community translation:
  if (!$source) {
    return t("The source language document has been deleted.  Translation has been disabled.");
  }

  //Update the node's information with what Lingotek has on it's translation progress.
  //Response format: $mt_queue[$lang] = $target_node->nid;
  $mt_queue = lingotek_update_nodes($source);

  $doc_id = lingotek_lingonode($source->nid, 'document_id');
  $engine = lingotek_lingonode($tnid, "publishWithMt");
  $mt_queue_source = "<div doc=\"$doc_id\" node=\"" . $source->nid . "\" engine=\"$engine\" id=\"lingotek\" style=\"display: none;\">";
  foreach ($mt_queue as $lang => $node) {
    $mt_queue_source .= "<input type=\"hidden\" name=\"$lang\" value=\"$node\" />";
  }

  $xliff_text = "";
  if (count($mt_queue) || user_access('machine_translation')) {
    $file_uri = $_lingotek_client->download("downloadDocumentAsXliff", array('documentId' => $doc_id));
    $file = fopen($file_uri, "r");
    if ($file) {
      while (!feof($file)) {
        $xliff_text .= fread($file, 2);
      }
    }
    fclose($file);
  }
  $mt_queue_source .= "<textarea id=\"lingotek_xliff\">" . $xliff_text . "</textarea>";

  $mt_queue_source .= "</div>";

  //Adding javascript strings to be localized:
  $mt_queue_source .= '<div id="lingotek-error" style="display: none;">' . t("There was an error importing the machine translation.") . '</div>';
  $mt_queue_source .= '<div id="lingotek-cancel" style="display: none;">' . t("Cancel") . '</div>';
  $mt_queue_source .= '<div id="lingotek-info" style="display: none;">' . t("Lingotek is machine translating the selected page(s). This process may take several minutes.\n\nYou may continue to work during this process. If you stay on this page, it will reload once the process is done.") . '</div>';
  $mt_queue_source .= '<div id="lingotek-warn-mt" style="display: none;">' . t("Machine translation replaces page text stored in Drupal with machine translated text. Machine translated text is immediately published, regardless of the translation's completion status.\n\nIf you have edited the target language page(s) in Drupal since last syncing with Lingotek, those edits will be lost.") . '</div>';
  $mt_queue_source .= '<div id="lingotek-warn-sync" style="display: none;">' . t("Syncing replaces page text stored in Drupal with the translated text stored in the Lingotek cloud. Synced text is immediately published, regardless of the translation's completion status.\n\nIf you have edited the target language page(s) in Drupal since last syncing with Lingotek, those edits will be lost.") . '</div>';
  $mt_queue_source .= '<div id="lingotek-loading" style="display: none;">' . t("Translating with Machine Translation ...") . '</div>';

  //Get current Target languages for the node
  $output = $_lingotek_client->request('listTranslationTargets', array('documentId' => $doc_id));
  $phases = array();
  if ($output->results == "success") {
    foreach ($output->translationTargets as $target) {
      $language = lingotek_language_matching($target->language);

      $list_phases = $_lingotek_client->request('listPhases', array('translationTargetId' => $target->id));
      if ($list_phases->results == "success") {
        foreach ($list_phases->phases as $phase) {
          $phases[$language][$phase->order] = $phase;
        }
      }
      else {
        error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") listPhases FAILED - translationTargetId: " . $target->id);
        error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") listPhases message: " . $output->message);
      }

      foreach ($target->phases as $phase) {
        $phases[$language][$phase->order]->percentComplete = $phase->percentComplete;
      }
    }
  }
  else {
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") listTranslationTargets FAILED - documentId: " . $doc_id);
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") listTranslationTargets message: " . $output->message);
  }



  //Create a table to show current translation progress:
  $list = language_list();
  $header = array(t('Language'), t('Title'), t('Phase'), t('Status'), t('Published'));
  $locales = variable_get('lingotek_languages', array());

  //For each target language, or for each row in the table...
  $translations = lingotek_node_get_translations($tnid);
  foreach ($translations as $node) {
    if ($list[lingotek_language_matching($node->language)]->enabled != "1") {
      continue;
    }

    $node = node_load(array('nid' => $node->nid));
    $operation = "";
    $current_phase = 0;
    $progress = 0;
    $order = 0;
    $native = $list[lingotek_language_matching($node->language)]->native;
    $check = "";

    //Current Phase Progress: (phases must be returned in order, or this will break)
    $language_phases = $phases[$node->language];
    if ($language_phases) {
      //Get the current phase the target is on
      foreach ($language_phases as $key => $value) {
        if ($value->percentComplete < 100) {
          $progress = $value->percentComplete;
          $order = $value->order;
          break;
        }
        $current_phase++;
      }

      $num_phases = count($language_phases);
      $label = "";
      $phase_id = -1;
      if ($current_phase != $num_phases) {
        $this_phase = $language_phases[$order];
        $label = t($this_phase->name);
        $phase_id = $this_phase->id;
      }

			$percent = lingotek_lingonode($node->nid, 'percent_complete');
			if($percent == "")
			{
				$percent = 0;
			}
      $total_percent = $percent . "%";
      $operation = $label;

      //Show nodes that have completed the translation process.
      if ($percent == 100) {
        $operation = t('Completed');
      }
      else {
        //First phase is always a translation phase, so give users with translator permissions the link
        if ($current_phase == 0) {
          if (user_access('translation_phase')) {
            $operation = lingotek_workbench_phase_link($doc_id, $phase_id, $label);
          }
        }
        //All other phases are review phases, so give users with review permissions the link
        else {
          if (user_access('review_phase')) {
            $operation = lingotek_workbench_phase_link($doc_id, $phase_id, $label);
          }
        }
      }
    }
    else {
      //Possible cause: The system automatically set this node to the default language when the admin deleted a language from the system and the node is no longer associated with the target on Lingopoint
      //Readding the language to the system will create the node again and it will automatically be associated with lingopoint again.
      //The target language is removed from lingopoint that this node was pointing to in lingotek.sync.inc
      node_delete($node->nid);
      continue;
    }

    if (user_access('machine_translation') || user_access('sync')) {
      $check = '<input tag="lingotek-mt-checkbox" type="checkbox" node="' . $node->nid . '" language="' . $locales[$node->language] . '" />';
    }


    $title = $node->title;
    //Don't link to itself.
    if ($node->nid != $current_nid) {
      //Let's create the link manually so that Drupal doesn't overwrite the url to the current local:
      $title = "<a href=\"" . url('node/'. $node->nid, array('language' => $list[$node->language])) . "\">" . $node->title . "</a>";
    }

    $published = ($node->status) ? "Yes" : "No";
    $row = array($native, $title, $operation, $total_percent, $published);


    if ($check != "") {
      array_unshift($row, $check);
    }
    $rows[] = $row;
  }

  //Let's keep the order consistent on the table.
  sort($rows);

  $title = "<a href=\"" . url('node/'. $source->nid, array('language' => $list[$source->language])) . "\">" . $source->title . "</a>";
  drupal_set_title(t('Collaborative Translations of') . ' ' . $title, PASS_THROUGH);

  $download = "";
  if (user_access('sync')) {
    $download = '<input type="button" value="Sync" onclick="lingotek.update_checked();" />';
  }
  $mt = "";
  if (user_access('machine_translation')) {
    $available_engines = lingotek_get_machine_translation_engines();
    $enabled_engines = variable_get('lingotek_available_mt_options', array_keys($available_engines));
    $engines = array();
    $select = "";
    foreach ($enabled_engines as $engine) {
      $engines[$engine] = $available_engines[$engine];
    }
    unset($engines[0]);
    if (count($engines) == 1) {
      $keys = array_keys($engines);
      $mt .= '<input id="lingotek-mt-engine" tag="lingotek-engine" type="hidden" value="' . $keys[0] . '" />';
    }
    else {
      foreach ($engines as $value => $label) {
        $select .= '<option tag="lingotek-engine" value="' . $value . '">' . t($label) . '</option>';
      }
      $mt .= "<select id=\"lingotek-mt-engine\">$select</select>";
    }
    $mt .= '<input id="lingotek-mt-button" type="button" onclick="lingotek.mt_run();" value="' . t('Machine Translate') . '" />';
  }
  if (user_access('sync') || user_access('machine_translation')) {
    array_unshift($header, '<input type="checkbox" onclick="lingotek.check_all(this);" />');
  }
  $table = theme('table', $header, $rows);

  return $table . $mt_queue_source . $mt . '<br />' . $download;
}

/*
 * AJAX call to submit XLIFF to lingotek for machine translation
 *
 * @param $nid
 *  Node Id for the target node
 *
 * @return
 *  json object with result information (status and message)
 */
function lingotek_mt_ajax($nid) {
  global $_lingotek_client;

  header('Content-Type: text/json; charset=utf-8');

  $xliff = $_POST['xliff'];
  $doc_id = $_POST['docid'];
  $engine = $_POST['engine'];

  if (!$_lingotek_client->isLoggedIn()) {
    drupal_json(array('status' => 1, 'message' => "FAILED"));
    return;
  }

  $file_name = tempnam("temp", "Xliff");
  $file_handle = fopen($file_name, "w");
  fwrite($file_handle, $xliff);

  $source_node = node_load(array('nid' => $nid));

  $data = array
  (
    'tmVaultId' => variable_get('lingotek_vault', 1),
    'name' => 'Drupal Machine Translation: ' . $source_node->title
  );

  $output = $_lingotek_client->upload
  (
    'uploadXliffToTMVault',
    $data,
    $file_name
  );

  if ($output->results == "success") {
    $vault_id = $output->id;

    //Wait until the TMVault is done uploading.
    while (TRUE) {
      $poll = $_lingotek_client->request("pollTMVaultImport", array('tmVaultUploadId' => $vault_id));
      if ($poll->results == "success") {
        if ($poll->progress == 100) {
          break;
        }
      }
      sleep(3);
    }
    $analyze = $_lingotek_client->request("analyzeDocument", array('documentId' => $doc_id));
    if ($analyze->results != "success") {
      error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") analyzeDocument - documentId: " . $doc_id);
      return;
    }

    //Give it a sec to update the progress complete in case you run this more than once.
    sleep(3);

    //Wait until the document is done being analyzed.
    while (TRUE) {
      $count = 0;
      $poll = $_lingotek_client->request("pollDocumentAnalysis", array('documentId' => $doc_id));

      if ($poll->results == "success") {
        foreach ($poll->translations as $translation) {
          if ($translation->statsProgress == 100) {
            $count++;
          }
        }
        if (count($poll->translations) == $count) {
          break;
        }
      }
      sleep(3);
    }
    //Download the finished results, either for the specified language, or every target language.
    if (isset($_POST['targetNode']) ) {
      foreach ($_POST['targetNode'] as $target_node) {
        $target_node = node_load(array('nid' => $target_node));
        lingotek_download_document($source_node, $target_node);
      }
    }
    else {
      $translations = lingotek_node_get_translations($nid);
      foreach ($translations as $node) {
        $target_node = node_load(array('nid' => $node->nid));
        lingotek_download_document($source_node, $target_node);
      }
    }

    //Remove the google translations from the vault.  They're not reliable translations to have in TM Vaults
    $_lingotek_client->request("removeUploadedTMFromVault", array('id' => $vault_id));
    if (!lingotek_lingonode($nid, "publishWithMt")) {
      lingotek_lingonode($nid, "publishWithMt", 'deferred');
    }
    drupal_json(array('status' => 0, 'message' => "DONE processing node " . $nid . ". " . $output->count . " segments exported."));
  }
  else {
    lingotek_lingonode($nid, 'publishWithMt', 'deferred');
    drupal_json(array('status' => 1, 'message' => "FAILED"));
  }
}

/*
 * Cancel First MT call
 */
function lingotek_mt_cancel($nid) {
	lingotek_lingonode($nid, 'publishWithMt', 'deferred');
}
/*
 * Calls lingotek_download_ajax_each for each target node passed in
 *
 * Sync the current translations from Lingotek to Drupal for the target nodes
 */
function lingotek_download_ajax() {
  if (isset($_POST['targets'])) {
    foreach ($_POST['targets'] as $target) {
      if (!lingotek_download_ajax_each($target)) {
        drupal_json(array('status' => 1, 'message' => "FAILED"));
        return;
      }
    }
  }
  drupal_json(array('status' => 0, 'updated' => $_POST['targets']));
}

/*
 * Sync the current translations from Lingotek to Drupal for the target node
 *
 * @param $nid
 *  Node Id for the source node
 */
function lingotek_download_ajax_each($nid) {
  global $_lingotek_client;

  if (!$_lingotek_client->isLoggedIn()) {
    return FALSE;
  }

  $target_node = node_load(array('nid' => $nid));
  $source_node = node_load(array('nid' => lingotek_tnid($target_node)));

  lingotek_download_document($source_node, $target_node);

  return TRUE;
}
