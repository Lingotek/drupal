<?php
/**
 * @file
 * Lingotek Dashboard.
 */


/**
 * Adds elements needed for the Lingotek dashboard.
 */
function lingotek_dashboard() {
  $output = '';
  $dashboard = '';

  $output = '
    <div id="dashwrap" style="">
      <div id="dashboard" style=""></div>
    </div>
  ';

  $dashboard = lingotek_get_dashboard();

  // Counts
  $source_language_docs = new EntityFieldQuery();
  $source_language_docs->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Just Published
  $source_language_doc_count = $source_language_docs->execute();
  // $source_language_doc_count = $source_language_docs->count()->execute();
  //$output .= '<div>Source Lang Doc Count: </div><pre>' .print_r( $source_language_doc_count) .'</pre><div></div>';

  return $output . $dashboard; 

} // END:  lingotek_dashboard()



/**
 * Generates the core javascript dashboard page.
 */
function lingotek_get_dashboard() {

  $community = variable_get( 'lingotek_community_identifier', '' );
  $oauth_key = variable_get( 'lingotek_oauth_consumer_id', '' );
  $oauth_secret = variable_get( 'lingotek_oauth_consumer_secret', '' );
  $external = variable_get( 'lingotek_login_id', '' );
  $project = variable_get( 'lingotek_project', '' );
  $vault = variable_get( 'lingotek_vault', '' );
  $workflow = variable_get( 'lingotek_workflow', '' );

  $sitename = variable_get( 'site_name', 'Drupal Site' );
  $guid = _update_manager_unique_identifier();

  $default_language = language_default();
  $source_language = variable_get( 'lingotek_source_language', $default_language->language );



  $totals = array();



  if( $community != '' && $oauth_key != '' && $oauth_secret != '' && $external != '' && $project != '' && $vault != '' ) {

    $translatable_fields = lingotek_translatable_node_field_details();
    //debug( $translatable_fields );
    //debug( '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );

    $content_types_and_fields_to_translate		= variable_get( 'lingotek_translate_fields', false ); // $translate_settings
    //debug( $content_types_and_fields_to_translate );

    $content_types_to_translate = array_keys( $content_types_and_fields_to_translate ); //$content_type_list
    //debug( $content_types_to_translate );

    $active_languages = array_shift( language_list('enabled') );
    //debug( $active_languages );

    $language_count = new EntityFieldQuery();
    $language_count->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Published Nodes
    $language_count->entityCondition('bundle', $content_types_to_translate ); // The Content Types we Want Translated.
    $translatable_nodes = $language_count->count()->execute();

    // Loop though each active language, and initialize the data.
    foreach ( $active_languages as $key => $language ) {
      // Start with the 
      $totals[ $key ] = array(
        'code'    => $key,
        'docs'    => $translatable_nodes,
        'mt'      => 0,
        'pr'      => 0,
        'synced'  => 0
      );
    } // END: Initialize

    // Loop though each active language
    foreach ( $active_languages as $key => $language ) {
      // Lets loop though the translatable fields (the fields marked translatable)
      //debug( 'Working with: ' . $language->name );
      foreach ( $translatable_fields as $field ) {
        // Then loop though each bundle that uses this field, making sure that we want to translate that.  IE:  is it included in $content_types_to_translate list?
        //debug( $field );
        foreach ( $field['bundles'] as $bundle ) {
          //debug( $bundle );
          // 1st make sure the bundle has been selected for translation.
          if ( in_array( $bundle, $content_types_to_translate ) ) {
            // Next, look though the fields and figure out which one it is.
            //debug( 'This content type is marked for translation, check the field now:  ' .$field['machine_name']   );
            if ( in_array( $field['machine_name'], $content_types_and_fields_to_translate[ $bundle ] ) ) {
              // Now, do the counts for this field.
              $query = db_select( $field['db_table'], 'd' )->fields( 'd' );
              $query->condition('deleted', '0');
              $query->condition('bundle', $bundle);
              $query->condition('language', $key);
              $result = $query->countQuery()->execute()->fetchAssoc();
              //$result['expression']
              $totals[ $key ][ 'mt' ] += $result['expression'];
              $totals[ $key ][ 'synced' ] += $result['expression'];
            }
          }
        }
      } // END:  foreach $translatable_fields
    }

    $target_language_list = array();
    foreach( $totals as $total ) {
      $target_language_list[] = $total;
    }
    $target_language_list = json_encode( $target_language_list );


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
          // cms 
          "cms_site_id": "%s", // the host name or ip address
          "cms_site_name": "%s",
          "cms_type": "drupal",
          "cms_version": "%s", // ex. drupal version 
          "endpoint_url": "/admin/settings/lingotek/command",  // (ex. https://mysite.com/admin/lingotek/target)
          "module_version": "2.0beta", // our drupal module version 
          "source_language": {"code":"%s","docs":"%s"},   //in Drupal, "docs" refers to the # of "nodes"
          "target_languages": %s
        };
         lingotek.dashboard(containerId,config); 
        });
      </script>
    ';

    $output = sprintf( $script, 
      $community,
      $oauth_key,
      $oauth_secret,
      $external,
      $vault,
      $workflow,
      $project,
      $guid,
      $sitename,
      VERSION,
      $source_language,
      $translatable_nodes,
      $target_language_list
    );

  } else {

    $output = 'Your Lingotek module is not Properly configured.  Please run the Lingotek <a href="' .url( 'admin/config/lingotek/setup' ) .'">configuration process</a>.';

  }

  return $output;
} // END:  lingotek_get_dashboard()




