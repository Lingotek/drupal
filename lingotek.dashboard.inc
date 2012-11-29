<?php
/**
 * @file
 * Lingotek Dashboard.
 */
 

/**
 * Creates the central Lingotek dashboard
 */
function lingotek_dashboard() {
  $output = '';
  $dashboard = '';

  $output = '
    <div id="dashwrap" style="">
      <div id="dashboard" style=""></div>
    </div>
  ';





  if ( 1 == 2 ) {
    $output .= '<div>Your installation is not setup correctly</div>';
    
  } else {
    $dashboard = lingotek_get_dashboard();
  }


  return $output . $dashboard; 
} // END:  lingotek_dashboard()



/**
 * Generates the core javascript dashboard code.
 */
function lingotek_get_dashboard() {

  $community = variable_get( 'lingotek_community_identifier', '' );
  $oauth_key = variable_get( 'lingotek_oauth_consumer_id', '' );
  $oauth_secret = variable_get( 'lingotek_oauth_consumer_secret', '' );
  $external = variable_get( 'lingotek_login_id', '' );
  $project = variable_get( 'lingotek_project', '' );
  $vault = variable_get( 'lingotek_vault', '' );
  $workflow = variable_get( 'lingotek_workflow', '' );

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
        "cms_site_id": "lingotek.com", // the host name or ip address
        "cms_site_name": "Lingotek",
        "cms_type": "drupal",
        "cms_version": "6.0", // ex. drupal version 
        "endpoint_url": "http://lingotek.com/drupal/lingotek/target",  // url for the endpoint above
        "module_version": "1.0", // our drupal module version 
        "source_language": {"code":"en","docs":"500"}, //in Drupal, "docs" refers to the # of "nodes"
        "target_languages": [{"code":"de","docs":"500","mt":"500","pr":"500","synced":"500"},
        {"code":"nl", "docs":"500","mt":"500","pr":"0","synced":"500"}] 
      };
       lingotek.dashboard(containerId,config); 
      });
    </script>
  ';

  $script = sprintf( $script, 
    $community,
    $oauth_key,
    $oauth_secret,
    $external,
    $vault,
    $workflow,
    $project
  );

  return $script;
}






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

  $node = node_load( 27 );
  dpm( $node );


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
  
  return $output . $table;
} // END:  lingotek_translate()

















function lingotek_development() {
  $output = '';





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
