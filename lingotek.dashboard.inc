<?php
/**
 * @file
 * Lingotek Dashboard.
 */

/**
 * Tab:  Dashboard - The main Lingotek dashboard page.
 */
function lingotek_dashboard() {

  $output = '<div id="dashwrap" style=""><div id="dashboard" style=""></div></div>';
  $output .= lingotek_get_dashboard_code(); // Generates the dashboard javascript.
  return $output; 

} // END:  lingotek_dashboard()


/**
 * Tab:  Development - Test area.
 */
function lingotek_development() {

  $output = '';
  $output .= '';
  return $output;

}


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

    // Loop though each active language, and initialize the data.  Add the language code for each language, setting the counts to 0.  Skip the source language
    foreach ( $active_languages as $language_code ) {
      if( $language_code != $source_language ) { // Skip the source language.

        $cnt_docs = lingotek_get_node_count( $language_code ); // How many docuents are in this language?
        $cnt_mt = $cnt_docs; // How many docs were machine translated?
        $cnt_pr = 0; // How many docs were human translated?
        $cnt_sync = ( ( $cnt_mt + $cnt_pr ) ) ;  // Err..  not this -> Percent completed.  ( total docs / (mt docs + pr docs) ) * 100

        $totals[ ] = array(
          'code'    => lingotek_drupal_to_lingotek_language_code( $language_code ),
          'docs'    => $cnt_docs, // Number of Docs in this language.
          'mt'      => $cnt_mt, // Translated
          'pr'      => $cnt_pr, // Reviewed
          'synced'  => $cnt_sync  // In Sync
        );
      }
    } // END: Foreach Active Language - Initialize Totals Array

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

  $index = lingotek_get_dashboard_language_mappings();

  $method = $_SERVER['REQUEST_METHOD'];



  if ( $_POST ) { // Insert a new Target Language

    $message = 'POST: Insert a new Target Language';
    $dashboard_language_code = $_POST[ 'code' ];
    $data = $_POST;

    if( strlen( $dashboard_language_code ) > 1 ) {

      // These are the languages that are currently active in this Drupal installation.
      $active_languages = language_list('enabled');
      $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.

      // if the Source language is not active, activate it.
      if ( !array_key_exists( $drupal_language_code, $active_languages[1] ) ) {
        locale_add_language( $drupal_language_code );  // Function from the Locale module.
      }

      $response = array(
        'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ), // Return this language code as the Lingotek language code.
        'docs' => '0',
        'mt' => '0',
        'pr' => '0',
        'synced' => '0'
      );

    }
  } // END:  POST


  if ( $_GET ) { 

    if ( $method == 'GET' ) {
        // Retrieve the Target Languages and Status
        $message = 'GET: Retrieve the Target Languages and Status';
        $data = $_GET;
        $dashboard_language_code = $_GET[ 'code' ];
        $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.

        $cnt_docs = lingotek_get_node_count( $drupal_language_code ); // How many docuents are in this language?
        $cnt_mt = $cnt_docs; // How many docs were machine translated?
        $cnt_pr = 0; // How many docs were human translated?
        $cnt_sync = ( ( $cnt_mt + $cnt_pr ) ) ; 

        $active_languages = language_list('enabled');
        $response = array();
    
        foreach( $active_languages[1] as $language ) {
          $set = array(
            'code' => lingotek_drupal_to_lingotek_language_code( $language->language ), // Return this language code as the Lingotek language code.
            'active' => true,
            'docs' => $cnt_docs,
            'mt' => $cnt_mt,
            'pr' => $cnt_pr,
            'synced' => $cnt_sync
          );

          $response[] = $set;
    
        } // END:  Language Loop

    } // END: Real Get


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


    } // END:  PUT


    if ( $method == 'DELETE' ) {

      // Initiate Language Delete
      $message = 'DELETE: Remove a language ABC';
      $dashboard_language_code = $_GET[ 'code' ];
      $drupal_language_code = lingotek_dashboard_to_drupal_language_code( $dashboard_language_code ); // Map the code from a dashboard language code, to a drupal one.

      $response = array(
        'code' => lingotek_drupal_to_lingotek_language_code( $drupal_language_code ),
        'active' => false,
        'success' => true
      ); // Format:  {'code':'es','active':'false','success':true}

      // Language delete logic.

    } // END:  DELETE

  } // END:  GET


  $message .= print_r( $_lingotek_locale, true );

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






//////// THIS NEEDS TO MOVE TO THE LingotekApi.php


/**
 * Get Account Status
 * Request:  http://cp.lingotek.com:8080/billing/account.json?community=B2MMD3X5&external_id=community_admin&oauth_key=28c279fa-28dc-452e-93af-68d194a2c366&oauth_secret=0e999486-3b4d-47e4-ba9a-d0f3f0bbda73
 * Response:  {"state":"active","plan":{"trial_ends_at":0,"state":"active","activated_at":1355267936,"type":"cosmopolitan_monthly","languages_allowed":2,"language_cost_per_period_in_cents":14900}}
 * Will return FALSE or a json decoded object.
 */
function lingotek_get_account_status( ) {

  $result = false;
  $fields = array(
    'community'    => urlencode( variable_get( 'lingotek_community_identifier', '' ) ),
    'external_id'  => urlencode( variable_get( 'lingotek_login_id', '' ) ),
    'oauth_key'    => urlencode( variable_get( 'lingotek_oauth_consumer_id', '' ) ),
    'oauth_secret' => urlencode( variable_get( 'lingotek_oauth_consumer_secret', '' ) ),
  );
  //dpm( $fields );

  if( ( isset($fields['community']) && $fields['community'] != '' ) && ( isset($fields['external_id']) && $fields['external_id'] != '' )  && ( isset($fields['oauth_key']) && $fields['oauth_key'] != '' )  && ( isset($fields['oauth_secret']) && $fields['oauth_secret'] != '') ) {

    $url = LINGOTEK_BILLING_SERVER . '?';
    foreach( $fields as $key=>$value ) { 
      $url .= $key . '=' . $value . '&';
    }
    $url = substr( $url, 0, -1 );

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
    //curl_setopt( $ch, CURLINFO_HEADER_OUT, TRUE );
    $response = curl_exec( $ch );
    $info = curl_getinfo( $ch );
    curl_close( $ch );

    $json = json_decode( $response );
    if ( isset( $json ) ) { // Did we get valid data back?  If not, $json is NULL.
      
    }

    dpm( '- - - - - - - - - - - - - - -' );
    dpm( 'URL: ' . $url );
    dpm( 'INFO:' );
    dpm( $info );
    dpm( 'RESULT (json):' );
    dpm( $json );
    dpm( '- - - - - - - - - - - - - - -' );
  } // END:  has credentials

  return $result;

} // END:  lingotek_get_account_status()




