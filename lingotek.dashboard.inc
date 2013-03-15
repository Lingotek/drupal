<?php

/**
 * @file
 * Lingotek Dashboard.
 */

/**
 * Tab:  Dashboard - The main Lingotek dashboard page.
 */
function lingotek_dashboard() {

  lingotek_is_module_setup();
  $account = LingotekAccount::instance(); //remove?

  if (lingotek_is_config_missing()) {
    $output = array();
    // Check the login ID to see if this is a Lingopoint user, if so, provide information about setting up their oauth keys.
    $login_id = variable_get('lingotek_login_id', 'community_admin');

    if ($login_id != 'community_admin') {
      drupal_set_message(t('Your Lingotek module is not fully configured.'), 'warning');
      $output['instructions'] = array(
        '#type' => 'markup',
        '#markup' => theme('item_list', array(
          'title' => 'Complete configuration by performing the following:',
          'type' => 'ol',
          'items' => array(
            array('data' => t('Login to the ' . l(t('Lingotek account dashboard'), LINGOTEK_API_SERVER, array('attributes' => array('target' => '_blank'))) . '')),
            array('data' => t('Select the <i>Community &gt; Integrations</i> menu item')),
            array('data' => t('Setup an <i>Inbound OAuth integration</i> method to create an <i>OAuth Key</i> and <i>OAuth Secret</i>')),
            array('data' => t('Copy and paste the <i>OAuth Key</i> and <i>OAuth Secret</i> that were created into the ' . l(t('Lingotek Drupal module settings'), 'admin/settings/lingotek/settings')))
          )
        ))
      );
    }
    else { // Show the standard misconfiguration message.
      drupal_set_message(t('Your Lingotek module is not fully configured. To complete setup, run the') . ' ' . l(t('Lingotek Configuration Wizard'), 'admin/config/lingotek/setup'), 'warning');
    }
    $output[] = lingotek_support_footer();
    return $output;
  }
  
  // As a backup fail safe, the dashboard loading should clear the sync flag if its still set.
  if (isset($_SESSION['lingotek_sync_in_progress'])) {
    unset($_SESSION['lingotek_sync_in_progress']);
  }

  return array(
    'dashboard' => array(
      '#type' => 'markup',
      '#markup' => '<div id="dashboard"></div>'
    ),
    'script' => array(
      '#type' => 'markup',
      '#markup' => lingotek_get_dashboard_code() // Generates the dashboard javascript.
    )
  );
}

// END:  lingotek_dashboard()

/**
 * Drupal JSON Output - CORS - allows cross domain requests
 * (adapted from: drupal_json_output)
 * @param type $var
 */
function drupal_json_output_cors($var = NULL) {
  // We are returning JSON, so tell the browser.
  drupal_add_http_header('Content-Type', 'application/json');
  drupal_add_http_header('Access-Control-Allow-Origin', "*");
  drupal_add_http_header('Access-Control-Allow-Methods', 'GET,PUT,POST,DELETE');
  drupal_add_http_header('Access-Control-Allow-Headers', 'Content-Type');
  drupal_add_http_header('X-Powered-By', 'Lingotek');
  if (isset($var)) {
    echo drupal_json_encode($var);
  }
}

/**
 * Generates the code for the embedded Javascript dashboard.
 */
