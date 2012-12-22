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

  // If we have a language prefix, then drupal_get_path will append that to the front of the string it returns.  So lets strip it back off, so it doesn't break the paths.
  global $language_content;
  $icon_green_check = url( drupal_get_path('module', 'lingotek') . '/images/ico_tick_17x.png' );
  $prefix = $language_content->prefix;
  if ( strlen( $prefix ) > 0 ) {
    $icon_green_check = substr( $icon_green_check, strlen( $prefix )+1 );    
  }

  $output = array();

  drupal_add_js(drupal_get_path('module', 'lingotek') . '/js/lingotek.pm.js');

  if (class_exists('LingotekDocument')) {
    $checkbox = '<input type="checkbox" onchange="lingotek.pm.toggle_checkboxes(this);" />';
    $headers = array(
      $checkbox,
      t('Target Language'),
      t('Document Progress'),
      t('Phase'),
      t('Phase Progress'),
      t('Phase Complete'),
    );

    $document = LingotekDocument::load(lingotek_lingonode($node->nid, 'document_id_' . $node->language));
    $progress = lingotek_get_document_targets($document->document_id, TRUE);
    $rows = array();
    foreach ($progress as $language => $target) {
      $current_phase = $document->currentPhase($target->id);
      $phase_complete = ($current_phase->isMarkedComplete) ? TRUE : FALSE;
      $phase_complete_percent = $current_phase->percentComplete;
      if (empty($phase_complete_percent)) {
        $phase_complete_percent = 0;
      }
      $row = array(
        '<input type="checkbox" tag="lingotek_pm_row" language="' . $language . '" />',
        lingotek_language_native($language) . ' (' . lingotek_language_name($language) . ')',
        $target->percentComplete . '%',
        lingotek_get_workbench_url($node, $language, TRUE),
        $phase_complete_percent . '%',
        ($phase_complete) ? '<img src="' . $icon_green_check . '" />' : '',
      );
      lingotek_trace("lingotek_pm table row", array('language' => $language));
      $rows[] = $row;
    }
    $table = theme('table', array('header' => $headers, 'rows' => $rows));

    $actions = '<input type="button" id="lingotek-update-button" value="' . t('Update') . '"/>';

    $vars = '<input type="hidden" id="lingotek_nid" value="' . $node->nid . '" />';

    $token_element = array(
      '#type' => 'hidden',
      '#attributes' => array('id' => 'submit-token'),
      '#value' => drupal_get_token(),
    );
    $vars .= drupal_render($token_element);

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

    if (lingotek_supported_type($node->type) && lingotek_supported_language($node->language)) {
      $content_push_form = drupal_get_form('lingotek_content_push_form', $node);
      $output['content_push'] = array(
        '#markup' => (lingotek_lingonode($node->nid, 'create_lingotek_document')) ? '' : drupal_render( $content_push_form ),
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

    // Add the mark as complete table if there are complete-eligible phrases.
    if ($document->hasPhasesToComplete()) {
      $drupal_phase_complete_from = drupal_get_form('lingotek_mark_phases_complete', $node);
      $output['mark_complete'] = array(
        '#markup' => drupal_render($drupal_phase_complete_from),
      );
    }

    $lingotek_advanced_parsing_form = drupal_get_form('lingotek_advanced_parsing_upgrade_form');
    $output['upgrade_form'] = array(
      '#markup' => drupal_render($lingotek_advanced_parsing_form),
    );
  }

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

/**
 * Form constructor for the lingotek_mark_phases_complete form.
 *
 * @param array $form
 *   A FAPI form array.
 * @param array $form_state
 *   A FAPI form state array.
 * @param object $node
 *   The Drupal node whose complete-eligible phases should be displayed.
 *
 * @return array
 *   A FAPI form data array.
 */
function lingotek_mark_phases_complete($form, $form_state, $node) {
  $form = array();

  $document_id = lingotek_lingonode($node->nid, 'document_id_' . $node->language);
  if (class_exists('LingotekDocument') && class_exists('LingotekPhase') && $document_id) {
    $api = LingotekApi::instance();

    $document = LingotekDocument::load($document_id);
    if ($progress = $document->translationProgress()) {
      $targets = $progress->translationTargets;

      foreach ($targets as $target) {
        $language = lingotek_drupal_language($target->language);
        $current_phase = $document->currentPhase($target->id);

        $phase_complete_percent = $current_phase->percentComplete;
        if (empty($phase_complete_percent)) {
          $phase_complete_percent = 0;
        }        
        
        if ($current_phase && $current_phase->canBeMarkedComplete()) {
          $phase_link = l($current_phase->name, '#', array('attributes' => array(
            'onclick' => 'window.open(\'' . $api->getWorkbenchLink($document_id, $current_phase->id) . '\'); return FALSE;')));

          $row = array(
            lingotek_language_native($language) . ' (' . lingotek_language_name($language) . ')',
            $phase_link,
            $phase_complete_percent . '%',
          );

          $options[$current_phase->id] = $row;
        }
      }

      $form['mark_complete'] = array(
        '#type' => 'fieldset',
        '#title' => t('Mark Workflow Phases complete'),
        '#description' => t('The following Translation Targets have Phases that can be marked as complete.'),
      );

      $form['mark_complete']['phases'] = array(
        '#type' => 'tableselect',
        '#header' => array(
          t('Target Language'),
          t('Phase'),
          t('Phase Progress'),
        ),
        '#options' => $options,
      );

      $form['mark_complete']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Mark Selected Phases as Complete'),
      );
    }
    else {
      watchdog('lingotek', 'Unable to build mark as complete form: could not get progress data from API.',
        NULL, WATCHDOG_ERROR);
    }
  }

  return $form;
}

/**
 * Submit handler for the lingotek_mark_phases_complete form.
 */
function lingotek_mark_phases_complete_submit($form, $form_state) {
  if (!empty($form_state['values']['phases'])) {
    $api = LingotekApi::instance();
    $errors = FALSE;
    foreach ($form_state['values']['phases'] as $phase_id) {
      if ($phase_id) {
        if (!$api->markPhaseComplete($phase_id)) {
          $errors = TRUE;
        }
      }
    }

    if (!$errors) {
      drupal_set_message(t('All selected phases were marked as complete.'));
    }
    else {
      drupal_set_message(t('There were errors marking one or more phases as complete.'), 'error');
    }
  }
  else {
    drupal_set_message(t('No phases were selected.'), 'error');
  }
}