/**
 * Ajax Command Processing for the Lingotek dashboard.
 */
function lingotek_dashboard_command_ajax() {

  /*
    watchdog('lingotek_dashboard', 'Got a dash command: @message',
        array('@message' => 'Test message.'), WATCHDOG_ERROR);
  */

  $index = array(); //indexed by the lingotek language code

  $index['en'] = array( 'drupal_language_code' => 'en', 'lingotek_language_code' => 'en', 'name' => 'English' );
  $index['de'] = array( 'drupal_language_code' => 'de', 'lingotek_language_code' => 'de', 'name' => 'German' );
  $index['it'] = array( 'drupal_language_code' => 'it', 'lingotek_language_code' => 'it', 'name' => 'Italian' );
  $index['ko'] = array( 'drupal_language_code' => 'ko', 'lingotek_language_code' => 'ko', 'name' => 'Korean' );
  $index['es'] = array( 'drupal_language_code' => 'es', 'lingotek_language_code' => 'es', 'name' => 'Spanish' );
  $index['ja'] = array( 'drupal_language_code' => 'ja', 'lingotek_language_code' => 'ja', 'name' => 'Japanese' );
  $index['fr'] = array( 'drupal_language_code' => 'fr', 'lingotek_language_code' => 'fr', 'name' => 'French' );
  $index['zh'] = array( 'drupal_language_code' => 'zh-hans', 'lingotek_language_code' => 'zh', 'name' => 'Chinese' );
  $index['nl'] = array( 'drupal_language_code' => 'nl', 'lingotek_language_code' => 'nl', 'name' => 'Dutch' );
  $index['ar'] = array( 'drupal_language_code' => 'ar', 'lingotek_language_code' => 'ar', 'name' => 'Arabic' );
  $index['ru'] = array( 'drupal_language_code' => 'ru', 'lingotek_language_code' => 'ru', 'name' => 'Russian' );
  $index['sv'] = array( 'drupal_language_code' => 'sv', 'lingotek_language_code' => 'sv', 'name' => 'Swedish' );
  $index['no'] = array( 'drupal_language_code' => 'fa', 'lingotek_language_code' => 'no', 'name' => 'Persian' );
  $index['pl'] = array( 'drupal_language_code' => 'pl', 'lingotek_language_code' => 'pl', 'name' => 'Polish' );
  $index['tr'] = array( 'drupal_language_code' => 'tr', 'lingotek_language_code' => 'tr', 'name' => 'Turkish' );
  $index['da'] = array( 'drupal_language_code' => 'da', 'lingotek_language_code' => 'da', 'name' => 'Danish' );
  $index['fi'] = array( 'drupal_language_code' => 'fi', 'lingotek_language_code' => 'fi', 'name' => 'Finnish' );
  $index['el'] = array( 'drupal_language_code' => 'el', 'lingotek_language_code' => 'el', 'name' => 'Greek' );
  $index['cs'] = array( 'drupal_language_code' => 'cs', 'lingotek_language_code' => 'cs', 'name' => 'Czech' );
  $index['id'] = array( 'drupal_language_code' => 'id', 'lingotek_language_code' => 'id', 'name' => 'Indonesian' );
  $index['lt'] = array( 'drupal_language_code' => 'lt', 'lingotek_language_code' => 'lt', 'name' => 'Lithuanian' );
  $index['hu'] = array( 'drupal_language_code' => 'hu', 'lingotek_language_code' => 'hu', 'name' => 'Hungarian' );
  $index['ca'] = array( 'drupal_language_code' => 'ca', 'lingotek_language_code' => 'ca', 'name' => 'Catalan' );
  $index['th'] = array( 'drupal_language_code' => 'th', 'lingotek_language_code' => 'th', 'name' => 'Thai' );
  $index['sk'] = array( 'drupal_language_code' => 'sk', 'lingotek_language_code' => 'sk', 'name' => 'Slovak' );
  $index['bs'] = array( 'drupal_language_code' => 'bs', 'lingotek_language_code' => 'bs', 'name' => 'Bosnian' );
  $index['hr'] = array( 'drupal_language_code' => 'hr', 'lingotek_language_code' => 'hr', 'name' => 'Croatian' );
  $index['sr'] = array( 'drupal_language_code' => 'sr', 'lingotek_language_code' => 'sr', 'name' => 'Serbian' );
  $index['bg'] = array( 'drupal_language_code' => 'bg', 'lingotek_language_code' => 'bg', 'name' => 'Bulgarian' );
  $index['et'] = array( 'drupal_language_code' => 'et', 'lingotek_language_code' => 'et', 'name' => 'Estonian' );
  $index['tl'] = array( 'drupal_language_code' => 'fil', 'lingotek_language_code' => 'tl', 'name' => 'Filipino' );
  $index['hi'] = array( 'drupal_language_code' => 'hi', 'lingotek_language_code' => 'hi', 'name' => 'Hindi' );
  $index['is'] = array( 'drupal_language_code' => 'is', 'lingotek_language_code' => 'is', 'name' => 'Icelandic' );
  $index['lv'] = array( 'drupal_language_code' => 'lv', 'lingotek_language_code' => 'lv', 'name' => 'Latvian' );
  $index['ro'] = array( 'drupal_language_code' => 'ro', 'lingotek_language_code' => 'ro', 'name' => 'Romanian' );
  $index['sl'] = array( 'drupal_language_code' => 'sl', 'lingotek_language_code' => 'sl', 'name' => 'Slovenian' );
  $index['uk'] = array( 'drupal_language_code' => 'uk', 'lingotek_language_code' => 'uk', 'name' => 'Ukrainian' );
  $index['ur'] = array( 'drupal_language_code' => 'ur', 'lingotek_language_code' => 'ur', 'name' => 'Urdu' );
  $index['vi'] = array( 'drupal_language_code' => 'vi', 'lingotek_language_code' => 'vi', 'name' => 'Vietnamese' );


  $data = array();
  $response = array();
  $message = 'Not doing anything.';

  $method = $_SERVER['REQUEST_METHOD'];



  if ( $_POST ) { // Insert a new Target Language
    $message = 'POST: Insert a new Target Language';
    $data = $_POST;

    $code = $_POST[ 'code' ];
    if( strlen( $code ) > 1 ) {

      // These are the languages that are currently active in this Drupal installation.
      $active_languages = language_list('enabled');

      // if the Source language is not active, activate it.
      if ( !array_key_exists( $code, $active_languages[1] ) ) {
        locale_add_language( $index[$code]['drupal_language_code'] );  // Function from the Locale module.
      }

      $response = array(
        'code' => $code,
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
    
        $code = $_GET[ 'code' ];
    
        $active_languages = language_list('enabled');
        $response = array();
    
        foreach( $active_languages[1] as $language ) {
          $set = array(
            'code' => $language->language,
            'docs' => '0',
            'mt' => '0',
            'pr' => '0',
            'synced' => '0'
          );
    
          $response[] = $set;
    
        } // END:  Language Loop

    } // END: Real Get

    if ( $method == 'PUT' ) {

        // Initiate Machine Translation
        $message = 'PUT: Initiate Machine Translation';
        $data = $_GET;
    
        $code = $_GET[ 'code' ];
        $type = $_GET[ 'type' ];

      


    } // END:  PUT

  } // END:  GET

  // Debugging
  drupal_json_output(array('response' => $response, 'message' => $message, 'data' => $data, 'method' => $method  ));

  // Regular
  //drupal_json_output( $response );

} // END:  lingotek_dashboard_command_ajax























/**
 * Developer Test Page
 */
function lingotek_translate() {
  $output = '';

  //$node = node_load( 5 );
  //dpm( $node );

  //$node = translation_node_get_translations( 9 );
  //dpm( $node );


  $login_id = variable_get( 'lingotek_login_id', '' );
  $password = variable_get( 'lingotek_password', '' );

  $api = LingotekApi::instance();
  //debug( $api );
  //$results = $api->requestV2( 'listProjects', array( 'externalId' => $login_id ) );
  //$results = $api->listVaults( );

//  $node = node_load( 30 );
//  dpm( $node );


/*
  // Sends a Doc to Lingotek - With Target Languages

  $results = lingotek_node_init_default( $node );
  debug( $results ); // we just get a null....
/**/


/*
  // Retrieves Document Translations
  $lingotek_document_id = lingotek_lingonode($node->nid, 'document_id_' . $node->language);
  debug( $lingotek_document_id );
  $translation_target_items = lingotek_get_document_targets( $lingotek_document_id );  // Gives back An array of translation target items. ALL OF THESE CLAIM %Complete is 0!!!!  LIES!  But you get the translation target id!!!
  debug( $translation_target_items );

  foreach( $translation_target_items as $target ) {
    $translation_target_id = $target->id;
    $translation_target = $api->getTranslationTarget( $translation_target_id );
    $target_language = $translation_target->language;
    //debug(  );
    //debug( $translation_target->percentComplete );

    if( $translation_target->percentComplete == 100 ) {
      //debug( 'Translation is done! Pull it down!' );
      
      lingotek_download_document( $node, $target_language );

    } // END:  100% Complete 
    else {
      debug( 'Translation is ' . $translation_target->percentComplete . ' % complete.' );
    }
  } // END:  Looping $translation_target_items
/**/



  // Pull the data back?  FAILS!   THis call looks at the %complete in the 'Translation Targets'.  WHich always seems 0.   Because it uses: lingotek_get_document_targets
  //$results = lingotek_node_sync(&$node);
  //debug( $results );













  // Adds a Document to Lingotek - BUT NOT THE TARGET LANGUAGES!  USE:  ->  lingotek_node_init_default( $node )
  //$results = $api->addContentDocument( $node ); // This did work!  Whoo!  But no target languages are attached.
  //debug( $results );








  //$data = json_decode( $results['body'] );

  //dpm( $results );

  /*
     'id' => 391,
     'name' => 'D1 Local',
     'state' => 'Active',
     'companyId' => 0,
     'percentComplete' => 0,
     'createdDate' => '2012-11-15',
     'ownerId' => 257,
     'access' => 
  */
  /*
  $projects = array();
  if ( $data->results == 'success' ) {
    foreach( $data->projects as $project ) {
      if( $project->state == 'Active' ) {
        $projects[ $project->id ] = $project->name;
      }
    }
  } // END:  Success
  */


  return $output; 
} // END:  lingotek_translate()





/**
 * Developer Test Page:  Some Quick Node Counts
 */
function lingotek_statistics() {

  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'node')->propertyCondition('status', 1);
  $nodeCount = $query->count()->execute();


  $source_language		= variable_get( 'lingotek_source_language', 'Not Set' );
  $target_language		= variable_get( 'lingotek_target_language', 'Not Set' );
  $install_status		= variable_get( 'lingotek_install_complete', 'Not Set' );


  $headers = array(
    array( 'data' => t( 'Information' ) ),
    array( 'data' => t( 'Data' ) )
  );

  $caption = 'Information & Statistics';

  $rows[] = array(
    array( 'data' => t( 'Published Nodes' ) ),
    array( 'data' => t( $nodeCount ) )
  );

  $rows[] = array(
    array( 'data' => t( 'Source Language' ) ),
    array( 'data' => t( $source_language ) )
  );
  $rows[] = array(
    array( 'data' => t( 'Target Language' ) ),
    array( 'data' => t( $target_language ) )
  );
  $rows[] = array(
    array( 'data' => t( 'Install Complete' ) ),
    array( 'data' => t( $install_status ) )
  );

  /*
  $rows[] = array( 
    array('data' => t('Row 2 – Cell 1')),
    array('data' => t('Row 2 – Cell 2'))
  );
  */

$output = '';
//$output .= '<div>Hello!</div>';

/*
$output .= '


<div style="border: 1px solid green;">

  <div style="border: 1px solid red; width: 49%; float: left;">
  	<div>Information</div>
  </div>
  <div style="border: 1px solid red; width: 49%; float: right;">
  	<div>Information</div>
  </div>

</div>

';
*/


  $table = theme( 'table', array(
    'headers' => $headers,
    'caption' => $caption,
    'sticky' => 1,
    'rows' => $rows
  ));

  $community = variable_get( 'lingotek_community_identifier', '' );
  $oauth_key = variable_get( 'lingotek_oauth_consumer_id', '' );
  $oauth_secret = variable_get( 'lingotek_oauth_consumer_secret', '' );
  $external = variable_get( 'lingotek_login_id', '' );
  $project = variable_get( 'lingotek_project', '' );
  $vault = variable_get( 'lingotek_vault', '' );
  $workflow = variable_get( 'lingotek_workflow', '' );

  $end = '<div>&nbsp;</div>';
  $end .= '<div><strong style="width: 110px; display: inline-block;">Community:</strong> ' . $community .' </div>';
  $end .= '<div><strong style="width: 110px; display: inline-block;">OAuth Key:</strong> ' . $oauth_key .' </div>';
  $end .= '<div><strong style="width: 110px; display: inline-block;">OAuth Secret:</strong> ' . $oauth_secret .' </div>';
  $end .= '<div><strong style="width: 110px; display: inline-block;">External:</strong> ' . $external .' </div>';
  $end .= '<div><strong style="width: 110px; display: inline-block;">Project:</strong> ' . $project .' </div>';
  $end .= '<div><strong style="width: 110px; display: inline-block;">Vault:</strong> ' . $vault .' </div>';
  $end .= '<div><strong style="width: 110px; display: inline-block;">Workflow:</strong> ' . $workflow .' </div>';

  return $output . $table . $end;
} // END:  lingotek_translate()


