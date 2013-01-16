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
  // If we havent already, check the account status.
  if ( variable_get( 'lingotek_enterprise_account', "ACCOUNT STATUS NOT SET" ) === "ACCOUNT STATUS NOT SET" ) {
    lingotek_account_check_enterprise_status();
  }

  $output = '<div id="dashwrap" style=""><div id="dashboard" style=""></div></div>';
  $output .= lingotek_get_dashboard_code(); // Generates the dashboard javascript.

  return $output; 

} // END:  lingotek_dashboard()


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

  $totals = array();
  $guid = lingotek_get_guid(); // 16 char string
  $source_language = lingotek_get_source_language(); // ie:  en - Drupal Language Code
  $active_languages = lingotek_get_active_languages( );  // array of Drupal language codes.  ie:  en.

  if ( variable_get( 'lingotek_community_identifier', '' ) != '' && variable_get( 'lingotek_oauth_consumer_id', '' ) != '' && variable_get( 'lingotek_oauth_consumer_secret', '' ) != '' && 
      variable_get( 'lingotek_login_id', '' ) != '' && variable_get( 'lingotek_project', '' ) != '' && variable_get( 'lingotek_vault', '' ) != '' ) {

    // Loop though each active target languages, and get sync status counts.
    $target_languages = lingotek_get_target_languages( FALSE );
    $totals = array();
    foreach ($target_languages as $language) {
      if ( $language[ 'active' ] ) {
        $edited  = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_EDITED,  $language[ 'code' ] );
        $pending = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_PENDING, $language[ 'code' ] );
        $current = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $language[ 'code' ] );
  
        // Format:  [{"code":"fr","active":true,"docs":100,"pending":20,"current":80},
        $totals[] = array(
          'code' => lingotek_drupal_to_dashboard_language_code( $language['code'] ),
          'active' => TRUE,
          'docs' => 0,
          //'edited' => $edited,
          //'pending' => $pending,
          'pending' => $edited + $pending,
          'current' => $current
        );
      }
    } // END:  foreach language counts

    $script = '
      <script src="'.LINGOTEK_GMC_SERVER.'/js/lingotek.min.js"></script>
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

    $output = sprintf( $script, 
      // lingotek 
      variable_get( 'lingotek_community_identifier', '' ), //community
      variable_get( 'lingotek_oauth_consumer_id', '' ), //oauth_key
      variable_get( 'lingotek_oauth_consumer_secret', '' ), //oauth_secret
      variable_get( 'lingotek_login_id', '' ), //external_id
      variable_get( 'lingotek_vault', '' ), //tm_vault_id
      variable_get( 'lingotek_workflow', '' ), //workflow_id
      variable_get( 'lingotek_project', '' ), //project_id
      variable_get( 'lingotek_activation_first_name', '' ), //first_name
      variable_get( 'lingotek_activation_last_name', '' ), //last_name
      variable_get( 'lingotek_activation_email', '' ), //email
      // cms
      $guid, // installation GUID
      url( '<front>', array( 'absolute' => TRUE ) ), // site url
      variable_get( 'site_name', 'Unidentified Drupal Site' ), // sitename
      'Drupal', // cms type
      VERSION, // drupal version
      variable_get( 'lingotek_cms_tag', ''), // CMS tag     
      LINGOTEK_VERSION, // module version
      $endpoint, // endpoint url
      $upload_url,
      $download_url,
      $sync_url,
      // data
      lingotek_get_source_language_json(), // source_language
      lingotek_get_localization_languages_json(), // localization_languages - The languages “localized” on Drupal
      json_encode( $totals ) // The target languages they are using on Lingotek
    );

  }
  else {
    $output = 'Your Lingotek module is not properly configured.  Please run the Lingotek <a href="' . url( 'admin/config/lingotek/setup' ) . '">Installation Wizard</a>.';
  }

  return $output;

} // END:  lingotek_get_dashboard_code()


/**
 * Ajax Command Processing for the Lingotek dashboard.
 */
