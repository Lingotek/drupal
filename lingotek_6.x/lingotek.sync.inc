<?php
// $Id:lingotek.sync.inc,LingoTek$

/**
 * @file
 * Update the Drupal Nodes with new information from the Lingotek Collaborative
 * Translation Platform
 */

/*
 * Update Drupal nodes with the current progress of each target translation for $documentId
 *
 * @param $node
 *  Currently viewed node
 *
 * @return
 *  Queue of languages that need machine translation
 *  Keys are the languages, values are the node ids
 */
function lingotek_update_nodes($node) {
  global $_lingotek_client;

  $mt_queue = array();
  $locales = variable_get('lingotek_languages', array());

  if ($node->language == "") {
    $node->language = variable_get('lingotek_neutral_language', 'en');
  }

  $languages = array();
  foreach (language_list() as $language) {
    if ($language->language != $node->language && $language->enabled) {
      array_push($languages, $locales[$language->language]);
    }
  }

  //Is the document uploaded?  If not, let's upload it.
  if (!lingotek_lingonode($node->nid, 'document_id')) {
    $file_name = tempnam("temp", "Doc");
    $file_handle = fopen($file_name, "w");

    $body = $node->body;

    fwrite($file_handle, "<?xml version=\"1.0\"?><contents><title><![CDATA[" . $node->title . "]]></title><body><![CDATA[" . $body . "]]></body></contents>");

    $data = array
    (
      'projectId' => variable_get('lingotek_project', ''),
      'documentName' => $node->title,
      'format' => 'XML',
      'encoding' => 'UTF-8',
      'sourceLanguage' => $locales[$node->language],
      'tmVaultId' => variable_get('lingotek_vault', 1),
    );

    $output = $_lingotek_client->upload
    (
      'addDocument',
      $data,
      $file_name
    );

    fclose($file_handle);

    if ($output->results == "success") {
      lingotek_lingonode($node->nid, 'document_id', $output->id);
    }
    else {
      error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") addDocument FAILED - projectId: " . variable_get('lingotek_project', '') . ", documentName: " . $node->title . ", node language: " . $node->language . ", sourceLanguage: " . $locales[$node->language]);
      error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") addDocument message: " . $output->message);
    }
  }

  $doc_id = lingotek_lingonode($node->nid, 'document_id');

  //Flush % complete for documents
  $output = $_lingotek_client->request("flushDocumentProgress", array('documentId' => $doc_id));
  if ($output->results != "success") {
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") flushDocumentProgress FAILED - documentId: " . $doc_id);
  }

  //Is the Target Language added?
  $output = $_lingotek_client->request("getDocument", array('documentId' => $doc_id));
  if ($output->results == "success") {
    $doc_name = $output->name;
    $target_languages = array();
    foreach ($output->translationTargets as $target) {
      $target_node = lingotek_get_node(lingotek_language_matching($target->language), $node->nid);
      //Make sure for some reason the language wasn't deleted by the administrator
      if ($target_node) {
        array_push($target_languages, $target->language);

        $is_downloaded = lingotek_lingonode($target_node->nid, "downloaded");

        //Update only on 100% complete AND only once:
        if ($target->percentComplete == 100 && !$is_downloaded) {

          lingotek_lingonode($target_node->nid, "downloaded", TRUE);

          lingotek_download_document($node, $target_node, TRUE);

          $target_node->status = 1; //Published now;
          $target_node->promote = $node->promote;
          $target_node->sticky = $node->sticky;
          $target_node->comment = $node->comment;

          //Link to the node so it can be visible:
          //We don't check 'publishWithMt' in case it changed since the time the article was created (although it shouldn't), so that it will still be published independent of the setting.
          $tnid = lingotek_lingonode($target_node->nid, "tnid");
          //Check if it wasn't published initially:
          if ($tnid) {
            $target_node->tnid = $tnid;
          }
					node_save($target_node);
        }

        //Needs to be updated every time.
        if (lingotek_lingonode($target_node->nid, 'percent_complete') != $target->percentComplete) {
          lingotek_lingonode($target_node->nid, 'percent_complete', $target->percentComplete);
        }

      }
      else {
        //This can be caused when the administrator deletes a language system-wide.
        //We need to remove the target language.
        $_lingotek_client->request('removeTranslationTarget', array('translationTargetId' => $target->id));
      }
    }

    //Are there any translations missing?
    $missing_languages = array_diff($languages, $target_languages);

    //If translations are missing, let's add them:
    foreach ($missing_languages as $lang) {
      $add_translation_target = $_lingotek_client->request
      (
        "addTranslationTarget",
        array
        (
          'documentId' => $doc_id,
          'targetLanguage' => $lang
        )
      );
      if ($add_translation_target->results == "success") {
        $phase_template_id = lingotek_lingonode($node->nid, "phaseTemplate");

        if ($phase_template_id === FALSE) {
          $phase_template_id = variable_get('lingotek_phase_template', 2);
        }

        $apply_phase_template = $_lingotek_client->request("applyPhaseTemplate", array('translationTargetId' => $add_translation_target->id, 'phaseTemplateId' => $phase_template_id));

        if ($apply_phase_template->results == "success") {

          $get_translation_target = $_lingotek_client->request("getTranslationTarget", array('translationTargetId' => $add_translation_target->id));

          //Create placeholder nodes for target languages:
          if ($get_translation_target->results == "success") {
            $target_node = new stdClass();
            $target_node->type = $node->type;
            $target_node->language = lingotek_language_matching($lang);
            $target_node->uid = $node->uid;
            $target_node->sticky = $node->sticky;
            $target_node->format = $node->format;
            $target_node->status = 0;

						//Submit so we can get the new node's nid
            node_submit($target_node);

            if (lingotek_lingonode($node->nid, 'publishWithMt')) {
              //Add node to target queue for MT Translation:
              $mt_queue[$lang] = $target_node->nid;
            }
            $target_node->tnid = $target_node->nid;

            node_save($target_node);
            lingotek_lingonode($target_node->nid, "tnid", $node->nid);
          }
          else {
            error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") getTranslationTarget FAILED - translationTargetId: " . $add_translation_target->id);
            error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") getTranslationTarget message: " . $get_translation_target->message);
          }
        }
        else {
          error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") applyPhaseTemplate FAILED - translationTargetId: " . $add_translation_target->id . ", phaseTemplateId: " . $phase_template_id);
          error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") applyPhaseTemplate message: " . $apply_phase_template->error);
        }
      }
      else {
        error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") addTranslationTarget FAILED - documentId: " . $doc_id . ", targetLanguage: " . $lang . ", phaseTemplateId: " . 2);
        error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") addTranslationTarget message: " . $add_translation_target->message);
      }
    }
  }
  else {
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") getDocument FAILED - documentId: " . $doc_id);
    error_log("LINGOTEK API: " . __FILE__ . ":(" . __LINE__ . ") getDocument message: " . $output->message);
  }

  if (!$node->tnid || $node->tnid == "") {
    $node->tnid = $node->nid;
    node_save($node);
  }
  return $mt_queue;
}
