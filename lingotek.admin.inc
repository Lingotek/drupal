<?php
/**
 * @file
 * Administrative Settings for the module.
 */
include_once('lingotek.session.inc');

/**
 * Form constructor for the administration form.
 *
 * @return array
 *   A FAPI form array.
 */
function lingotek_admin_form() {

  // Check the current settings -- SKIPS: vault, workflow and the 2 oauth fields, because legacy users will not have those.
  $current_community_identifier = variable_get( 'lingotek_community_identifier', '' );
  $current_login_id = variable_get( 'lingotek_login_id', '' );
  $current_project = variable_get( 'lingotek_project', '' );
  // Does the install already have connection credentials?
  if ($current_community_identifier == '' || $current_login_id == '' || $current_project == '' ) {
    // If something is missing - Go though the Setup Process
    drupal_goto( 'admin/config/lingotek/new-account' );
  } 

  $api = LingotekApi::instance();
  $connected = $api->testAuthentication();
  $site = variable_get('site_name', 'Drupal Site');
  
  $connection_group_description = ($connected) ? '' :
    t('Connect this site to your Lingotek account by filling in the fields below. If you do not yet have a Lingotek account, you can <a href="@signup_url">sign up</a> to create an ID and collect OAuth credentials. If all fields are complete, there is a problem with one or more of the values.',
      array('@link_url' => url(variable_get('lingotek_url', LingotekApi::LINGOTEK_SERVER_PRODUCTION) . '/lingopoint/portal/communitySignup.action'))
    );
    

  // Lingotek connection settings.
  $form['connection'] = array(
    '#type' => 'fieldset',
    '#title' => t('Connection Settings'),
    '#description' => $connection_group_description,
    '#collapsible' => TRUE,
    '#collapsed' => $connected,
    '#group' => 'administrative_settings',
  );
  
  $status_message = ($connected) ? 
    t('<strong>Connection Status</strong>: OK.') : 
    t('<strong>Connection Status</strong>: Not Connected.');
    
  $form['connection']['status'] = array(
    '#markup' => '<h4 class="connection-status">' . $status_message . '</h4>',
  );

  $form['connection']['lingotek_login_id'] = array(
    '#type' => 'textfield',
    '#title' => t('Lingotek ID'),
    '#description' => t('Enter the Lingotek ID you use to access the Lingotek Dashboard and Workbench.'),
    '#default_value' => variable_get('lingotek_login_id', ''),
  );

  $form['connection']['lingotek_oauth_consumer_id'] = array(
    '#type' => 'textfield',
    '#title' => t('OAuth Key'),
    '#description' => t('The OAuth Key used to connect with the Lingotek server.'),
    '#default_value' => variable_get('lingotek_oauth_consumer_id', ''),
  );

  $form['connection']['lingotek_oauth_consumer_secret'] = array(
    '#type' => 'textfield',
    '#title' => t('OAuth Secret'),
    '#description' => t('The OAuth Secret used to connect with the Lingotek server.'),
    '#default_value' => variable_get('lingotek_oauth_consumer_secret', ''),
  );

  $form['connection']['lingotek_url'] = array(
    '#type' => 'textfield',
    '#title' => t('Lingotek Server'),
    '#description' => t('The path to the Lingotek server to which this site should be connected.'),
    '#default_value' => variable_get('lingotek_url', LingotekApi::LINGOTEK_SERVER_PRODUCTION),
  );

  // If the connection isn't yet established, stop here.
  if (!$api->testAuthentication()) {
    return system_settings_form($form);
  }

  // Options
  $options = array();
  $options[FALSE] = t("Disabled");
  $options[TRUE] = t("Enabled");

  // Default Settings
  $form['defaults'] = array(
    '#type' => 'fieldset',
    '#title' => t('Content Default Settings'),
    '#description' => t('Set defaults for use when creating new nodes and sending them to Lingotek for translation.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'administrative_settings',
  );

  $projects = (class_exists('LingotekApi')) ? $api->listProjects() : array();
  $id = variable_get('lingotek_project', '');

  if ($id == '' || !array_key_exists($id, $projects)) { //No project id set, project deleted, or community changed to one without that project.  Try to find the Drupal project
    $id = array_search($site, $projects);
    if ($id === False) { //Setup a default Drupal project
      $id = lingotek_add_project($site);
      $projects = (class_exists('LingotekApi')) ? $api->listProjects() : array();
    }
    else { //Assign to an existing Drupal project
      variable_set('lingotek_project', $id);
    }
  }

  $form['defaults']['lingotek_project'] = array(
    '#type' => 'select',
    '#title' => t('Default Project ID'),
    '#options' => $projects,
    '#description' => t('The default Lingotek Project ID with which translations will be associated.'),
    '#default_value' => $id,
  );

  $vaults = (class_exists('LingotekApi')) ? $api->listVaults() : array();
  $id = variable_get('lingotek_vault', '');

  if ($id == '') { //No vault id set. (Vaults are not community specific, so we don't have to test this like we did the project)  Try to find the Drupal vault
    $id = array_search($site, $vaults);
    if ($id === FALSE) { //Setup a default Drupal vault
      $id = lingotek_add_vault($site);
      $vaults = (class_exists('LingotekApi')) ? $api->listVaults() : array();
    }
    else { //Assign to an existing Drupal project
      variable_set('lingotek_vault', $id);
    }
  }
  lingotek_add_vault_to_project();

  $form['defaults']['lingotek_vault'] = array(
    '#type' => 'select',
    '#title' => t('Vault'),
    '#options' => $vaults,
    '#description' => t('The Translation Memory Vault where translations are saved.'),
    '#default_value' => $id,
  );

  $form['defaults']['lingotek_sync'] = array(
    '#type' => 'radios',
    '#title' => t('Synchronization Method'),
    '#description' => t('Use this synchronization method as the default when creating new pages.'),
    '#options' => lingotek_get_sync_methods(),
    '#default_value' => variable_get('lingotek_sync', 100),
  );

  $form['defaults']['lingotek_use_source'] = array(
    '#type' => 'select',
    '#title' => t('Include source text in synchronization.'),
    '#description' => t('Should the synchronization include the source language when a translation isn\'t provided?'),
    '#options' => $options,
    '#default_value' => variable_get('lingotek_use_source', TRUE),
  );

  $form['defaults']['lingotek_create_documents_by_default'] = array(
    '#type' => 'select',
    '#title' => t('Automatically Push Node Content to Lingotek'),
    '#description' => t('When nodes are created and edited, should the content automatically be pushed to the Lingotek platform? This default can be overridden for each node.'),
    '#options' => array(
      1 => t('Enabled'),
      0 => t('Disabled'),
    ),
    '#default_value' => variable_get('lingotek_create_documents_by_default', 1),
  );

  // URL Alias Translation.
  $form['defaults']['lingotek_url_alias_translation'] = array(
    '#type' => 'radios',
    '#title' => t('URL Alias Translation'),
    '#description' => t("Choose how you would like to translate the URL alias. The last option requires that you install both the Title and Pathauto modules, and define a path pattern, and check \"Enable Lingotek Translation\" for the Title field."),
    '#options' => lingotek_get_url_alias_translations(),
    '#default_value' => variable_get('lingotek_url_alias_translation', 1),
  );
  
  // Community Translation
  $form['defaults']['lingotek_allow_community_translation'] = array(
    '#type' => 'select',
    '#title' => t('Allow Community Translation'),
    '#description' => t('Sets the default value for Community Translation (translation by site visitors) on node add/edit forms.'),
    '#options' => array(
      1 => t('Enabled'),
      0 => t('Disabled'),
    ),
    '#default_value' => variable_get('lingotek_allow_community_translation', 0),
  );

  // Advanced XML configuration.
  $form['advanced-parsing'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced Content Parsing'),
    '#description' => t('Settings to support advanced parsing of translatable content.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['advanced-parsing']['lingotek_advanced_xml_config1'] = array(
    '#type' => 'textarea',
    '#title' => t('Configuration Settings (Primary)'),
    '#description' => t('Paste in the contents of an advanced configuration file (.fprm). This will be used as the primary set of advanced settings when sending content to Lingotek.'),
    '#default_value' => variable_get('lingotek_advanced_xml_config1'),
  );

  $form['advanced-parsing']['lingotek_advanced_xml_config2'] = array(
    '#type' => 'textarea',
    '#title' => t('Configuration Settings (Secondary)'),
    '#description' => t('Paste in the contents of an advanced configuration file (.fprm). This will be used as the secondary set of advanced settings when sending content to Lingotek.'),
    '#default_value' => variable_get('lingotek_advanced_xml_config2'),
  );

  if (!variable_get('lingotek_advanced_parsing', FALSE)) {
    $form['advanced-parsing']['lingotek_advanced_parsing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Upgrade to advanced content parsing.'),
      '#description' => t('This site is currently using Simple content parsing.
        Check this box to upgrade your site to use advanced content parsing for existing and future content. <strong>Warning:</strong> This will update all current Lingotek-associated content on the site, possibly modifying the state of in-progress translations.'),
    );

    $form['#submit'][] = 'lingotek_handle_advanced_xml_upgrade';
  }

  // Developer settings.
  $form['developer_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Developer Settings'),
    '#description' => t('Help debug any issues with the module and adds ways to manipulate the module\'s data directly.') . ' ' . l(t('(tools)'), 'admin/settings/lingotek/dev'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'administrative_settings',
  );

  $form['developer_settings']['lingotek_error_log'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable Error Log'),
    '#description' => t('This prints errors and warnings to the web server\'s error logs in addition to adding them to watchdog.'),
    '#default_value' => variable_get('lingotek_error_log', FALSE),
  );

  $form['developer_settings']['lingotek_warning_log'] = array(
    '#type' => 'checkbox',
    '#title' => t('Supress Warnings'),
    '#description' => t('This prevents warnings from being sent to watchdog and the web server\'s error logs.'),
    '#default_value' => variable_get('lingotek_warning_log', FALSE),
  );

  $form['developer_settings']['lingotek_trace_log'] = array(
    '#type' => 'checkbox',
    '#title' => t('Supress Trace'),
    '#description' => t('This prevents debug messages from being sent to watchdog and the web server\'s error logs.'),
    '#default_value' => variable_get('lingotek_trace_log', TRUE),
  );

  $form['developer_settings']['lingotek_flush_cache'] = array(
    '#type' => 'checkbox',
    '#title' => t('Never cache'),
    '#description' => t('Skips caching so you can test easier.  This avoids frequent polling of fresh data from Lingotek.  Only those with Developer permissions will have caching disabled.'),
    '#default_value' => variable_get('lingotek_flush_cache', FALSE),
  );

  $form['developer_settings']['lingotek_api_debug'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable API debugging'),
    '#description' => t('Logs the timing and request/response details of both successful and failing Lingotek API
      calls to the Drupal <a href="@watchdog_path">watchdog</a> in category "lingotek_debug".', array('@watchdog_path' => url('admin/reports/dblog'))),
    '#default_value' => variable_get('lingotek_api_debug', FALSE),
  );


  return system_settings_form($form);
}

/**
 * Administration form for the field language cleanup utility.
 *
 * @param array
 *   The current FAPI form state array.
 *
 * @return array
 *   A FAPI form array.
 */
function lingotek_form_field_language_utility($form_state) {
  $form = array();

  $form['description'] = array(
    '#markup' => '<p class="help">' . t('This tool processes all existing field data for translation-enabled fields on nodes,
      ensuring that if data was entered before enabling field translation on a field
      that the existing field data is copied over to the parent node\'s current language.') .
      '</p>',
  );

  $form['warning'] = array(
    '#markup' => '<p class="help">' . t('<strong>WARNING:</strong> This tool makes changes to your site\'s data. We strongly encourage you to back up your database before using this tool.') .
      '</p>',
  );

  $translatable_fields = lingotek_translatable_node_fields();

  $form['status'] = array(
    '#markup' => theme('item_list', array('items' => $translatable_fields, 'title' => t('Translation-enabled Fields'), 'type' => 'ul')),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update Field Data'),
  );

  return $form;
}

/**
 * Submit handler for the lingotek_form_field_language_utility form.
 *
 * @param array $form
 *   The FAPI form array.
 * @param array $form_state
 *   The FAPI form state array.
 */
function lingotek_form_field_language_utility_submit($form, $form_state) {
  $query = new EntityFieldQuery();
  $entity_type = 'node';
  $nodes = $query->entityCondition('entity_type', $entity_type)
    ->propertyCondition('language', 'und', '<>')
    ->execute();

  $operations = array();
  foreach ($nodes[$entity_type] as $node) {
    $operations[] = array('lingotek_handle_batch_field_language_update',
      array($node->nid));
  }

  $batch = array(
    'title' => t('Lingotek Field Language Updater'),
    'operations' => $operations,
    'finished' => 'lingotek_field_language_update_finished',
    'file' => 'lingotek.admin.inc',
  );

  batch_set($batch);
}

/**
 * Custom form handler for upgrading a site from using Lingotek's simple to advanced XML parsing of content.
 */
function lingotek_handle_advanced_xml_upgrade($form, $form_state) {
  if ($form_state['values']['lingotek_advanced_parsing']) {
    $results = db_select('lingotek', 'l')->fields('l', array('nid'))->distinct()->execute();
    $operations = array();
    foreach ($results as $result) {
      $node = node_load($result->nid, NULL, TRUE);
      if (!empty($node->nid)) {
        $operations[] = array('lingotek_advanced_parsing_update_node', array($node->nid));
      }
    }

    $batch = array(
      'title' => t('Lingotek Advanced Parsing Updater'),
      'operations' => $operations,
      'file' => 'lingotek.admin.inc',
      'finished' => 'lingotek_advanced_parsing_update_finished',
    );

    // The admin form might not have finished processing yet, but if we're here, we're moving to advanced processing.
    // Ensure the appropriate variable is already set.
    variable_set('lingotek_advanced_parsing', TRUE);

    batch_set($batch);
  }
}
