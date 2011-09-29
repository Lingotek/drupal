<?php
/**
 * @file
 * Machine Translation
 */

/*
 * AJAX call to add to the machine translation queue
 */
function lingotek_mt($node, $targets, $engine) {
  global $user;
  //Is an engine enabled?
  if ($engine) {
    foreach ($targets as $target) {
      if ($target != $node->language) {
        lingotek_enqueue($user->uid, $node->nid, $target, $engine);
      }
    }
  }
}

/*
 * Append to the queue nodes and targets to be machine translated
 */
function lingotek_enqueue($uid, $nid, $language, $engine) {
  $row = array(
    db_escape_field('uid') => $uid,
    db_escape_field('nid') => $nid,
    db_escape_field('language') => $language,
    db_escape_field('engine') => $engine,
  );
  drupal_write_record('lingotek_mt_queue', $row);
}

/*
 * Run the current machine translation queue
 */
function lingotek_dequeue() {
  global $user, $_lingotek_locale;
  $result = db_select('lingotek_mt_queue', 'l')->fields('l', array('nid', 'language', 'engine'))->condition('uid', $user->uid)->execute();
  if ($result && $result->rowCount() > 0) {
    db_delete('lingotek_mt_queue')->condition('uid', $user->uid)->execute();
    $xlf = array();
    foreach ($result as $row) {
      $xlf[$row->nid]['engine'] = $row->engine;
      $xlf[$row->nid]['targets'][$row->language] = 1;
    }

    foreach ($xlf as $nid => $row) {
      $node = node_load($nid);
      $doc_id = lingotek_lingonode($nid, 'document_id_' . $node->language);
      $xliff = lingotek_get_xliff($doc_id);

      $target_languages = array();
      foreach (array_keys($row['targets']) as $lang) {
        array_push($target_languages, $_lingotek_locale[$lang]);
      }
      $targets = implode(",", $target_languages);

      echo '<textarea style="display:none;" tag="lingotek-mt-xliff" nid="' . $nid . '" doc="' . $doc_id . '" engine="' . $row['engine'] . '" source="' . $node->language . '" targets="' . $targets . '" node="' . $node->title . '">' . $xliff . '</textarea>';
      lingotek_trace('lingotek_dequeue', array("title" => $node->title, "nid" => $nid, "document_id" => $doc_id, "engine" => $row['engine'], "source" => $node->language, "targets" => $targets));
    }

    drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/lingotek.mask.css');
    drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/progress.css');

    $available_engines = variable_get('lingotek_available_mt_options', array_keys(lingotek_get_machine_translation_engines()));
    if (in_array('google', $available_engines)) {
      drupal_add_js('https://www.google.com/jsapi', 'external');
    }
    if (in_array('microsoft', $available_engines)) {
      drupal_add_js('http://api.microsofttranslator.com/V1/Ajax.svc/Embed?appId=No3DzjkuktHtOtTI_V5sUt2WCkefHlP4', 'external');
    }

    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.util.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.xliff.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.process.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.translate.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.mt.js');
  }
}

/*
 * TODO allow machine translation of the entire site.
 */
function lingotek_mt_all() {
  global $user;

  //all nodes minus already init nodes:
    //init nodes

  //all nodes - run mt
    //lingotek_enqueue($user->uid, $nid, $language, $engine);
}

/*
 * AJAX call to save a segment's machine translation
 */
function lingotek_mt_save_segment() {
  $result = lingotek_save_segment($_POST['sourceText'], $_POST['targetText'], $_POST['targetLanguage'], $_POST['document']);
  drupal_json_output(array('status' => 0, 'result' => $result));
}
