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
 * Generates the code for the embedded Javascript dashboard.
 */
function lingotek_get_dashboard_code() {

  $totals = array();
  $guid = lingotek_get_guid(); // 16 char string
  $source_language = lingotek_get_source_language(); // ie:  en - Drupal Language Code
  $active_languages = lingotek_get_active_languages( );  // array of Drupal language codes.  ie:  en.

  if( variable_get( 'lingotek_community_identifier', '' ) != '' && variable_get( 'lingotek_oauth_consumer_id', '' ) != '' && variable_get( 'lingotek_oauth_consumer_secret', '' ) != '' && 
      variable_get( 'lingotek_login_id', '' ) != '' && variable_get( 'lingotek_project', '' ) != '' && variable_get( 'lingotek_vault', '' ) != '' ) {

    // Loop though each active target language, and initialize the data.  Add the language code for each language, setting the counts to 0.  Skip the source language
    $target_languages = lingotek_get_target_languages( );

    foreach( $target_languages as $language ) {
      if( $language[ 'active' ] === true && $language[ 'code' ] != $source_language ) { // Only active languages.
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

    //debug( 'TOTALS: - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );
    //debug( $totals );

    $script = '
      <script src="http://demo-gmc-01.aws.lingotek.com:8080/cc/lingotek.min.js"></script> 
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
          "module_version": "%s",
          "endpoint_url": "%s",
          // data
          "source_language":%s ,
          "localization_languages": %s,
          "target_languages": %s
        };
         lingotek.dashboard(containerId,config); 
        });
      </script>
    ';

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
      url( '<front>', array( 'absolute' => true ) ), // site url
      variable_get( 'site_name', 'Unidentified Drupal Site' ), // sitename
      'Drupal', // cms type
      VERSION, // drupal version
      LINGOTEK_VERSION, // module version
      '/admin/settings/lingotek/command', // endpoint
      // data
      lingotek_get_source_language_json(), // source_language
      lingotek_get_localization_languages_json(), // localization_languages - The languages “localized” on Drupal
      json_encode( $totals ) // The target languages they are using on Lingotek
    );

  } else {

    $output = 'Your Lingotek module is not Properly configured.  Please run the Lingotek <a href="' .url( 'admin/config/lingotek/setup' ) .'">Installation Wizard</a>.';

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
      'active' => true,
      'docs' => '0',
      'mt' => '0',
      'pr' => '0',
      'synced' => '0'
    );

    // Launch the machine translation process!
    lingotek_bulk_mt_batch_create( );

  } // END:  PUT


  // 2. Add/Insert Target Language
  if ( $_POST ) { 

    $message = 'POST: Insert a new Target Language';
    $dashboard_language_code = $_POST[ 'code' ];

    if( strlen( $dashboard_language_code ) > 1 ) {

      // These are the languages that are currently active in this Drupal installation.
      $active_languages = language_list('enabled');
      $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.

      // if the Source language is not active, activate it.
      if ( !array_key_exists( $drupal_language_code, $active_languages[1] ) ) {
        locale_add_language( $drupal_language_code );  // Function from the Locale module.
      }
      $add = lingotek_add_target_language( $drupal_language_code );

      $response = array(
        'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ), // Return this language code as the Lingotek language code.
        'active' => true,
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

    $response = array(
      'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ),
      'active' => false,
      'success' => $delete
    ); // Format:  {'code':'es','active':'false','success':true}

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
        if( isset( $target_languages[ $drupal_language_code ] ) ) {

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

      } else { // Otherwise Return a List of all the Active Languages

        foreach( $target_languages as $language ) {

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


  // Debugging
  //drupal_json_output(array('response' => $response, 'message' => $message, 'data' => $data, 'method' => $method  ));

  // Regular
  drupal_json_output( $response );

} // END:  lingotek_dashboard_command_ajax


/**
 * Gets the language mappings used in the dashboard.  These are the code that get sent to the endpoints.
 * They must be mapped from the dashboard language code back to a Drupal language code.
 * Due to sunspots, this list does not match the standard language mappings list.
 * Returns a Drupal language code.
 */
function lingotek_dashboard_to_drupal_language_code( $dashboard_language_code = null ) {

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

  $result = false;

  if( isset( $dashboard_language_code ) && isset( $index[ $dashboard_language_code ] ) ) {
    $result = $index[ $dashboard_language_code ];
  }

  return $result;

} // END:  lingotek_dashboard_to_drupal_language_code()


/**
 * Given a Drupal language code, Lookup the Lingotek equivalent.  (Uses the core lingotek.reference.inc file.)
 */
function lingotek_drupal_to_lingotek_language_code( $drupal_language_code = null ) {

  global $_lingotek_locale;

  $result = false;
  if( isset( $drupal_language_code ) && isset( $_lingotek_locale[ $drupal_language_code ] ) ) {
    $result = $_lingotek_locale[ $drupal_language_code ];
  }

  return $result;
}


/**
 * Counts all the nodes of a specific language.
 *
 * Limits the counts to node / fields that are marked translatable.
 */
function lingotek_get_node_count( $language = null ) {

  $result = 0;

  if( isset( $language ) ) {

    $source_language = lingotek_get_source_language();

    // if the language count we are requested to do, IS the source language.  Do a direct query against the nodes, using EntityFieldQuery.
    if( $language == $source_language ) {

      $bundles = lingotek_translatable_node_types(); // $bundles -- array ( 0 => 'page', 1 => 'article', )
      $query = new EntityFieldQuery();
      $query->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Published Nodes
      $query->entityCondition('bundle', $bundles ); // The Content Types we Want Translated.
      $query->propertyCondition('language', $language, '=' ); // The Language to search for
      $result = $query->count()->execute();

    } else { // Otherwise, do a regular count, where we count field languages.

      $sum = 0;
      $type_field_mapping = lingotek_get_type_field_mapping();
      //debug( $type_field_mapping );

      // Loop though the type_field_mapping, and count this language for EACH node type and get a sum.
      foreach( $type_field_mapping as $type => $db_table ) {

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
function lingotek_get_type_field_mapping( $node_type = null ) {

  $type_field_mapping = array(); // Keep as an array, cause you have to loop it for each content type time you do a count.
  $node_types = lingotek_translatable_node_types(); // These are the node types marked for translation.  I need 1 field from each to count off of.
  $node_fields = lingotek_translatable_node_field_details(); // These are the fields that are translated.
  //debug( $node_types );
  //debug( $node_fields );

  // Match a translated node_type up with a translated node_field, and grab the db_table where its data is stored so we can query off of it.
  foreach( $node_types as $type ) {
    //debug( $type );
    // Look for that type in one of the field bundles.
    foreach( $node_fields as $field ) {
      //debug( $field );
      // Is this type, listed in the bundles for this field?  ie:  does this type, use this field?
      if( in_array( $type, $field['bundles'] ) ) {
        //debug( 'The Type: ' . $type . ' Uses the field: ' . $field['machine_name'] );
        $type_field_mapping[ $type ] = $field['db_table']; // Get a db table for this field that we can do a language count off of it.
        break; // Only need one field, bail on this loop.
      }
    } // END:  loop fields
  } // END:  loop types

  return $type_field_mapping;

} // END:  lingotek_get_type_field_mapping()


/**
 * Content node types linked to 'translatable' fields.
 */
function lingotek_translatable_node_types() {

  $types = array();

  $fields = lingotek_translatable_node_field_details();
  foreach( $fields as $field ) {
    //debug( $field['bundles'] );
    foreach( $field['bundles'] as $bundle ) {
      $types[$bundle] = $bundle;
    }
  }

  if( count( $types ) > 0 ) {
    $types = array_keys( $types );
  }

  return $types;

}


/**
 * Goes though ALL the fields in the system and gets the details about the ones that are marked 'translatable'.
 */
function lingotek_translatable_node_field_details() {

  $fields = field_info_fields();
  $translatable_fields = array();
  /*
    debug( $fields );
    echo '<pre>';
    print_r( $fields );
    echo '</pre>';
  */
  foreach ($fields as $field_id => $field) {
    foreach ($field['bundles'] as $type => $instance) {
      /*
      echo '<br>FieldID: ' . $field_id;
      echo '<br>Field: ' . $field;
      echo '<br>Type: ' . $type;
      echo '<br>Instance: ' . $instance;
      */
      if (field_is_translatable($type, $field)) {
        //echo '<br>Translatable: YES!' ;
        $field_db_table = array_keys( $field['storage']['details']['sql']['FIELD_LOAD_CURRENT'] );
        $field_db_table = array_shift( $field_db_table );
        $translatable_fields[] =  array( 
          'entity_type' => $type,
          'machine_name' => $field['field_name'],
          'db_table' => $field_db_table ,
          'bundles' => $field['bundles']['node'],
        );
      }
      //echo '<br>';
    }
  }

  /* Return data format
    array (
      0 => 
      array (
        'entity_type' => 'node',
        'machine_name' => 'body',
        'db_table' => 'field_data_body',
        'bundles' => 
        array (
          0 => 'page',
          1 => 'article',
        ),
      ),
      1 => 
      array (
        'entity_type' => 'node',
        'machine_name' => 'title_field',
        'db_table' => 'field_data_title_field',
        'bundles' => 
        array (
          0 => 'article',
          1 => 'page',
        ),
      ),
    )
  */
  return $translatable_fields;

} // END:  lingotek_translatable_node_field_details()


/**
 * Get the currently active languages for this Drupal installation.
 */
function lingotek_get_active_languages( $get_details = false ) {
  $active_languages = language_list('enabled');
  $active_languages = array_shift( $active_languages );

  if( $get_details === false ) {
    $active_languages = array_keys( $active_languages );
  }
  /*
  Get_Details -> false
  array (
    0 => 'en',
    1 => 'es',
  )
  
  Get_Details -> true
  array (
    'en' => 
    stdClass::__set_state(array(
       'language' => 'en',
       'name' => 'English',
       'native' => 'English',
       'direction' => '0',
       'enabled' => '1',
       'plurals' => '0',
       'formula' => '',
       'domain' => '',
       'prefix' => '',
       'weight' => '0',
       'javascript' => '',
    )),
  */

  return $active_languages;

} // END:  lingotek_get_active_languages()


/**
 * Flags a target language as active:false in the Target Language tracking.
 */
function lingotek_delete_target_language( $drupal_language_code = null ) {

  $result = false;

  if( isset( $drupal_language_code ) ) {

    $target_languages = lingotek_get_target_languages();
    $target_languages[ $drupal_language_code ] = array( 'code' => $drupal_language_code, 'active' => false );
    lingotek_save_target_languages( $target_languages );
    $result = true;  // Hrm... variable_set isnt really into returning values.
  }

  return $result;

} // END:  lingotek_get_target_languages()


/**
 * Adds a target language to the Target Language tracking.
 */
function lingotek_add_target_language( $drupal_language_code = null ) {

  $result = false;

  if( isset( $drupal_language_code ) ) {

    $target_languages = lingotek_get_target_languages();
    $target_languages[ $drupal_language_code ] = array( 'code' => $drupal_language_code, 'active' => true );
    lingotek_save_target_languages( $target_languages );
    $result = true;  // Hrm... variable_set isnt really into returning values.
  }

  return $result;

} // END:  lingotek_get_target_languages()


/**
 * Get the languages we are going to translate into.  These are ONLY those added on the Dashboard.
 * These are Drupal language codes.  This is how we track lingotek translations vs active Drupal languages.
 */
function lingotek_get_target_languages( ) {

  $target_languages = variable_get( 'lingotek_target_languages', array() );
  return $target_languages;

} // END:  lingotek_get_target_languages()


/**
 * Save the Targeted Languages data.
 */
function lingotek_save_target_languages( $target_languages = null ) {

  if ( isset( $target_languages ) ) {
    variable_set( 'lingotek_target_languages', $target_languages );
  }

} // END:  lingotek_save_target_languages()


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
  foreach( $laguages as $language ) {
    $result[] = lingotek_drupal_to_lingotek_language_code( $language );
  }

  return json_encode( $result );

} // END:  lingotek_get_localization_languages_json()


/**
 * Gets the Lingotek Source Language ( Drupal variable: lingotek_source_language IE:  en).
 */
function lingotek_get_source_language() {

  $source_language = variable_get( 'lingotek_source_language' );
  return $source_language;

} // END:  lingotek_get_source_language()


/**
 * Generates Lingotek GUID for this installation.
 */
function lingotek_get_guid() {

  $guid = variable_get( 'lingotek_guid', '' );
  if( $guid == '' ) {
    $guid = substr(hash('sha256', drupal_get_hash_salt()), 0, 16);
    variable_set( 'lingotek_guid', $guid );
  }

  return $guid;

} // END:  lingotek_get_guid()












function S_______P_______A_______C_______E_______R() {}





function lingotek_test() {

  $output = '';

  return $output;

}


/*
lingotek_account_get_status()
lingotek_account_check_enterprise_status()
lingotek_account_is_enterprise()
lingotek_account_max_languages()
*/



/**
 * Tab:  Development - Test area.
 */
function lingotek_development() {

  $output = '';

  $account_state = '';
  $account_plan = '';
  $account_max_languages = '';

  $account = lingotek_account_get_status();
  dpm($account);
  if( is_object( $account ) ) {
    $account_state = $account->state;
    $account_plan = $account->plan->type;
    $account_max_languages = $account->plan->languages_allowed;
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

  $output .= '<p><strong>Credentials:</strong> (Live) Enterprise User from Matt</p>';
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



/*
// (Live) Enterprise User from Matt
variable_set( 'lingotek_community_identifier', 'AAYEUF9G' );
variable_set( 'lingotek_oauth_consumer_id', '1e1245a2-94b5-40e0-965c-5eaca006f009' );
variable_set( 'lingotek_oauth_consumer_secret', 'bde071f7-c366-443a-bae8-d85b4a73c240' );
variable_set( 'lingotek_login_id', 'community_admin' );
variable_set( 'lingotek_vault', '8678' );
variable_set( 'lingotek_workflow', '2ad91992-e639-471b-b8af-c533d71f5497' );
variable_set( 'lingotek_project', '20058' );



// (Live) My Auto Provisioned Community


// Blank Credentials
variable_get( 'lingotek_community_identifier', '' );
variable_get( 'lingotek_oauth_consumer_id', '' );
variable_get( 'lingotek_oauth_consumer_secret', '' );
variable_get( 'lingotek_login_id', '' );
variable_get( 'lingotek_vault', '' );
variable_get( 'lingotek_workflow', '' );
variable_get( 'lingotek_project', '' );

/**/

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
 * Batch Create: Bulk Machine Translate Batch.
 */
function lingotek_bulk_mt_batch_create( ) {

  $operations = array();
  $bundles = lingotek_translatable_node_types();

  $query = new EntityFieldQuery();
  $query->entityCondition( 'entity_type', 'node' )->entityCondition( 'bundle', $bundles )->propertyCondition('status', 1); // Published Nodes of the correct Content Types that we want Translated.
  //$query->propertyCondition('language', array('','und') ); // The Language to search for.
  $nodes = $query->execute();

  foreach( array_shift( $nodes ) as $node ) {
    $operations[] = array( 'lingotek_machine_translate_node', array( $node->nid ) );
  }

  watchdog( 'lingotek_batch_mt', 'CREATE: batch has: @node_count nodes', array( '@node_count' => count( $operations ) ), WATCHDOG_DEBUG );

  $batch = array(
    'title' => t('Sending Content for Translation'),
    'operations' => $operations,
    'finished' => 'lingotek_bulk_mt_batch_finished',
    'file' => drupal_get_path('module', 'lingotek') . '/lib/Drupal/batch/lingotek.mt.batch.inc',
  );

  batch_set($batch);
  batch_process('<front>'); // Needed if not inside a form _submit handler.  Setting redirect in batch_process.

} // END:  lingotek_bulk_mt_batch_create(

