function lingotek_get_dashboard_code() {
  global $base_url;

  $output = '';
  $totals = array();
  $guid = lingotek_get_guid(); // 16 char string
  //$active_languages = lingotek_get_active_languages();  // array of enabled Drupal language codes (from locale module)
  $target_languages = LingotekAccount::instance()->getManagedTargets(TRUE);

  foreach ($target_languages as $target) {
      $edited = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_EDITED, $target->language);
      $pending = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_PENDING, $target->language);
      $current = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $target->language);
      $total = $edited + $pending + $current;

      // Format:  [{"code":"fr_FR","active":true,"docs":100,"edited":10,"pending":20,"current":80},
      $totals[] = array(
        'code' => $target->locale,
        'dcode' => $target->language,
        'active' => $target->active,
        'docs' => $total,
        'edited' => $edited,
        'pending' => $pending,
        'current' => $current
      );
  } // END:  foreach language counts

  $script = '
      <script src="' . LINGOTEK_GMC_SERVER . '/js/lingotek.min.js"></script>
      <script> 
        require(["lingotek"],function(lingotek){ 
        var containerId = "dashboard"; 
        var config = { 
          // lingotek
          "community": "%s",
          "oauth_key": "%s",
          "oauth_secret": "%s",
          "external_id": "%s",
          "tm_vault_id": "%s",
          "workflow_id": "%s",
          "project_id": "%s",
          "first_name": "%s",
          "last_name": "%s",
          "email": "%s",
          // cms
          "cms_site_id": "%s",
          "cms_site_key": "%s",
          "cms_site_name": "%s",
          "cms_type": "%s",
          "cms_version": "%s",
          "cms_tag": "%s",
          "module_version": "%s",
          "endpoint_url": "%s",
          "upload_url": "%s",
          "download_url": "%s",
          "sync_url": "%s",
          // data
          "source_language":%s ,
          "localization_languages": %s,
          "target_languages": %s
        };
         lingotek.dashboard(containerId,config); 
        });
      </script>
    ';

  $lingotek_url = $base_url . '/' . current_path();
  $endpoint = $lingotek_url . '/command';
  $upload_url = $lingotek_url . '/mt-upload';
  $download_url = $lingotek_url . '/mt-download';
  $sync_url = $lingotek_url . '/sync';

  $output = sprintf($script,
      // lingotek 
      variable_get('lingotek_community_identifier', ''), //community
      variable_get('lingotek_oauth_consumer_id', ''), //oauth_key
      variable_get('lingotek_oauth_consumer_secret', ''), //oauth_secret
      variable_get('lingotek_login_id', ''), //external_id
      variable_get('lingotek_vault', ''), //tm_vault_id
      variable_get('lingotek_workflow', ''), //workflow_id
      variable_get('lingotek_project', ''), //project_id
      variable_get('lingotek_activation_first_name', ''), //first_name
      variable_get('lingotek_activation_last_name', ''), //last_name
      variable_get('lingotek_activation_email', ''), //email
      // cms
      $guid, // installation GUID
      url('<front>', array('absolute' => TRUE)), // site url
      variable_get('site_name', 'Unidentified Drupal Site'), // sitename
      'Drupal', // cms type
      VERSION, // drupal version
      variable_get('lingotek_cms_tag', ''), // CMS tag     
      LINGOTEK_VERSION, // module version
      $endpoint, // endpoint url
      $upload_url, $download_url, $sync_url,
      // data
      lingotek_get_source_language_json(), // source_language
      lingotek_get_localization_languages_json(), // localization_languages - The languages "localized" on Drupal
      json_encode($totals) // The target languages they are using on Lingotek
  );
  return $output;
}

// END:  lingotek_get_dashboard_code()

/**
 * Ajax Command Processing for the Lingotek dashboard.
 */
