<?php
/**
 * @file
 * Lingotek Tab for Nodes
 */

/**
 * Page callback for the Lingotek local task on node detail pages.
 *
 * Construct the table summarizing information a Product Manager would want
 * to know about the progress of the translations.
 *
 * @return array
 *   A Drupal render array of the page contents.
 */
function lingotek_pm($node) {
  drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.pm.js');

  $checkbox = '<input type="checkbox" onchange="lingotek.pm.toggle_checkboxes(this);" />';
  $header = array($checkbox, t('Target Language'), t('Document Progress'), t('Phase Progress'), t('Phase'));
  $progress = lingotek_get_document_targets(lingotek_lingonode($node->nid, 'document_id_' . $node->language), TRUE);

  $rows = array();
  foreach ($progress as $language => $target) {
    $current_phase = LingotekApi::instance()->currentPhase($target->id);
    $row = array(
      '<input type="checkbox" tag="lingotek_pm_row" language="' . $language . '" />',
      lingotek_language_native($language) . ' (' . lingotek_language_name($language) . ')',
      $target->percentComplete . '%',
      $current_phase->percentComplete . '%',
      lingotek_get_workbench_url($node, $language, TRUE),
    );
    lingotek_trace("lingotek_pm table row", array('language' => $language));
    $rows[] = $row;
  }
  $table = theme('table', array('header' => $header, 'rows' => $rows));

  $actions = '<input type="button" id="lingotek-update-button" value="' . t('Update') . '"/>';

  $vars = '<input type="hidden" id="lingotek_nid" value="' . $node->nid . '" />';
  
  $pull_translations = array();
  
  $pull_translations['fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Pull translations from Lingotek'),
    '#description' => t('Pull the latest translations from Lingotek in the selected languages.'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );
  
  $pull_translations['fieldset']['contents'] = array(
    '#markup' => $table . $actions . $vars,
  );
  
  $output = array();
    
  if (lingotek_supported_type($node->type) && lingotek_supported_language($node->language)) {
    $output['content_push'] = array(
      '#markup' => (lingotek_lingonode($node->nid, 'create_lingotek_document')) ? '' : drupal_render(drupal_get_form('lingotek_content_push_form', $node)),
    );
    
    if (!empty($rows)) {    
      $output['content_pull'] = array(
        '#markup' => drupal_render($pull_translations),
      );
    }
  }
  else {
    $output['content_pull'] = array(
      '#markup' => '<p class="help">' . t('This node is not compatible with Lingotek translation. Either it is not a Lingotek tranlsation-enabled content type. Or the node does not have a defined language.') . '</p>'
    );
  }

  $output['upgrade_form'] = array(
    '#markup' => drupal_render(drupal_get_form('lingotek_advanced_parsing_upgrade_form')),
  );

  return $output;
}

/*
 * Synchonize translations with what is available on the Lingotek platform (current translations)
 */
function lingotek_update($nid) {
  $node = node_load($nid);
  foreach ($_POST['targets'] as $target) {
    lingotek_download_document($node, check_plain($target));
  }
  drupal_set_message(t('Updated local translations for the selected languages.'));
  drupal_json_output(array('status' => 0, 'updated' => $_POST['targets'], 'nid' => $nid));
}

/**
 * Form constructor for parsing upgrade of a node.
 *
 * @return array
 *   A FAPI form array.
 */
function lingotek_advanced_parsing_upgrade_form($form_state) {
  $form = array();

  if (!variable_get('lingotek_advanced_parsing', FALSE)) {
    $router_item = menu_get_item();
    if (!empty($router_item['page_arguments'][0]->nid)) {
      $node_id = $router_item['page_arguments'][0]->nid;

      $form['node_id'] = array(
        '#type' => 'hidden',
        '#value' => $node_id,
      );

      $form['advanced_parsing_upgrade'] = array(
        '#type' => 'fieldset',
        '#title' => t('Advanced Content Parsing'),
        '#description' => t('Your site is currently set to use legacy ("simple") content parsing. Use the button below to upgrade this node to advanced content parsing.'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      );

      $advanced_parsing = lingotek_lingonode($node_id, 'use_advanced_parsing');
      if (empty($advanced_parsing)) {
        $form['advanced_parsing_upgrade']['submit'] = array(
          '#type' => 'submit',
          '#value' => t('Upgrade node'),
        );
      }
      else {
        $form['advanced_parsing_upgrade']['already_upgraded'] = array(
          '#markup' => t('This node has already been upgraded to use advanced parsing.')
        );
      }
    }
    else {
      watchdog('lingotek', 'Unable to locate node ID for advanced parsing upgrade form: @path', array('@path' => $_GET['q']), WATCHDOG_ERROR);
    }
  }

  return $form;
}


/**
 * Submit handler for the lingotek_advanced_parsing_upgrade_form form.
 *
 * @param array $form
 *   A FAPI form array.
 * @param array $form_state
 *   A FAPI form state array.
 */
function lingotek_advanced_parsing_upgrade_form_submit($form, $form_state) {
  $error = FALSE;

  if (!empty($form_state['values']['node_id'])) {
    lingotek_lingonode($form_state['values']['node_id'], 'use_advanced_parsing', 1);
    $target_node = node_load($form_state['values']['node_id']);
    if ($target_node->nid) {
      if (LingotekApi::instance()->updateContentDocument($target_node)) {
        drupal_set_message(t('This node has been upgraded to use advanced content parsing.'));
      }
      else {
        $error = TRUE;
        watchdog('lingotek', 'Error updating node for advanced content parsing. Lingotek updateContentDocument call failed.', NULL, WATCHDOG_ERROR);
      }
    }
    else {
      $error = TRUE;
      watchdog('lingotek', 'Unable to load target node for content parsing upgrade: @node_id',
        array('@node_id' => $form_state['values']['node_id']), WATCHDOG_ERROR);
    }
  }
  else {
    $error = TRUE;
    watchdog('lingotek', 'No target node ID in parsing upgrade form data.', NULL, WATCHDOG_ERROR);
  }

  if ($error) {
    drupal_set_message(t('There was an error upgrading this node. It has <strong>not</strong> been updated to use advanced parsing.'), 'error');
  }
}