function lingotek_dashboard_command_ajax() {

  $data = array();
  $response = array();
  $message = 'Not doing anything.';

  $method = $_SERVER['REQUEST_METHOD'];

  // 1. Update a Target Language (Initiate a Machine Translation Batch)
  if ( $method == 'PUT' ) {

    // Initiate Machine Translation
    $message = 'PUT: Initiate Machine Translation';
    parse_str( file_get_contents("php://input"), $put_vars );
    $type = $put_vars['type'];
    $dashboard_language_code = $put_vars['code'];
    $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.

    $response = array(
      'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ),
      'active' => TRUE,
      'docs' => '0',
      'pending' => '0',
      'current' => '0'
    );

    // Launch the machine translation process!

  } // END:  PUT


  // 2. Add/Insert Target Language
  if ( $_POST ) { 

    $message = 'POST: Insert a new Target Language';
    $dashboard_language_code = $_POST[ 'code' ];

    if ( strlen( $dashboard_language_code ) > 1 ) {

      // These are the languages that are currently active in this Drupal installation.
      $active_languages = language_list('enabled');
      $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.

      // if the Source language is not active, activate it.
      if ( !array_key_exists( $drupal_language_code, $active_languages[1] ) ) {
        locale_add_language( $drupal_language_code );  // Function from the Locale module.
      }

      // Adds the language to the local list of languages we do translation for, and to the Lingotek project.
      $add = lingotek_add_target_language( $drupal_language_code );

      // Add the node sync target language entries to the lingotek table.
      lingotek_add_target_sync_status_to_all_nodes( $drupal_language_code );

      $response = array(
        'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ), // Return this language code as the Lingotek language code.
        'active' => TRUE,
        'docs' => '0',
        'pending' => '0',
        'current' => '0'
      );

    }
  } // END:  POST


  // 3. Remove/Delete Target Language
  if ( $method == 'DELETE' ) {

    // Initiate Language Delete
    $message = 'DELETE: Remove a language ABC';
    parse_str( file_get_contents("php://input"), $delete_vars );

    $dashboard_language_code = $delete_vars[ 'code' ];
    $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.      

    // Removes the language to the local list of languages we do translation for, and also removes the target language from the Lingotek project.
    $delete = lingotek_delete_target_language( $drupal_language_code ); // Mark this language as Active => False

    // Removes the node sync target language entries from the lingotek table.
    lingotek_delete_target_sync_status_for_all_nodes( $drupal_language_code );

    $response = array(
      'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ),
      'active' => FALSE,
      'success' => $delete
    ); // Format:  {'code':'es','active':'FALSE','success':TRUE}

  } // END:  DELETE


  // 4. Retrieve the Target Languages and Status
  if ( $method == 'GET' ) {

      // Retrieve the Target Languages and Status
      $message = 'GET: Retrieve the Target Languages and Status';
      $data = $_GET;

      $response = array();
      $target_languages = lingotek_get_target_languages( );

      // If we get a parameter, only return that language.  Otherwise return all the codes (dont return the source language).
      if ( isset( $_GET[ 'code' ] ) ) {

        $dashboard_language_code = $_GET[ 'code' ];
        $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.

        //$response = print_r( $target_languages[ $drupal_language_code ][ 'active' ] );
        if ( isset( $target_languages[ $drupal_language_code ] ) ) {

          $cnt_docs = lingotek_get_node_count( $drupal_language_code ); // How many docuents are in this language?
          $edited  = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_EDITED,  $drupal_language_code );
          $pending = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_PENDING, $drupal_language_code );
          $current = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $drupal_language_code );

          $response = array(
            'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ), // Return this language code as the Lingotek language code.
            'active' => $target_languages[ $drupal_language_code ][ 'active' ],
            'docs' => $cnt_docs,
            'pending' => $edited + $pending,
            'current' => $current
          );

        }

      }
      else { // Otherwise Return a List of all the Active Languages

        foreach ( $target_languages as $language ) {

          $cnt_docs = lingotek_get_node_count( $language[ 'code' ] ); // How many docuents are in this language?
          $edited  = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_EDITED,  $language[ 'code' ] );
          $pending = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_PENDING, $language[ 'code' ] );
          $current = lingotek_count_node_targets( LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $language[ 'code' ] );
  
          $response[] = array(
            'code' => lingotek_drupal_to_lingotek_language_code( $language[ 'code' ] ), // Return this language code as the Lingotek language code.
            'active' => $language[ 'active' ],
            'docs' => $cnt_docs,
            'pending' => $edited + $pending,
            'current' => $current
          );
 
        } // END:  foreach target language

      } // END:  Language List

  } // END:  GET

  if ( $method == 'PUT' ) {
    $commands = array();
    ajax_deliver( array('#type' => 'ajax', '#commands' => $commands) );
  }
  else {
    // Debugging
    // drupal_json_output_cors(array('response' => $response, 'message' => $message, 'data' => $data, 'method' => $method  ));
    // Regular
    drupal_json_output_cors( $response );   
  }

} // END:  lingotek_dashboard_command_ajax