/**
 * Developer Test Page
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
        $translatable_fields[] =  array( 
          'entity_type' => $type,
          'machine_name' => $field['field_name'],
          'db_table' => array_shift( array_keys( $field['storage']['details']['sql']['FIELD_LOAD_CURRENT'] ) ),
          'bundles' => $field['bundles']['node'],
        );
      }
      //echo '<br>';
    }
  }

  return $translatable_fields;
}
/*
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
      2 => 'press_release',
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
    ),
  ),
)
*/



function lingotek_development() {
  $output = '';
  $totals = array();


  $translatable_fields = lingotek_translatable_node_field_details();
  //debug( $translatable_fields );
  //debug( '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );

  $content_types_and_fields_to_translate		= variable_get( 'lingotek_translate_fields', false ); // $translate_settings
  //debug( $content_types_and_fields_to_translate );

  $content_types_to_translate = array_keys( $content_types_and_fields_to_translate ); //$content_type_list
  //debug( $content_types_to_translate );

  $active_languages = array_shift( language_list('enabled') );
  //debug( $active_languages );

  $language_count = new EntityFieldQuery();
  $language_count->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Published Nodes
  $language_count->entityCondition('bundle', $content_types_to_translate ); // The Content Types we Want Translated.
  $translatable_nodes = $language_count->count()->execute();

  // $translatable_nodes - Count of all the nodes we want translated, based off what the user selected.
  //debug( 'Total Nodes to Translate: ' . $translatable_nodes );
  //debug( '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );
  //debug( '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );





/**/
  // Loop though each active language, and initialize the data.
  foreach ( $active_languages as $key => $language ) {
    // Start with the 
    $totals[ $key ] = array(
      'code'    => $key,
      'docs'    => 0,
      'mt'      => 0,
      'pr'      => 0,
      'synced'  => 0
    );
  } // END: Initialize

  // Loop though each active language
  foreach ( $active_languages as $key => $language ) {
    // Lets loop though the translatable fields (the fields marked translatable)
    //debug( 'Working with: ' . $language->name );
    foreach ( $translatable_fields as $field ) {
      // Then loop though each bundle that uses this field, making sure that we want to translate that.  IE:  is it included in $content_types_to_translate list?
      //debug( $field );
      foreach ( $field['bundles'] as $bundle ) {
        //debug( $bundle );
        // 1st make sure the bundle has been selected for translation.
        if ( in_array( $bundle, $content_types_to_translate ) ) {
          // Next, look though the fields and figure out which one it is.
          //debug( 'This content type is marked for translation, check the field now:  ' .$field['machine_name']   );
          if ( in_array( $field['machine_name'], $content_types_and_fields_to_translate[ $bundle ] ) ) {
            // Now, do the counts for this field.
            $query = db_select( $field['db_table'], 'd' )->fields( 'd' );
            $query->condition('deleted', '0');
            $query->condition('bundle', $bundle);
            $query->condition('language', $key);
            $result = $query->countQuery()->execute()->fetchAssoc();
            //$result['expression']
            $totals[ $key ][ 'docs' ] += $result['expression'];
          }
        }
      }
    } // END:  foreach $translatable_fields
  }

  $final = array();
  foreach( $totals as $total ) {
    $final[] = $total;
  }


  //debug( '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );
  //debug( '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );





  debug( '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ' );
  debug( json_encode( $final ) );


/*
  $language_count = new EntityFieldQuery();
  $language_count->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Published Nodes
  $language_count->entityCondition('bundle', $content_type_list ); // The Content Types we Want Translated.

  //$language_count->fieldCondition( 'body', 'value' );
  //$language_count->fieldLanguageCondition($this->fields[0], 'en', '<>', NULL, 'group'); 

  $results = $language_count->execute();
  debug( $results );
*/












/*

SELECT count(*), `language` FROM field_data_body 
WHERE deleted = 0
#AND language = 'es'
GROUP BY language
ORDER BY language
;


*/


/*
  foreach( $translate_fields as $content_type => $content_fields ) {
    debug( $content_type );
    debug( $content_fields );
  }



*/

/*
  $default_language = language_default();
  $source_language = variable_get( 'lingotek_source_language', $default_language->language );

*/




/*

  foreach( $translate_fields as $content_type => $content_fields ) {
    debug( $content_type );
    debug( $content_fields );
  }
*/




/*
[
{"code":"de","docs":"500","mt":"500","pr":"500","synced":"500"},
{"code":"nl", "docs":"500","mt":"500","pr":"0","synced":"500"}] 
*/




/*

 
  // Counts
  $source_language_docs = new EntityFieldQuery();
  $source_language_docs->entityCondition('entity_type', 'node')->propertyCondition('status', 1); // Just Published

  $source_language_docs->entityCondition('bundle', array( 'page', 'article') );


  $source_language_doc_count = $source_language_docs->execute();
  // $source_language_doc_count = $source_language_docs->count()->execute();

debug( $source_language_doc_count);
*/


  return $output;
} // END:  Development

























