function lingotek_dashboard_command_ajax() {

  $parameters = array();
  $message = 'Not doing anything.';
  $method = $_SERVER['REQUEST_METHOD'];

  $response = array();

  // 1. Update a Target Language (Initiate a Machine Translation Batch)
  if ($method == 'PUT') {

    // Initiate Machine Translation
    $message = 'PUT: Initiate Machine Translation';
    parse_str(file_get_contents("php://input"), $parameters);
    
    $type = $parameters['type'];
    $lingotek_locale = $parameters['code'];
    $drupal_language_code = Lingotek::convertLingotek2Drupal($lingotek_locale);

    $response = array(
      'code' => Lingotek::convertDrupal2Lingotek($drupal_language_code),
      'active' => TRUE,
      'docs' => '0',
      'pending' => '0',
      'current' => '0'
    );

    // Launch the machine translation process!
  } // END:  PUT
  // 2. Add/Insert Target Language
  if ($_POST) {

    $message = 'POST: Insert a new Target Language';
    $lingotek_locale = $_POST['code'];
    $parameters = $_POST;

    if (strlen($lingotek_locale) > 1) {

      // These are the languages that are currently active in this Drupal installation.
      $active_languages = current(language_list('enabled'));
      $drupal_language_code = Lingotek::convertLingotek2Drupal($lingotek_locale, FALSE);//


      // if the Source language is not active, activate it.
      if (!array_key_exists($drupal_language_code, $active_languages)) {
        $name = isset($_POST['language']) ? $_POST['language'] : NULL;
        $native = isset($_POST['native']) ? $_POST['native'] : NULL;
        $direction = isset($_POST['direction']) && (strcasecmp('RTL', $_POST['direction']) == 0 ) ? LANGUAGE_RTL : LANGUAGE_LTR;
        $domain = '';
        $prefix = '';//to-do: attempt to use a short prefix
        locale_add_language($drupal_language_code, $name, $native, $direction, $domain, $prefix); // Function from the Locale module.
      }

      // Adds the language to the local list of languages we do translation for, and to the Lingotek project.
      $add = lingotek_add_target_language($drupal_language_code);
      
      // Add the node sync target language entries to the lingotek table.
      lingotek_add_target_sync_status_to_all_nodes($drupal_language_code);

      $response = lingotek_get_target_status($lingotek_locale);
      $response['success'] = $add;
    }
  } // END:  POST
  // 3. Remove/Delete Target Language
  if ($method == 'DELETE') {

    // Initiate Language Delete
    parse_str(file_get_contents("php://input"), $parameters);
    
    $lingotek_locale = $parameters['code'];

    $drupal_language_code = Lingotek::convertLingotek2Drupal($lingotek_locale);

    $message = t('DELETE: Remove language (@code)',array('@code'=>$drupal_language_code));

    // Removes the language to the local list of languages we do translation for, and also removes the target language from the Lingotek project.
    $delete = lingotek_delete_target_language($drupal_language_code); // Mark this language as Active => False
    // Removes the node sync target language entries from the lingotek table.
    lingotek_delete_target_sync_status_for_all_nodes($drupal_language_code);

    $response = array(
      'code' => Lingotek::convertDrupal2Lingotek($drupal_language_code),
      'active' => FALSE,
      'success' => $delete
    ); // Format:  {'code':'es','active':'FALSE','success':TRUE}
  } // END:  DELETE
  // 4. Retrieve the Target Languages and Status
  if ($method == 'GET') {

    // Retrieve the Target Languages and Status
    $message = 'GET: Retrieve the Target Language(s) and Status';
    $parameters = $_GET;

    $response = isset($_GET['code']) ? lingotek_get_target_status($_GET['code']) : lingotek_get_target_status();
  } // END:  GET

  if ($method == 'PUT') {
    $commands = array();
    ajax_deliver(array('#type' => 'ajax', '#commands' => $commands));
  }
  else {
    //drupal_json_output_cors(array('response' => $response, 'message' => $message, 'data' => $data, 'method' => $method  ));
    $general = array('message' => $message);
    $full_response = array_merge($general, $response);
    if ($method !== 'GET') { //skip GETS (since they occur lots)
      watchdog('endpoint_api', '
      <h1>@method</h1>
      <h2>Parameters:</h2>
      <div>!parameters</div>
      <h2>Response:</h2>
      <div>!response</div>
      ', array(
        '@method' => $method,
        '!parameters' => watchdog_format_object($parameters),
        '!response' => watchdog_format_object($response)
          ), WATCHDOG_NOTICE);
    }
    return drupal_json_output_cors($full_response);
  }
}

// END:  lingotek_dashboard_command_ajax

/**
 * The number of nodes with the status sync counts from the lingotek table for the LANGUAGE TARGETS.
 * */
function lingotek_count_nodes($status) { //LINGOTEK_NODE_SYNC_STATUS_EDITED, LINGOTEK_NODE_SYNC_STATUS_PENDING, LINGOTEK_NODE_SYNC_STATUS_CURRENT
  $query = db_select('lingotek', 'l')->fields('l');
  $query->condition('lingokey', 'node_sync_status');
  $query->condition('lingovalue', $status);

  $result = $query->countQuery()->execute()->fetchField();

  return $result;
}