/**
 * Gets the current sync counts from the lingotek table for the LANGUAGE TARGETS.
 *
 * Detaults to CURRENT.
**/
function lingotek_count_node_targets( $status = LINGOTEK_TARGET_SYNC_STATUS_CURRENT, $drupal_language_code ) {

  $node_language_target_key = 'target_sync_status_' . $drupal_language_code;
  $lingotek_language_code = lingotek_drupal_to_lingotek_language_code( $drupal_language_code );

  $query = db_select( 'lingotek', 'l' )->fields( 'l' );
  $query->condition( 'lingokey', $node_language_target_key );
  $query->condition( 'lingovalue', $status );

  $result = $query->countQuery()->execute()->fetchAssoc();

  if ( is_array( $result ) ) {
    $count = array_shift( $result );
  }
  else {
    $count = 0;
  }

  return $count;

} // END:  lingotek_count_nodes()


/**
 * Counts all the nodes of a specific language.
 * 
 * OLD METHOD.   Replaced by:  lingotek_count_node_targets() (for reporting node target counts)
 * Not removed yet, because its still used in other places for reporting other counts.
 *
 * Limits the counts to node / fields that are marked translatable.
 */
function lingotek_get_node_count( $language = NULL ) {

  $result = 0;

  if ( isset( $language ) ) {

    $source_language = lingotek_get_source_language();

    // if the language count we are requested to do, IS the source language.  Do a direct query against the nodes, using EntityFieldQuery.
    if ( $language == $source_language ) {

      $bundles = lingotek_translatable_node_types(); // $bundles -- array ( 0 => 'page', 1 => 'article', )
      $query = new EntityFieldQuery();
      $query->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Published Nodes
      $query->entityCondition('bundle', $bundles ); // The Content Types we Want Translated.
      $query->propertyCondition('language', $language, '=' ); // The Language to search for
      $result = $query->count()->execute();

    }
    else { // Otherwise, do a regular count, where we count field languages.

      $sum = 0;
      $type_field_mapping = lingotek_get_type_field_mapping();
      //debug( $type_field_mapping );

      // Loop though the type_field_mapping, and count this language for EACH node type and get a sum.
      foreach ( $type_field_mapping as $type => $db_table ) {

        $count = 0;

        $query = db_select( $db_table, 'tbl' )->fields( 'tbl' );
        $query->condition( 'entity_type', 'node' );
        $query->condition( 'deleted', 0 );
        $query->condition( 'bundle', $type );
        $query->condition( 'language', $language );
        $count = $query->countQuery()->execute()->fetchAssoc();

        $sum += $count['expression'];
        //debug( 'Lang: ' . $language . ' Type: ' . $type . ' Count: ' . $count['expression'] );
      }
      $result = $sum;
    } // END:  regular count
  } // END:  isset $language

  return $result;

} // END:  lingotek_get_node_count()


/**
 * Returns an array that maps a content_type to one (and just 1) of its translated fields.
 * We can then use that array to track how many nodes have been translated.
 */
function lingotek_get_type_field_mapping( $node_type = NULL ) {

  $type_field_mapping = array(); // Keep as an array, cause you have to loop it for each content type time you do a count.
  $node_types = lingotek_translatable_node_types(); // These are the node types marked for translation.  I need 1 field from each to count off of.
  $node_fields = lingotek_translatable_node_field_details(); // These are the fields that are translated.
  //debug( $node_types );
  //debug( $node_fields );

  // Match a translated node_type up with a translated node_field, and grab the db_table where its data is stored so we can query off of it.
  foreach ( $node_types as $type ) {
    //debug( $type );
    // Look for that type in one of the field bundles.
    foreach ( $node_fields as $field ) {
      //debug( $field );
      // Is this type, listed in the bundles for this field?  ie:  does this type, use this field?
      if ( in_array( $type, $field['bundles'] ) ) {
        //debug( 'The Type: ' . $type . ' Uses the field: ' . $field['machine_name'] );
        $type_field_mapping[ $type ] = $field['db_table']; // Get a db table for this field that we can do a language count off of it.
        break; // Only need one field, bail on this loop.
      }
    } // END:  loop fields
  } // END:  loop types

  return $type_field_mapping;

} // END:  lingotek_get_type_field_mapping()


