<?php
/**
 * @file
 * Displays progress of the community translation
 */

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
  global $_lingotek_client, $_lingotek_locale;

  drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.pm.js');

  if (!$_lingotek_client->canLogIn()) {
    $msg = 'Lingotek Login Failed';
    lingotek_error($msg);
    return t($msg);
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
  lingotek_update_nodes($source);

  $doc_id = lingotek_lingonode($source->nid, 'document_id');

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

      foreach ($target->phases as $phase) {
        $phases[$language][$phase->order]->percentComplete = $phase->percentComplete;
      }
    }
  }

  //Create a table to show current translation progress:
  $list = language_list();
  $header = array(t('Language'), t('Title'), t('Phase'), t('Status'), t('Published'));

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
      if ($percent == "") {
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
          if (user_access('lingotek_translate')) {
            $operation = lingotek_workbench_phase_link($doc_id, $phase_id, $label);
          }
        }
        //All other phases are review phases, so give users with review permissions the link
        else {
          if (user_access('lingotek_review_translations')) {
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

    if (user_access('lingotek_machine_translation') || user_access('lingotek_sync_from_lingotek')) {
      $check = '<input tag="lingotek_pm_row" type="checkbox" language="' . $node->language . '" />';
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
  if (user_access('lingotek_sync_from_lingotek')) {
    $download = '<input type="button" value="Sync" onclick="lingotek.pm.update();" />';
  }
  $mt = "";
  if (user_access('lingotek_machine_translation')) {
    $available_engines = lingotek_get_machine_translation_engines();
    $enabled_engines = variable_get('lingotek_available_mt_options', array_keys($available_engines));
    $engines = array();
    $select = "";
    foreach ($enabled_engines as $engine) {
      $engines[$engine] = $available_engines[$engine];
    }
    unset($engines[0]);
    if (count($engines) == 0) {
      $mt = "";
    }
    else {
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
      $mt .= '<input id="lingotek-mt-button" type="button" onclick="lingotek.pm.mt();" value="' . t('Machine Translate') . '" />';
    }
  }
  if (user_access('lingotek_sync_from_lingotek') || user_access('lingotek_machine_translation')) {
    array_unshift($header, '<input type="checkbox" onclick="lingotek.pm.toggle_checkboxes(this);" />');
  }
  $table = theme('table', $header, $rows);

  return $table . $mt . '<br />' . $download . '<input id="lingotek_nid" type="hidden" value="' . $tnid . '" />';
}

/*
 * AJAX call to add to the machine translation queue
 */
function lingotek_mt_ajax($nid) {
  global $user;

  foreach ($_POST['targets'] as $target) {
    lingotek_enqueue($user->uid, $nid, $target, $_POST['engine']);
  }
}

/*
 * Sync the current translations from Lingotek to Drupal for the target node
 *
 * @param $nid
 *  Node Id for the source node
 */
function lingotek_update($nid) {
  global $_lingotek_locale;

  $node = node_load(array('nid' => $nid));
  foreach ($_POST['targets'] as $target) {
    //Handle the language being passed as the value lingotek uses already:
    if (!array_key_exists($target, $_lingotek_locale)) {
      $target = lingotek_drupal_language($target);
    }
    lingotek_download_document($node, lingotek_get_node($target, $nid));
  }
  drupal_json(array('status' => 0, 'updated' => $_POST['targets'], 'nid' => $nid));
}