// END:  lingotek_count_nodes()

/**
 * Gets the current sync counts from the lingotek table for the LANGUAGE TARGETS.
 *
 * Defaults to CURRENT.
 * */
function lingotek_count_node_targets($status = LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $drupal_language_code) {

  $node_language_target_key = 'target_sync_status_' . $drupal_language_code;
  $lingotek_language_code = Lingotek::convertDrupal2Lingotek($drupal_language_code);

  $query = db_select('lingotek', 'l')->fields('l');
  $query->condition('lingokey', $node_language_target_key);
  $query->condition('lingovalue', $status);

  $result = $query->countQuery()->execute()->fetchAssoc();

  if (is_array($result)) {
    $count = array_shift($result);
  }
  else {
    $count = 0;
  }

  return $count;
}

// END:  lingotek_count_node_targets()

/**
 * Gets the curret count from the lingotek table for the nodes that Lingotek manages the translation for.
 * IE:  A count of the distinct Node IDs.
 * NOTE:  This WILL include nodes that are NOT published.  (Because we will still track, translate and manage them.)
 * */
function lingotek_count_translation_managed_source_language_nodes($drupal_language_code) {

  $query = db_select('lingotek', 'l')->fields('l', array('nid'))->distinct();
  $result = $query->countQuery()->execute()->fetchAssoc();

  if (is_array($result)) {
    $count = array_shift($result);
  }
  else {
    $count = 0;
  }

  return $count;
}

// END:  lingotek_count_nodes()

/**
 * Counts all the nodes of a specific language.
 * 
 * OLD METHOD.   Replaced by:  lingotek_count_node_targets() (for reporting node target counts)
 * Not removed yet, because its still used in other places for reporting other counts.
 *
 * Limits the counts to node / fields that are marked translatable.
 */
function lingotek_get_node_count($language = NULL) {

  $result = 0;

  if (isset($language)) {

    $source_language = lingotek_get_source_language();

    // if the language count we are requested to do, IS the source language.  Do a direct query against the nodes, using EntityFieldQuery.
    if ($language == $source_language) {

      $bundles = lingotek_translatable_node_types(); // $bundles -- array ( 0 => 'page', 1 => 'article', )
      $query = new EntityFieldQuery();
      $query->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Published Nodes
      $query->entityCondition('bundle', $bundles); // The Content Types we Want Translated.
      $query->propertyCondition('language', $language, '='); // The Language to search for
      $result = $query->count()->execute();
    }
    else { // Otherwise, do a regular count, where we count field languages.
      $sum = 0;
      $type_field_mapping = lingotek_get_type_field_mapping();
      //debug( $type_field_mapping );
      // Loop though the type_field_mapping, and count this language for EACH node type and get a sum.
      foreach ($type_field_mapping as $type => $db_table) {

        $count = 0;

        $query = db_select($db_table, 'tbl')->fields('tbl');
        $query->condition('entity_type', 'node');
        $query->condition('deleted', 0);
        $query->condition('bundle', $type);
        $query->condition('language', $language);
        $count = $query->countQuery()->execute()->fetchAssoc();

        $sum += $count['expression'];
        //debug( 'Lang: ' . $language . ' Type: ' . $type . ' Count: ' . $count['expression'] );
      }
      $result = $sum;
    } // END:  regular count
  } // END:  isset $language

  return $result;
}

// END:  lingotek_get_node_count()

/**
 * Returns an array that maps a content_type to one (and just 1) of its translated fields.
 * We can then use that array to track how many nodes have been translated.
 */
