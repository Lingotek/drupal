<?php
/**
 * @file
 * Machine Translation
 */

/*
 * Append to the queue nodes and targets to be machine translated
 */
function lingotek_enqueue($uid, $nid, $language, $engine) {
  db_query("INSERT INTO {lingotek_mt_queue} VALUES(%d, %d, '%s', '%s')", $uid, $nid, $language, $engine);
}

/*
 * Run the current machine translation queue
 */
function lingotek_dequeue() {
  global $user, $_lingotek_locale;

  $result = db_query('SELECT l.nid, l.language, l.engine FROM {lingotek_mt_queue} l WHERE l.uid = %d', $user->uid);
  if ($result) {
    db_query('DELETE FROM {lingotek_mt_queue} WHERE uid = %d', $user->uid);
    $xlf = array();
    while ($row = db_fetch_object($result)) {
      $xlf[$row->nid]['engine'] = $row->engine;
      $xlf[$row->nid]['targets'][$row->language] = 1;
    }

    foreach ($xlf as $nid => $row) {
      $doc_id = lingotek_lingonode($nid, 'document_id');
      $node = node_load(array('nid' => $nid));
      $xliff = lingotek_get_xliff($doc_id);

      $target_languages = array();
      foreach (array_keys($row['targets']) as $lang) {
        array_push($target_languages, $_lingotek_locale[$lang]);
      }
      $targets = implode(",", $target_languages);

      echo '<textarea style="display:none;" tag="lingotek-mt-xliff" nid="' . $nid . '" doc="' . $doc_id . '" engine="' . $row['engine'] . '" source="' . $node->language . '" targets="' . $targets . '" node="' . $node->title . '">' . $xliff . '</textarea>';
    }

    drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/lingotek.mask.css');
    drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/progress.css');

    $available_engines = variable_get('lingotek_available_mt_options', array_keys(lingotek_get_machine_translation_engines()));
    if (in_array('google', $available_engines)) {
      drupal_set_html_head('<script type="text/javascript" src="https://www.google.com/jsapi"></script>');
    }
    if (in_array('microsoft', $available_engines)) {
      drupal_set_html_head('<script type="text/javascript" src="http://api.microsofttranslator.com/V1/Ajax.svc/Embed?appId=No3DzjkuktHtOtTI_V5sUt2WCkefHlP4"></script>');
    }

    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.util.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.xliff.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.process.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.translate.js');
    drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.mt.js');

    lingotek_localize_js();
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
  drupal_json(array('status' => 0, 'result' => $result));
}
