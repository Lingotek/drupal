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
  lingotek_account_check_enterprise_status();

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

    // Loop though each active target language, and initialize the data.  Add the language code for each language, setting the counts to 0.  Skip the source language
    $target_languages = lingotek_get_target_languages( );

    foreach ( $target_languages as $language ) {
      if ( $language[ 'active' ] === TRUE && $language[ 'code' ] != $source_language ) { // Only active languages.
        $cnt_docs = lingotek_get_node_count( $language[ 'code' ] ); // How many docuents are in this language?
        $cnt_mt = $cnt_docs; // How many docs were machine translated?
        $cnt_pr = 0; // How many docs were human translated?
        $cnt_sync = ( ( $cnt_mt + $cnt_pr ) ) ; 

        $totals[] = array(
          'code' => lingotek_drupal_to_lingotek_language_code( $language[ 'code' ] ), // Return this language code as the Lingotek language code.
          'active' => $language[ 'active' ],
          'docs' => $cnt_docs,
          'mt' => $cnt_mt,
          'pr' => $cnt_pr,
          'synced' => $cnt_sync
        );

      } // END:  if active
    } // END:  foreach target language

    //       <script src="https://gmc.lingotek.com/js/lingotek.min.js"></script>
    $script = '
      <script src="http://10.0.11.173:3000/js/lingotek.min.js"></script>
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
          "cms_distribution": "%s",
          "module_version": "%s",
          "endpoint_url": "%s",
          "upload_url": "%s",
          "download_url": "%s",
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
      variable_get( 'drupal_distro', 'standard'), // drupal commons identifier     
      LINGOTEK_VERSION, // module version
      $endpoint, // endpoint url
      $upload_url,
      $download_url,
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
  // https://docs.google.com/document/d/1_uFxeaGcIIke1YqRj6bNm42VvwzEyEO9YqureyJy5to/edit


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
      'mt' => '0',
      'pr' => '0',
      'synced' => '0'
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
      $add = lingotek_add_target_language( $drupal_language_code );

      // Flag the MT Upload process, noting that nodes need to sync their translation targets on next run.
      variable_set( 'lingotek_resync_node_language_targets', 1 );

      // Add the node sync target language entries to the lingotek table.
      lingotek_add_target_sync_status_to_all_nodes( $drupal_language_code );

      $response = array(
        'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ), // Return this language code as the Lingotek language code.
        'active' => TRUE,
        'docs' => '0',
        'mt' => '0',
        'pr' => '0',
        'synced' => '0'
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
    $delete = lingotek_delete_target_language( $drupal_language_code ); // Mark this language as Active => False

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
          $cnt_mt = $cnt_docs; // How many docs were machine translated?
          $cnt_pr = 0; // How many docs were human translated?
          $cnt_sync = ( ( $cnt_mt + $cnt_pr ) ) ; 
  
          $response = array(
            'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ), // Return this language code as the Lingotek language code.
            'active' => $target_languages[ $drupal_language_code ][ 'active' ],
            'docs' => $cnt_docs,
            'mt' => $cnt_mt,
            'pr' => $cnt_pr,
            'synced' => $cnt_sync
          );

        }

      }
      else { // Otherwise Return a List of all the Active Languages

        foreach ( $target_languages as $language ) {

          $cnt_docs = lingotek_get_node_count( $language[ 'code' ] ); // How many docuents are in this language?
          $cnt_mt = $cnt_docs; // How many docs were machine translated?
          $cnt_pr = 0; // How many docs were human translated?
          $cnt_sync = ( ( $cnt_mt + $cnt_pr ) ) ; 
  
          $response[] = array(
            'code' => lingotek_drupal_to_lingotek_language_code( $language[ 'code' ] ), // Return this language code as the Lingotek language code.
            'active' => $language[ 'active' ],
            'docs' => $cnt_docs,
            'mt' => $cnt_mt,
            'pr' => $cnt_pr,
            'synced' => $cnt_sync
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
    // drupal_json_output(array('response' => $response, 'message' => $message, 'data' => $data, 'method' => $method  ));
    // Regular
    drupal_json_output_cors( $response );   
  }

} // END:  lingotek_dashboard_command_ajax


/**
 * Gets the language mappings used in the dashboard.  These are the code that get sent to the endpoints.
 * They must be mapped from the dashboard language code back to a Drupal language code.
 * Due to sunspots, this list does not match the standard language mappings list.
 * Returns a Drupal language code.
 */
function lingotek_dashboard_to_drupal_language_code( $dashboard_language_code = NULL ) {

  // Dashboard Language Code ->  Drupal Language Code
  $index = array( 
    'en' => 'en',
    'ja' => 'ja',
    'de' => 'de',
    'es' => 'es',
    'fr' => 'fr',
    'zh' => 'zh-hans',
    'it' => 'it',
    'pt' => 'pt',
    'nl' => 'nl',
    'ko' => 'ko',
    'ar' => 'ar',
    'ru' => 'ru',
    'sv' => 'sv',
    'no' => 'fa',
    'pl' => 'pl',
    'tr' => 'tr',
    'da' => 'da',
    'fi' => 'fi',
    'iw' => 'he',
    'el' => 'el',
    'cs' => 'cs',
    'id' => 'id',
    'lt' => 'lt',
    'hu' => 'hu',
    'ca' => 'ca',
    'th' => 'th',
    'sk' => 'sk',
    'bs' => 'bs',
    'hr' => 'hr',
    'sr' => 'sr',
    'bg' => 'bg',
    'et' => 'et',
    'tl' => 'fil',
    'hi' => 'hi',
    'is' => 'is',
    'lv' => 'lv',
    'ro' => 'ro',
    'sl' => 'sl',
    'uk' => 'uk',
    'ur' => 'ur',
    'vi' => 'vi'
  );

  $result = FALSE;

  if ( isset( $dashboard_language_code ) && isset( $index[ $dashboard_language_code ] ) ) {
    $result = $index[ $dashboard_language_code ];
  }

  return $result;

} // END:  lingotek_dashboard_to_drupal_language_code()


/**
 * Given a Drupal language code, Lookup the Lingotek equivalent.  (Uses the core lingotek.reference.inc file.)
 */
function lingotek_drupal_to_lingotek_language_code( $drupal_language_code = NULL ) {

  global $_lingotek_locale;

  $result = FALSE;
  if ( isset( $drupal_language_code ) && isset( $_lingotek_locale[ $drupal_language_code ] ) ) {
    $result = $_lingotek_locale[ $drupal_language_code ];
  }

  return $result;
}


/**
 * Counts all the nodes of a specific language.
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

  // launch the batch translate process.
  // lingotek_bulk_mt_batch_create( ); 
 
  //$api = LingotekApi::instance();
  //$status = $api->getAccountStatus( );
  //dpm( $status );



  return $output;

} // END:  lingotek_development()


/**
 * Just a development page.
 */
function lingotek_test() {

  $output = '<h1>Test</h1>';
/*
  $drupal_language_code = lingotek_drupal_language( 'en_US' );
  $output .= '<br>Drupal Language Code: ' . $drupal_language_code;

  $nid = lingotek_get_node_id_from_document_id( 111609 );

  $output .= '<br>NodeID: ' . $nid;
*/


$string = 'a:12:{s:4:"sets";a:1:{i:0;a:15:{s:7:"sandbox";a:0:{}s:7:"results";a:1:{i:0;s:14:"Upload Node: 7";}s:7:"success";b:0;s:5:"start";d:1357688924.3271820545196533203125;s:7:"elapsed";d:6562.6099999999996725819073617458343505859375;s:5:"title";s:44:"Sending Content to Lingotek for Translation.";s:8:"finished";s:38:"lingotek_bulk_mt_upload_batch_finished";s:4:"file";s:72:"sites/all/modules/lingotek/lib/Drupal/batch/lingotek.mt.batch.upload.inc";s:12:"init_message";s:24:"Initializing.<br/>&nbsp;";s:16:"progress_message";s:29:"Completed @current of @total.";s:13:"error_message";s:22:"An error has occurred.";s:3:"css";a:0:{}s:5:"total";i:2;s:5:"count";i:1;s:5:"queue";a:2:{s:4:"name";s:17:"drupal_batch:12:0";s:5:"class";s:10:"BatchQueue";}}}s:16:"has_form_submits";b:0;s:11:"current_set";i:0;s:11:"progressive";b:1;s:3:"url";s:5:"batch";s:11:"url_options";a:0:{}s:10:"source_url";s:33:"admin/settings/lingotek/mt-upload";s:8:"redirect";s:7:"<front>";s:5:"theme";s:5:"seven";s:17:"redirect_callback";s:11:"drupal_goto";s:2:"id";s:2:"12";s:13:"error_message";s:76:"Please continue to <a href="/batch?id=12&amp;op=finished">the error page</a>";}';

dpm( unserialize( $string ) );


/*
4	document_id_en	111611
4	node_sync_status	CURRENT
4	project_id	20342
4	target_sync_status_es	CURRENT
4	target_sync_status_it	CURRENT
4	vault_id	8787


6	document_id_en	111615
6	node_sync_status	PENDING
6	project_id	20342
6	target_sync_status_es	PENDING
6	target_sync_status_it	PENDING
6	vault_id	8787
*/

  return $output;

}








/**
 * Batch Create - Upload:  Create a Bulk Machine Translate UPLOAD Batch.
 */
function lingotek_bulk_mt_upload_batch_create( ) {

  $operations = array();
  $bundles = lingotek_translatable_node_types(); // The nodes we translate.


/*
  // This will actually grab all nodes, of the type we have marked for translation.
  $query = new EntityFieldQuery();
  $query->entityCondition( 'entity_type', 'node' )->entityCondition( 'bundle', $bundles )->propertyCondition('status', 1); // Published Nodes of the correct Content Types that we want Translated.
  $nodes = $query->execute();

  foreach ( array_shift( $nodes ) as $node ) {
    // Saftey Checks.  Could start with them here.
    // Does this node ALREADY have a forign language translation?
    $operations[] = array( 'lingotek_machine_translate_node', array( $node->nid ) );
  }
*/

  // Grab nodes that are currently marked 'EDITED'
  $query = db_select( 'lingotek', 'l' )->fields( 'l' );
  $query->condition( 'lingokey', 'node_sync_status' );
  $query->condition( 'lingovalue', LINGOTEK_NODE_SYNC_STATUS_EDITED );

  $result = $query->execute();
  watchdog( 'lingotek_batch_mt', 'Query: !nodes', array( '!nodes' => watchdog_format_object( $query ) ), WATCHDOG_DEBUG );
  watchdog( 'lingotek_batch_mt', 'Query Results: !nodes', array( '!nodes' => watchdog_format_object( $result ) ), WATCHDOG_DEBUG );


  while ($record = $result->fetchAssoc()) {
    $operations[] = array( 'lingotek_machine_translate_node', array( $record['nid'] ) );
    watchdog( 'lingotek_batch_mt', 'BATCH ITEM NODE: @node_id', array( '@node_id' => $record['nid'] ), WATCHDOG_DEBUG );
  }


  watchdog( 'lingotek_batch_mt', 'CREATE UPLOAD BATCH: batch has: @node_count nodes', array( '@node_count' => count( $operations ) ), WATCHDOG_DEBUG );
  watchdog( 'lingotek_batch_mt', 'BATCH DETAILS: !nodes', array( '!nodes' => watchdog_format_object( $operations ) ), WATCHDOG_DEBUG );

  $batch = array(
    'title' => t('Sending Content to Lingotek for Translation.'),
    'operations' => $operations,
    'finished' => 'lingotek_bulk_mt_upload_batch_finished',
    'file' => drupal_get_path('module', 'lingotek') . '/lib/Drupal/batch/lingotek.mt.batch.upload.inc',
  );

  batch_set($batch);
  batch_process('<front>'); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.

} // END:  lingotek_bulk_mt_upload_batch_create()


/**
 * Batch Create - Download:  Create a Bulk Machine Translate DOWNLOAD Batch.
 */
function lingotek_bulk_mt_download_batch_create( $language_code ) {

  $operations = array();

  if ( $language_code ) {

    $dashboard_language_code = $language_code;
    $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code );
    $lingotek_language_code = lingotek_drupal_to_lingotek_language_code( $drupal_language_code );
    // NOTE!!!   lingotek_download_document() does its OWN drupal to lingotek lookup.  So pass in the DRUPAL language code
    // watchdog( 'lingotek_batch_mt', 'LANG CODE FORMAT: Dash: @dash - Drupal: @drupal -- Lingotek: @lingotek', array( '@dash' => $dashboard_language_code, '@drupal' => $drupal_language_code,'@lingotek' => $lingotek_language_code, ), WATCHDOG_DEBUG );
/*
    $source_language = lingotek_get_source_language();
    $document_lookup = 'document_id_' . $source_language;

    // Only pull back nodes included in the Lingotek table (a node with a Lingotek managed translation)
    $query = db_select( 'lingotek', 'l' )->fields( 'l' );
    $query->condition( 'lingokey', $document_lookup );

    $result = $query->execute();
    while ($record = $result->fetchAssoc()) {
      $operations[] = array( 'lingotek_download_node_machine_translation', array( $record['nid'], $drupal_language_code ) );
    }
*/

    // Grab nodes that are currently marked 'PENDING'
    $query = db_select( 'lingotek', 'l' )->fields( 'l' );
    $query->condition( 'lingokey', 'node_sync_status' );
    $query->condition( 'lingovalue', LINGOTEK_NODE_SYNC_STATUS_PENDING );
  
    $result = $query->execute();
    watchdog( 'lingotek_batch_mt', 'Query: !nodes', array( '!nodes' => watchdog_format_object( $query ) ), WATCHDOG_DEBUG );
    watchdog( 'lingotek_batch_mt', 'Query Results: !nodes', array( '!nodes' => watchdog_format_object( $result ) ), WATCHDOG_DEBUG );
  
  
    while ($record = $result->fetchAssoc()) {
      $operations[] = array( 'lingotek_download_node_machine_translation', array( $record['nid'], $drupal_language_code ) );
      watchdog( 'lingotek_batch_mt', 'BATCH DL ITEM NODE: @node_id', array( '@node_id' => $record['nid'] ), WATCHDOG_DEBUG );
    }




    watchdog( 'lingotek_batch_mt', 'CREATE DOWNLOAD BATCH: batch has: @node_count nodes', array( '@node_count' => count( $operations ) ), WATCHDOG_DEBUG );
  watchdog( 'lingotek_batch_mt', 'BATCH DETAILS: !nodes', array( '!nodes' => watchdog_format_object( $operations ) ), WATCHDOG_DEBUG );
  
    $batch = array(
      'title' => t('Downloading Lingotek Translated Content'),
      'operations' => $operations,
      'finished' => 'lingotek_bulk_mt_download_batch_finished',
      'file' => drupal_get_path('module', 'lingotek') . '/lib/Drupal/batch/lingotek.mt.batch.download.inc',
    );

    batch_set($batch);
    batch_process('<front>'); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.

  } // END:  if language_code

}


/**
 * Batch Create:  Bulk Machine Translate Upload.
 */
function lingotek_dashboard_mt_upload( ) {

  lingotek_bulk_mt_upload_batch_create( );

}


/**
 * Batch Create:  Bulk Machine Translate Download.
 */
function lingotek_dashboard_mt_download() {

  if ( isset( $_GET['code'] ) ) {

    lingotek_bulk_mt_download_batch_create( $_GET['code'] );

  } // If Language Code
  else {
      watchdog( 'lingotek_batch_mt', 'BATCH DL Error: Language Code Not Specified.  Unable to start translated content download.', array( ), WATCHDOG_DEBUG );
  }

}
