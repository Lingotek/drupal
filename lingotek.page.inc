<?php
/**
 * @file
 * Lingotek Tab for Nodes
 */

/*
 * Construct the table summarizing information a Product Manager would want
 * to know about the progress of the translations.
 */
function lingotek_pm($node) {
  drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.pm.js');
  drupal_set_title(lingotek_get_title($node), PASS_THROUGH);

  $checkbox = '<input type="checkbox" onchange="lingotek.pm.toggle_checkboxes(this);" />';
  $header = array($checkbox, t('Target Language'), t('Progress'));
  $progress = lingotek_get_document_targets(lingotek_lingonode($node->nid, 'document_id_' . $node->language));

  $rows = array();
  foreach ($progress as $language => $target) {
    $row = array
    (
      '<input type="checkbox" tag="lingotek_pm_row" language="' . $language . '" />',
      lingotek_language_native($language) . ' (' . lingotek_language_name($language) . ')',
      $target->percentComplete . '%',
    );
    lingotek_trace("lingotek_pm table row", array('language' => $language));
    $rows[] = $row;
  }
  $table = theme('table', array('header' => $header, 'rows' => $rows));

  $actions = '<input type="button" value="' . t('Update') . '" onclick="lingotek.pm.update();" />';


  $vars = '<input type="hidden" id="lingotek_nid" value="' . $node->nid . '" />';

  return $table . $actions . $vars;
}

/*
 * Synchonize translations with what is available on the Lingotek platform (current translations)
 */
function lingotek_update($nid) {
  $node = node_load($nid);
  foreach ($_POST['targets'] as $target) {
    lingotek_download_document($node, $target);
  }
  drupal_json_output(array('status' => 0, 'updated' => $_POST['targets'], 'nid' => $nid));
}