/*
// Updated v2 - Auto Provision Community
  $api = LingotekApi::instance();
  $community = $api->createCommunity($name = null);
  if( $community['code'] == 200 ) {
    $community = json_decode( $community['body'] );
    debug( $community );
      /*
      //  stdClass::__set_state(array(
      //   'results' => 'success',
      //   'community' => 'UGE9REAQ',
      //   'oauth_key' => '97de99ff-f7a7-42e9-a2e3-4e87869b4027',
      //   'oauth_secret' => '46bcf6ee-ab86-43f5-88b2-6df1eae90f52',
      //   'external_id' => 'community_admin',
      //   'tm_vault_id' => 205,
      //   'workflow_id' => '4357be33-07ab-41b6-87eb-cfd1a537e993',
      //   'project_id' => 404,
      //))

    variable_set( 'lingotek_community_identifier', $community->community );
    variable_set( 'lingotek_oauth_consumer_id', $community->oauth_key );
    variable_set( 'lingotek_oauth_consumer_secret', $community->oauth_secret );
    variable_set( 'lingotek_login_id', $community->external_id ); // Used as the 'External ID'.  For old users, this is their login name / email.
    variable_set( 'lingotek_project', $community->project_id );
    variable_set( 'lingotek_vault', $community->tm_vault_id );

  } else {
    debug( 'There was an error provisioning a community.' );
  }
/**/




















/*  Example Code:

/*
  $box = theme( 'fieldset', array(
    '#type' => 'fieldset',
    '#title' => t('Make changes on page via hook_page_alter()'),
    '#children' => array(
        'first_para' => array(
      	  '#type' => 'markup',
      	  '#markup' => '<p>A paragraph about some stuff...</p>',
        ),
    )
  ));
*/
/*
$fieldset = array(
 '#theme' => 'fieldset',
 '#title' => 'my fieldset',
//doesn't make fields collapsible
 '#collapsible' => TRUE,
// although the following works
 '#attached' => array(
        'js' => array(
          'misc/form.js',
          'misc/collapse.js',
        ),
   ),
 '#attributes' => array(
          'class' => array('collapsible', 'collapsed'),
        ),
);


*/
/*
  $form['checkboxes_fieldset'] = array(
    '#title' => t("Generated Checkboxes"),
    // The prefix/suffix provide the div that we're replacing, named by
    // #ajax['wrapper'] above.
    '#prefix' => '<div id="checkboxes-div">',
    '#suffix' => '</div>',
    '#type' => 'fieldset',
    '#description' => t('This is where we get automatically generated checkboxes'),
  );
*/
