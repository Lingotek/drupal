<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;

class LingotekSettingsController extends LingotekControllerBase {

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    // TODO: alert if no languages are enabled yet.

    $account = LingotekAccount::instance();
  
    $api = LingotekApi::instance();
    $show_advanced = $account->showAdvanced();
  
    //$form_short_id values:  config, logging, utilities, language_switcher
    $form_id = "lingotek_admin_{$form_short_id}_form";
    if (!is_null($form_short_id) && function_exists($form_id)) {
      return drupal_get_form($form_id);
    }
  
    ctools_include('modal');
    ctools_modal_add_js();
  
    $show_fieldset = FALSE;
  
    $output = array();
  
    $output['lingotek'] = array(
      '#type' => 'vertical_tabs',
      '#attached' => array(
        'css' => array(
          '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css' => array(
            'type' => 'external',
          ),
        ),
        'js' => array(drupal_get_path('module', 'lingotek') . '/js/lingotek.admin.js'),
      ),
    );
  
    $account_summary = array(
      drupal_get_form('lingotek_admin_account_status_form', $show_fieldset),
      drupal_get_form('lingotek_admin_connection_form', $show_fieldset),
    );
  
    $output['lingotek'][] = lingotek_wrap_in_fieldset($account_summary, t('Account'), array('id' => 'ltk-account'));
  
    foreach (lingotek_managed_entity_types(TRUE) as $machine_name => $entity_type) {
      $entity_settings = drupal_get_form('lingotek_admin_entity_bundle_profiles_form', $machine_name, $show_fieldset);
      $output['lingotek'][] = lingotek_wrap_in_fieldset($entity_settings, t('Translate @type', array('@type' => $entity_type['label'])), array('id' => lingotek_get_tab_id($machine_name), 'class' => array('ltk-entity')));
    }
    $output['lingotek'][] = lingotek_wrap_in_fieldset(drupal_get_form('lingotek_admin_additional_translation_settings_form', $show_fieldset), t('Translate Configuration'), array('id' => 'ltk-config'));
    $output['lingotek'][] = lingotek_wrap_in_fieldset(drupal_get_form('lingotek_admin_profiles_form', $show_fieldset), t('Translation Profiles'), array('id' => 'ltk-profiles'));
    if ($show_advanced) {
      $output['lingotek'][] = lingotek_wrap_in_fieldset(drupal_get_form('lingotek_admin_field_settings_form', $show_fieldset), t('Advanced Field Settings'), array('id' => 'ltk-fields'));
      $output['lingotek'][] = lingotek_wrap_in_fieldset(drupal_get_form('lingotek_admin_advanced_parsing_form', TRUE), t('Advanced Content Parsing'), array('id' => 'ltk-advanced-content-parsing'));
    }
  
    $output['lingotek'][] = lingotek_wrap_in_fieldset(drupal_get_form('lingotek_admin_prefs_form', $show_fieldset), t('Preferences'), array('id' => 'ltk-prefs'));
    $output['lingotek'][] = lingotek_wrap_in_fieldset(drupal_get_form('lingotek_admin_logging_form', $show_fieldset), t('Logging'), array('id' => 'ltk-logging'));
  
    $utilities = array(
      drupal_get_form('lingotek_admin_cleanup_form', $show_fieldset),
      drupal_get_form('lingotek_admin_utilities_form', $show_fieldset),
    );
    $output['lingotek'][] = lingotek_wrap_in_fieldset($utilities, t('Utilities'), array('id' => 'ltk-utils'));
  
    return $output;
  }
}