function lingotek_get_type_field_mapping($node_type = NULL) {

  $type_field_mapping = array(); // Keep as an array, cause you have to loop it for each content type time you do a count.
  $node_types = lingotek_translatable_node_types(); // These are the node types marked for translation.  I need 1 field from each to count off of.
  $node_fields = lingotek_translatable_node_field_details(); // These are the fields that are translated.
  //debug( $node_types );
  //debug( $node_fields );
  // Match a translated node_type up with a translated node_field, and grab the db_table where its data is stored so we can query off of it.
  foreach ($node_types as $type) {
    //debug( $type );
    // Look for that type in one of the field bundles.
    foreach ($node_fields as $field) {
      //debug( $field );
      // Is this type, listed in the bundles for this field?  ie:  does this type, use this field?
      if (in_array($type, $field['bundles'])) {
        //debug( 'The Type: ' . $type . ' Uses the field: ' . $field['machine_name'] );
        $type_field_mapping[$type] = $field['db_table']; // Get a db table for this field that we can do a language count off of it.
        break; // Only need one field, bail on this loop.
      }
    } // END:  loop fields
  } // END:  loop types

  return $type_field_mapping;
}

// END:  lingotek_get_type_field_mapping()

/**
 * Outputs the language code with page count in json format.  Supplied to the dashboard.
 * Output Format: {'code':'en','docs':'500'}
 */
function lingotek_get_source_language_json() {

  $data = array();
  $source_language = lingotek_get_source_language();
  $data['code'] = Lingotek::convertDrupal2Lingotek($source_language);

  $data['edited'] = lingotek_count_nodes(LINGOTEK_NODE_SYNC_STATUS_EDITED);
  $data['pending'] = lingotek_count_nodes(LINGOTEK_NODE_SYNC_STATUS_PENDING);
  $data['current'] = lingotek_count_nodes(LINGOTEK_NODE_SYNC_STATUS_CURRENT);

  $data['docs'] = $data['edited'] + $data['pending'] + $data['current']; //lingotek_count_translation_managed_source_language_nodes( $source_language );

  return json_encode($data);
}

// END:  lingotek_get_source_language_json()

/**
 * Outputs the languages active in this installation.  Supplied to the dashboard.
 * Output Format: ["en","es"]
 */
function lingotek_get_localization_languages_json() {

  $languages = array_keys(current(language_list('enabled')));

  $result = array();
  foreach ($languages as $language) {
    $result[] = Lingotek::convertDrupal2Lingotek($language);
  }

  return json_encode($result);
}

// END:  lingotek_get_localization_languages_json()

/**
 * Generates Lingotek GUID for this installation.
 */
function lingotek_get_guid() {

  $guid = variable_get('lingotek_guid', '');
  if ($guid == '') {
    $guid = substr(hash('sha256', drupal_get_hash_salt()), 0, 16);
    variable_set('lingotek_guid', $guid);
  }

  return $guid;
}

// END:  lingotek_get_guid()


function lingotek_get_target_status($lingotek_locale = NULL) {

  $target_languages = LingotekAccount::instance()->getManagedTargets(TRUE);

  // If we get a parameter, only return that language.  Otherwise return all the codes (dont return the source language).
  if (!is_null($lingotek_locale)) {

    $drupal_language_code = Lingotek::convertLingotek2Drupal($lingotek_locale);
    $target_is_active = in_array($lingotek_locale, array_keys($target_languages));

    $edited = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_EDITED, $drupal_language_code);
    $pending = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_PENDING, $drupal_language_code);
    $current = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $drupal_language_code);
    $total = $edited + $pending + $current;

    $response = array(
      'code' => $lingotek_locale, // Return this language code as the Lingotek language code.
      'active' => $target_is_active,
      'docs' => $total,
      'pending' => $pending,
      'edited' => $edited,
      'current' => $current
    );
  }
  else { // Otherwise Return a List of all the Active Languages
    foreach ($target_languages as $target) {

      $drupal_language_code = $target->language;
      $lingotek_locale = $target->lingotek_locale; //Lingotek::convertDrupal2Lingotek($drupal_language_code)
      $edited = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_EDITED, $drupal_language_code);
      $pending = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_PENDING, $drupal_language_code);
      $current = lingotek_count_node_targets(LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $drupal_language_code);
      $total = $edited + $pending + $current;

      $response[] = array(
        'code' => $lingotek_locale, // Return this language code as the Lingotek language code.
        'dcode' => $drupal_language_code,
        'active' => $target->active,
        'docs' => $total,
        'pending' => $pending,
        'edited' => $edited,
        'current' => $current
      );
    } // END:  foreach target language
  } // END:  Language List
  return $response;
}