/**
 * Outputs the language code with page count in json format.  Supplied to the dashboard.
 * Output Format: {'code':'en','docs':'500'}
 */
function lingotek_get_source_language_json() {

  $data = array();
  $source_language = lingotek_get_source_language();

  $data['code'] = lingotek_drupal_to_lingotek_language_code( $source_language );
  $data['docs'] = lingotek_get_node_count( $source_language );

  return json_encode( $data );

} // END:  lingotek_get_source_language_json()


/**
 * Outputs the languages active in this installation.  Supplied to the dashboard.
 * Output Format: ["en","es"]
 */
function lingotek_get_localization_languages_json() {

  $laguages = language_list('enabled');
  $laguages = array_shift( $laguages );
  $laguages = array_keys( $laguages );

  $result = array();
  foreach ( $laguages as $language ) {
    $result[] = lingotek_drupal_to_lingotek_language_code( $language );
  }

  return json_encode( $result );

} // END:  lingotek_get_localization_languages_json()


/**
 * Generates Lingotek GUID for this installation.
 */
function lingotek_get_guid() {

  $guid = variable_get( 'lingotek_guid', '' );
  if ( $guid == '' ) {
    $guid = substr(hash('sha256', drupal_get_hash_salt()), 0, 16);
    variable_set( 'lingotek_guid', $guid );
  }

  return $guid;

} // END:  lingotek_get_guid()












function S_______P_______A_______C_______E_______R() {}


/**
 * Tab:  Development - Test area.
 */
function lingotek_development() {

  $output = '';

  $account_state = '';
  $account_plan = '';
  $account_max_languages = '';

  $account = lingotek_account_get_status();
  //dpm($account);
  if ( is_object( $account ) ) {
    $account_state = $account->state;
    if ( is_object( $account->plan ) ) {
      $account_plan = $account->plan->type;
      $account_max_languages = $account->plan->languages_allowed;
    }
  }

  $max_languages = lingotek_account_max_languages();
  $enterprise_account = lingotek_account_check_enterprise_status();
  $enterprise_account_text = ( $enterprise_account ) ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>';


  // Left Column - - - - - - - - - - - - - - - - - - - - - - - - - - - - 
  $output .= '<div style="border: 0px solid red; width: 50%; float: left;">';
  $output .= '<h1> Account Status </h1>';

  $output .= '<p><strong>Enterprise Account:</strong> ' . $enterprise_account_text . '</p>';

  $output .= '<p>
                <div><strong>State:</strong> ' . $account_state . '</div>
                <div><strong>Plan:</strong> ' . $account_plan . '</div>
              </p>';

  $output .= '<p>';
  $output .= '<div><strong>Max Languages:</strong> ' . $max_languages . '</div>';
  $output .= '</p>';
  $output .= '</div>';


  // Right Column - - - - - - - - - - - - - - - - - - - - - - - - - - - - 
  $output .= '<div style="border: 0px solid red; width: 40%; float: left;">';
  $output .= '<h1> Installation Credentials </h1>';

  $output .= '<p><strong>Credentials:</strong> (Live) NON-ENTERPRISE</p>';
  $output .= '<p>';
  $output .= '<div><strong>community:</strong> ' . variable_get( 'lingotek_community_identifier', '' ) . '</div>';
  $output .= '<div><strong>oauth_key:</strong> ' . variable_get( 'lingotek_oauth_consumer_id', '' ) . '</div>';
  $output .= '<div><strong>oauth_secret:</strong> ' . variable_get( 'lingotek_oauth_consumer_secret', '' ) . '</div>';
  $output .= '<div><strong>external_id:</strong> ' . variable_get( 'lingotek_login_id', '' ) . '</div>';
  $output .= '<div><strong>tm_vault_id:</strong> ' . variable_get( 'lingotek_vault', '' ) . '</div>';
  $output .= '<div><strong>workflow_id:</strong> ' . variable_get( 'lingotek_workflow', '' ) . '</div>';
  $output .= '<div><strong>project_id:</strong> ' . variable_get( 'lingotek_project', '' ) . '</div>';
  $output .= '</p>';
  $output .= '</div>';

  /* Credential Sets */

  $output .= '<div>&nbsp;</div>';
  $output .= '<div>&nbsp;</div>';
  $output .= '<div>&nbsp;</div>';

  return $output;

} // END:  lingotek_development()


/**
 * Just a development page.
 */
function lingotek_test() {

  $output = '<h1>Test</h1>';

  return $output;

}
