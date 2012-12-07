<?php
/**
 * @file
 * Lingotek Easy Install Process.
 */

/**
 * Routing:  Returning User, New Account or Already Setup
 */
function lingotek_setup() {

  // Check the current settings
  $current_community_identifier = variable_get( 'lingotek_community_identifier', '' );
  $current_oauth_consumer_id = variable_get( 'lingotek_oauth_consumer_id', '' );
  $current_oauth_consumer_secret = variable_get( 'lingotek_oauth_consumer_secret', '' );
  $current_login_id = variable_get( 'lingotek_login_id', '' );
  $current_project = variable_get( 'lingotek_project', '' );
  $current_vault = variable_get( 'lingotek_vault', '' );
  $current_workflow = variable_get( 'lingotek_workflow', '' );

  // Does the install already have connection credentials?
  if ($current_community_identifier == '' || $current_oauth_consumer_id == '' || $current_oauth_consumer_secret == '' || $current_login_id == '' || $current_project == '' || $current_vault == '' || $current_workflow == '' ) {
    drupal_goto( 'admin/config/lingotek/new-account' ); // If something is missing - Go though the Setup Process
  } else {
    drupal_goto( 'admin/lingotek' ); // We already have credentials, goto the Dashboard
  }

} // END:  Router

/**
 * New Account - Form
 */
function lingotek_setup_new_account_form() {

  $current_login_id = variable_get( 'lingotek_login_id', '' );
  $current_login_key = variable_get( 'lingotek_login_key', '' );
  $current_first_name = variable_get( 'lingotek_activation_first_name', '' );
  $current_last_name = variable_get( 'lingotek_activation_last_name', '' );
  $current_email = variable_get( 'lingotek_activation_email', '' );

  $form = array();

  $form['lingotek_user_directions_1'] = array( '#markup' => '<p>New to Lingotek?  Create a free account.</p><p>A Lingotek account is required to process your language translations.  <strong>All fields are required.</strong></p>' );
  $form['lingotek_user_directions_2'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form['lingotek_user_directions_3'] = array( '#markup' => '<h3>Your information</h3>' );
  $form['lingotek_user_directions_4'] = array( '#markup' => '<hr>' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['first_name'] = array(
    '#type' => 'textfield',
    '#title' => t('First name'),
    '#default_value' => $current_first_name,
    '#size' => 30,
    '#maxlength' => 128,
    '#required' => true,
  );
  $form['last_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Last name'),
    '#default_value' => $current_last_name,
    '#size' => 30,
    '#maxlength' => 128,
    '#required' => true,
  );
  $form['email'] = array(
    '#type' => 'textfield',
    '#title' => t('Email address'),
    '#default_value' => $current_email,
    '#size' => 40,
    '#maxlength' => 128,
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Next' ) // Create Account
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/account-settings' ) .'">Already have an account?</a></span>' );
  $form['lingotek_support_footer'] = array( '#markup' => lingotek_support_footer() );

  return $form;

} // END:  New Account - Form

/**
 * New Account - Form Processor
 * Provisions a Lingotek account and sends activation notice.
 */
function lingotek_setup_new_account_form_submit( $form, &$form_state ) {

  // Store the info so users dont have to retype stuff
    $first_name = $form_state[ 'values' ][ 'first_name' ];
    $last_name = $form_state[ 'values' ][ 'last_name' ];
    $email = $form_state[ 'values' ][ 'email' ];

    variable_set( 'lingotek_activation_first_name', $first_name );
    variable_set( 'lingotek_activation_last_name', $last_name );
    variable_set( 'lingotek_activation_email', $email );

  // Check the current settings
    $current_community_identifier = variable_get( 'lingotek_community_identifier', '' );
    $current_oauth_consumer_id = variable_get( 'lingotek_oauth_consumer_id', '' );
    $current_oauth_consumer_secret = variable_get( 'lingotek_oauth_consumer_secret', '' );
    $current_login_id = variable_get( 'lingotek_login_id', '' );
    $current_project = variable_get( 'lingotek_project', '' );
    $current_vault = variable_get( 'lingotek_vault', '' );
    $current_workflow = variable_get( 'lingotek_workflow', '' );

  // Provision a new community if any critical data is missing.
  if ($current_community_identifier == '' || $current_oauth_consumer_id == '' || $current_oauth_consumer_secret == '' || $current_login_id == '' || $current_project == '' || $current_vault == '' || $current_workflow == '' ) {

    // Provision a new Community & Save the Credentials
    $api = LingotekApi::instance();
    $community = $api->createCommunity($name = null);
    if( $community['code'] == 200 ) {
      $community = json_decode( $community['body'] );
      /*
        stdClass::__set_state(array(
           'results' => 'success',
           'community' => 'EDZDDWXA',
           'oauth_key' => '9a7187ea-fad9-41c0-9c0c-3eedbf9dffd0',
           'oauth_secret' => '58f059b9-d167-4f87-b29a-acbc4e0c67c9',
           'external_id' => 'community_admin',
           'tm_vault_id' => 212,
           'workflow_id' => '22092f38-a1f9-4499-b8d4-9e260e7e5d16',
           'project_id' => 411,
        ))
      */
      variable_set( 'lingotek_community_identifier', $community->community );
      variable_set( 'lingotek_oauth_consumer_id', $community->oauth_key );
      variable_set( 'lingotek_oauth_consumer_secret', $community->oauth_secret );
      variable_set( 'lingotek_login_id', $community->external_id ); // Used as the 'External ID'.  For old users, this is their login name / email.
      variable_set( 'lingotek_project', $community->project_id );
      variable_set( 'lingotek_vault', $community->tm_vault_id );
      variable_set( 'lingotek_workflow', $community->workflow_id );
    }

    lingotek_activation( $first_name, $last_name, $email );

  } // END:  Provision new Community

  drupal_set_message( t( 'Your new Lingotek account has been setup.' ) );
  drupal_goto( 'admin/config/lingotek/language-settings' );

} // END:  New Account - Form Processor

/**
 * Account Settings (for Current Users) - Form
 */
function lingotek_setup_account_settings_form() {

  $current_login_id = variable_get( 'lingotek_login_id', '' );
  $current_password = variable_get( 'lingotek_password', '' );

  $form = array();
  $form['lingotek_user_directions_1'] = array( '#markup' => '<p>Login to an existing Lingotek account.</p>' );

  $form['login_id'] = array(
    '#type' => 'textfield',
    '#title' => t('Username'),
    '#default_value' => $current_login_id,
    '#size' => 40,
    '#maxlength' => 128,
    '#required' => true,
  );

  $form['login_password'] = array(
    '#type' => 'password',
    '#title' => t('Password'),
    '#default_value' => $current_password,
    '#size' => 40,
    '#maxlength' => 128,
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form['save_settings'] = array(
    '#type' => 'submit',
    '#value' => t( 'Next' ), // Login
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/new-account' ) .'">Need to create an account?</a></span>' );
  $form['lingotek_support_footer'] = array( '#markup' => lingotek_support_footer() );

  return $form;

} // END:  Account Settings - Form

/**
 * Account Settings (for Current Users) - Form Processing
 */
function lingotek_setup_account_settings_form_submit( $form, $form_state ) {
  $login_id = $form_state[ 'values' ][ 'login_id' ];
  $password = $form_state[ 'values' ][ 'login_password' ];
    //debug( $login_id );
    //debug( $password );

  // Validate the Account - Can we Login?
  // These are V3 API Calls for Legacy Users
  $lingotek_url = variable_get( 'lingotek_url', LingotekApi::LINGOTEK_SERVER_PRODUCTION );
  $communities = lingotek_get_communities_v3( $login_id, $password, $lingotek_url . '/lingopoint/api' );
  if ( $communities === false ) {
    drupal_set_message( t( 'Invalid login or password.' ), 'error' );
  } else {
    // Login Successful:  Valid Account
    drupal_set_message( t( 'Your account settings have been saved.' ) );
    variable_set( 'lingotek_login_id', $login_id );
    variable_set( 'lingotek_password', $password );
    drupal_goto( 'admin/config/lingotek/community-select' );
  } // END:  Valid Login

} // END:  Account Settings - Form Processing

/**
 * Community Select Screen (for Current Users) - Form
 */
function lingotek_community_select_form() {
  $login_id = variable_get( 'lingotek_login_id', '' );
  $password = variable_get( 'lingotek_password', '' );

  $form = array();

  // V3 API Calls for Legacy Users
  $lingotek_url = variable_get( 'lingotek_url', LingotekApi::LINGOTEK_SERVER_PRODUCTION );
  $communities = lingotek_get_communities_v3( $login_id, $password, $lingotek_url . '/lingopoint/api' );
    //debug( $communities );
  if ( $communities === false ) {

    drupal_set_message( t( 'Invalid login or password.' ), 'error' );
    drupal_goto( 'admin/config/lingotek/account-settings' ); // Shouldnt be here.  So something messed up.  Go back.

  } else {

    /*
      debug( $communities );
      array (
        0 => 'YGBHNR3Q',
      )
    */
    $count = count( $communities );
    if( $count == 1 ) { // Just one community, use that.
      $community_id = array_shift( $communities );
      variable_set( 'lingotek_community_identifier', $community_id );
      drupal_goto( 'admin/config/lingotek/project-select' ); // Default path, if they only have 1 community. 
    }
    else if ( $count > 1 ) { // More then 1 community
      // Stay on this page
    }
    else {
      // 0 Results, we have an error.  Or, we should create a community for them.
      drupal_set_message( t( 'Error Accessing Account. Please try again.' ), 'error' );
      drupal_goto( 'admin/config/lingotek/account-settings' ); // Shouldnt be here.  So something messed up.  Go back.
    }
    
  } // END:  Community Select Paths

  $form['lingotek_user_directions_1'] = array( '#markup' => '<p>Your account is associated with multiple Lingotek communities.</p><p>Which community should we associate your Drupal site with?</p>' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['lingotek_site_community'] = array(
    '#title' => t( 'Community' ),
    '#type' => 'select',
    '#options' => $communities,
    //'#default_value' => ,
    //'#description' => t( '' ),
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Next' )
  );
  $previous_page = $_SERVER['HTTP_REFERER'];
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/account-settings' ) .'">Previous Step</a></span>' );
  $form['lingotek_support_footer'] = array( '#markup' => lingotek_support_footer() );

  return $form;

} // END:  Community Select

/**
 * Community Select Screen - Form Submit
 */
function lingotek_community_select_form_submit( $form, $form_state ) {
  $community_identity = $form_state[ 'values' ][ 'lingotek_site_community' ];
  variable_set( 'lingotek_community_identifier', $community_identity );

  drupal_set_message( t( 'Your Lingotek Community Settings have been saved.' ) );
  drupal_goto( 'admin/config/lingotek/project-select' ); // Move to Project Select Step. 

}

/**
 * Project Select Screen (for Current Users) - Form Layout
 */
function lingotek_project_select_form() {
  $login_id = variable_get( 'lingotek_login_id', '' );
  $password = variable_get( 'lingotek_password', '' );

  $form = array();

  // V3 API Call for Legacy Users
  $lingotek_url = variable_get( 'lingotek_url', LingotekApi::LINGOTEK_SERVER_PRODUCTION );
  $projects = lingotek_get_projects_v3( $login_id, $password, $lingotek_url . '/lingopoint/api' );

  if ( $projects === false ) {
    drupal_set_message( t( 'Error Retrieving Account Information.' ), 'error' );
    drupal_goto( 'admin/config/lingotek/account-settings' ); // Error geting projects.  Go back.

  } else {

    $count = count( $projects );
    if ( $count == 1 ) {
      // Just 1 Project, use that.  Then move on to the next step.
      $project_id = array_shift( $projects );
      variable_set( 'lingotek_project', $project_id );
      drupal_goto( 'admin/config/lingotek/language-settings' ); // Default path, if they only have 1 project. 
    } else {
      // Stay on this page.
    }

  }

  $form['lingotek_user_directions_1'] = array( '#markup' => '<p>Your account is associated with multiple Lingotek projects.</p><p>Choose the project where you would like your translated content stored.</p>' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['lingotek_site_project'] = array(
    '#title' => t( 'Project' ),
    '#type' => 'select',
    '#options' => $projects,
    //'#default_value' => ,
    //'#description' => t( '' ),
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Next' )
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/account-settings' ) .'">Previous Step</a></span>' );
  $form['lingotek_support_footer'] = array( '#markup' => lingotek_support_footer() );

  return $form;

} // END:  Project Select

/**
 * Project Select Screen - Form Submit
 */
function lingotek_project_select_form_submit( $form, $form_state ) {
  $project_id = $form_state[ 'values' ][ 'lingotek_site_project' ];
  variable_set( 'lingotek_project', $project_id );

  drupal_set_message( t( 'Your Lingotek project selection has been saved.' ) );
  drupal_goto( 'admin/config/lingotek/language-settings' ); // Move to Source Language Select Step. 
}

/**
 * Language Selection - Form Layout
 */
function lingotek_setup_language_settings_form() {
  $languages = array(
  	'0' => t('Select a Language...'), 
  	'ar' => t('Arabic'), 
  	'zh-hans' => t('Chinese (Simplified)'), 
  	'nl' => t('Dutch'), 
  	'en' => t('English'), 
  	'fr' => t('French'), 
  	'de' => t('German'), 
  	'it' => t('Italian'), 
  	'ja' => t('Japanese'), 
  	'ko' => t('Korean'), 
  	'pt' => t('Portuguese'), 
  	'ru' => t('Russian'), 
  	'es' => t('Spanish')
  );
  $default_language = language_default();
  $source_language = variable_get( 'lingotek_source_language', $default_language->language );
  $target_language = variable_get( 'lingotek_target_language' );

  $form['lingotek_current_language_1'] = array( '#markup' => '<p>To get started, select the source language of your website.</p>' );
  $form['lingotek_current_language_2'] = array( '#markup' => '<p>The current site default language is: ' .$default_language->name .' ( ' .$default_language->language .' )</p>' );
  $form['lingotek_current_language_3'] = array( '#markup' => '<p>In most cases, your source language should be the default language.</p>' );

  $form['lingotek_install_source_language'] = array(
    '#title' => t( 'Source Language' ),
    '#type' => 'select',
    '#options' => $languages,
    '#default_value' => $source_language,
    '#description' => t( 'The current language your site is written in.' ),
    '#required' => true,
  );

  $form['lingotek_install_target_language'] = array(
    '#title' => t( 'Target Language' ),
    '#type' => 'select',
    '#options' => $languages,
    '#default_value' => $target_language,
    '#description' => t( 'The language your site will be translated into.  You can add more languages later.' ),
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Next' )
  );
  $previous_page = $_SERVER['HTTP_REFERER'];
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( $previous_page ) .'">Previous Step</a></span>' );

  $form['lingotek_support_footer'] = array( '#markup' => lingotek_support_footer() );

  return $form;

} // END:  lingotek_install_language_settings_form()


/**
 * Language Selection - Form Validation
 * Requires a source and target language to be selected.
 */
function lingotek_setup_language_settings_form_validate( $form, &$form_state ) {
  if ( $form_state[ 'values' ][ 'lingotek_install_source_language' ] == '0' ) {
    form_set_error( '', t( 'You must select a valid source language.' ) );
  }
  if ( $form_state[ 'values' ][ 'lingotek_install_target_language' ] == '0' ) {
    form_set_error( '', t( 'You must select a valid target language.' ) );
  }
} // END:  lingotek_install_language_settings_form_validate

/**
 * Language Selection - Form Submit Processing
 * Save source and target language selections.
 * Activate a source or target language if it isn't currently active.
 */
function lingotek_setup_language_settings_form_submit( $form, $form_state ) {

  // These are the languages that are currently active in this Drupal installation.
  $active_languages = language_list('enabled');
  $source_language = $form_state[ 'values' ][ 'lingotek_install_source_language' ];
  $target_language = $form_state[ 'values' ][ 'lingotek_install_target_language' ];

  // if the Source language is not active, activate it.
  if ( !array_key_exists( $source_language, $active_languages[1] ) ) {
    locale_add_language( $source_language );  // Function from the Locale module.
    //drupal_set_message( t ('The source language activated.' ) );
  }
  // if the Source language is not active, activate it.
  if ( !array_key_exists( $target_language, $active_languages[1] ) ) {
    locale_add_language( $target_language );  // Function from the Locale module.
    //drupal_set_message( t ('The source language activated.' ) );
  }

  variable_set( 'lingotek_source_language', $form_state[ 'values' ][ 'lingotek_install_source_language' ] );
  variable_set( 'lingotek_target_language', $form_state[ 'values' ][ 'lingotek_install_target_language' ] );

  drupal_set_message( t( 'Your language settings have been saved.' ) );
  drupal_goto( 'admin/config/lingotek/node-translation-settings' );

} // END:  lingotek_install_language_settings_form_submit()

/**
 * Node Translation Settings - Form Layout
 * Select the Content Types and Fields to be Translated.
 */
function lingotek_setup_node_translation_settings_form( $form, $form_state ) {
  $raw_types = node_type_get_types();
  $types = array();
  $translate = variable_get( 'lingotek_translate_fields', array() );
  if ( count( $translate ) > 0 ) { $use_defaults = false; } else { $use_defaults = true; }

  $skip = array( 'taxonomy_autocomplete', 'image_image' );

  $form['lingotek_instructions'] = array( '#markup' => '<div>Which content do you want translated?</div>' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['lingotek_checkboxes_open'] = array( '#markup' => '<div id="content-checkboxes" style="border: 0px solid red;">' );
  // Arrange the Types nicely.
  foreach( $raw_types as $value ) {
    $types[ $value->type ] = t( $value->name );
    $form[ 'type_'.$value->type ] = array(
      '#type' => 'checkbox',
      '#title' => t( $value->name ) .' (<a id="togglelink_' .$value->type .'" href="/admin/config/lingotek/content-type-choose-fields-ajax/' .$value->type .'" class="use-ajax">choose fields</a>)',
      '#return_value' => $value->type,
      '#default_value' => ( array_key_exists( $value->type, $translate ) || ( $use_defaults === true) ) ? true : false,
      '#prefix' => '<div id="content_type_' .$value->type .'">',
      '#suffix' => '</div>' . '',
      '#ajax' => array(
          'event' => 'click',
          'callback' => 'lingotek_content_type_checkbox_callback',
          'wrapper' => 'content_fields_' . $value->type,
      ),
    );

    // Grab the fields for this Content Type.
    $fields = field_info_instances( 'node', $value->type );

    $switch = ( array_key_exists( $value->type, $translate ) ) ? 'block' : 'none';
    $form['lingotek_' .$value->type .'fieldlist_open'] = array( '#markup' => '<div id="content_fields_' .$value->type .'" style="display: ' .$switch .';">' );
    $form['lingotek_fieldlist_details_'.$value->type] = array( '#markup' => '<div style="padding-left: 25px; padding-bottom: 5px;">Which fields would you like to include:</div>' );

    // Loop the fields, outputting them.
    foreach ( $fields as $field ) {
      $field_label = $field[ 'label' ];
      $field_machine_name = $field[ 'field_name' ];
      $field_type = $field[ 'widget' ][ 'type' ];
      $field_name = $value->type . '_SEPERATOR_' . $field_machine_name;
        /*
        $rand = rand();
        $form['lingotek_fieldlist_debug_'.$rand] = array( '#markup' => '<div>' 
          .'Name: ' .$field_name .'<br>' 
          .'Label: ' .$field_label .'<br>' 
          .'Type: ' .$field_type .'<br>' 
          .'</div>' );
        */
      if ( false === array_search( $field_type, $skip ) ) {
        $form[ $field_name ] = array(
          '#type' => 'checkbox',
          '#title' => t( $field_label . ' [' .$field_type .']' ),
          // Set if your machine type is set to translate, and your field has been flagged for translation.   OR!  If use defaults == true, and your field type has text in the name somewhere
          '#default_value' => ( ( isset( $translate[$value->type] ) && false !== array_search( $field_machine_name, $translate[$value->type] ) ) || ( ( $use_defaults === true) && (strpos($field_type, 'text') !== false )  )  ) ? true : false,
          '#prefix' => '<div style="padding-left: 35px;">',
          '#suffix' => '</div>',
        );

      }

    } // END:  foreach field
    $form['lingotek_' .$value->type .'fieldlist_close'] = array( '#markup' => '</div>' );


    $rand = rand();
    $form['lingotek_after_fields_'.$rand] = array( '#markup' => '<div>&nbsp;</div>' );

  } // END:  foreach content type
  $form['lingotek_checkboxes_close'] = array( '#markup' => '</div>' );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Next' )
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/language-settings' ) .'">Previous Step</a></span>' );

  $form['lingotek_support_footer'] = array( '#markup' => lingotek_support_footer() );

  return $form;
} // END:  lingotek_setup_node_translation_settings_form

/**
 * Node Translation Settings - Ajax Callback 1 (Primary)
 */
function lingotek_content_type_choose_fields_callback( $data ) {

  $selector = '#content_fields_' . $data;
  $commands = array();
  $commands[] = ajax_command_invoke( $selector, 'toggle' );
  //$commands[] = ajax_command_alert( 'Hiding: ' .$data  );

  return ajax_deliver ( array('#type' => 'ajax', '#commands' => $commands) );

}

/**
 * Node Translation Settings - Ajax Callback - Checkbox Check/Uncheck
 */
function lingotek_content_type_checkbox_callback( $form, &$form_state ) {

  $triggered_by = $form_state[ 'input' ][ '_triggering_element_name' ];  // IE:  type_article
  $selected_type = substr( $triggered_by, 5 ); // This was the content type that was selected.  IE:  article
  $selector = '#content_fields_' . $selected_type;

  $commands = array();

  // A checkbox was just selected.   If it is checked, its value will be its type name.  If it was unchecked, it will be blank.
  $checkbox_value = $form_state[ 'input' ][ $triggered_by ];
  
  // If the checkbox was un-checked, deselect child checkboxes.
  if ( $checkbox_value == '' ) {
    // NAME: article_SEPERATOR_body
    // removeAttr('checked')
    // 'td[name^=tcol]'

    $checkbox_selector = ':checkbox[name^=' .$selected_type .'_SEPERATOR_]';
    $link_selector = '#togglelink_' . $selected_type;

    $commands[] = ajax_command_invoke( $checkbox_selector, 'removeAttr', array('checked') );
    $commands[] = ajax_command_invoke( $selector, 'hide' );
    $commands[] = ajax_command_invoke( $link_selector, 'text', array( 'choose fields' ) );

  } // END: uncheck
  else { // If the checkbox was selected, activate the default fields.
    //$commands[] = ajax_command_invoke( $selector, 'show' );

    // Grab the fields for this Content Type.
    $fields = field_info_instances( 'node', $selected_type );
    // Loop the fields, outputting them.
    foreach ( $fields as $field ) {
      $field_label = $field[ 'label' ]; //  Label: Body
      $field_machine_name = $field[ 'field_name' ];
      $field_type = $field[ 'widget' ][ 'type' ]; // Type: text_textarea_with_summary
      $field_name = $selected_type . '_SEPERATOR_' . $field_machine_name; // Name: article_SEPERATOR_body

      // If this is a text field, include it by default.
      if ( strpos($field_type, 'text') !== false ) {
        $checkbox_selector = ':checkbox[name=' .$field_name .']';
        $commands[] = ajax_command_invoke( $checkbox_selector, 'attr', array('checked','checked') );
      }
    } // END:  foreach  $fields

  } // END:  checked

  //$commands[] = ajax_command_invoke( $selector, 'toggle' );
  //$commands[] = ajax_command_alert( 'Info: ' . $link_selector );

  return array('#type' => 'ajax', '#commands' => $commands);

}

/**
 * Node Translation Settings - Form Submit
 */
function lingotek_setup_node_translation_settings_form_submit( $form, $form_state ) {

  $translate = array();

  foreach ( $form_state[ 'values' ] as $key => $value ) {
    // Only look at fields for content types.
    if( false !== strpos($key, '_SEPERATOR_') ) {
      // And only if set to translate
      if ( $value != 0 ) {
        $parts = explode( '_SEPERATOR_', $key );
        $content_type = $parts[0];
        $content_field = $parts[1];
        $translate[ $content_type ][] = $content_field;

        // Set this content type to be Lingotek translated.
        variable_set( 'language_content_type_'.$content_type, LINGOTEK_ENABLED );
        // Set this field to 'translatable'.
        db_update('field_config')->fields(array('translatable' => 1))->condition('field_name', $content_field)->execute();
      }
    }
  } // END: foreach form value

  variable_set( 'lingotek_translate_fields', $translate );
  drupal_set_message( t( 'Your content types and fields selections have been saved.' ) );

  drupal_goto( 'admin/config/lingotek/setup-complete' );

} // END:  lingotek_setup_node_translation_settings_form_submit()

/**
 * Setup Complete - Form Layout
 */
function lingotek_setup_complete_form( $form, $form_state ) {

  $form['lingotek_message_1'] = array( '#markup' => '<p>The Lingotek configuration module has been setup and configured and is now ready to use.</p>' );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Start Using Lingotek' )
  );

  $form['lingotek_support_footer'] = array( '#markup' => lingotek_support_footer() );

  return $form;

}

/**
 * Setup Complete - Form Submit - Redirects to Lingotek dashboard.
 */
function lingotek_setup_complete_form_submit( $form, $form_state ) {
  drupal_goto( 'admin/settings/lingotek' );
}

/**
 * Future Page - Form Layout
 */
function lingotek_setup_node_updates_form( $form, $form_state ) {

  $source_language = variable_get( 'lingotek_source_language', '' );

  $form['lingotek_header'] = array( '#markup' => '<h1>Node Updates</h1>' );
  //$form['lingotek_instructions'] = array( '#markup' => '<div>The Lingotek module has be</div>' );
  $form['lingotek_header_line'] = array( '#markup' => '<hr />' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );


  $form['lingotek_message_1a'] = array( '#markup' => '<p>To enable full multilingual functionality on your site we recommend the following:</p>' );
  //$form['lingotek_message_1b'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['title_group'] = array( '#type' => 'fieldset', '#title' => t('Enable Multilingual Node Titles'), '#collapsible' => false, '#collapsed' => false );
  $form['title_group']['lingotek_message_2a'] = array( '#markup' => '<p>Drupal\'s default node titles do not support multiple languages and translations.  For this reason Lingotek uses the Title module.</p>' );
  $form['title_group']['lingotek_message_2b'] = array( '#markup' => '<p>The Title module modifies your content types and adds a new title field, and copies over the current node titles.  The original node titles are left where they are, so you can switch back at any time.</p>' );
  $form['title_group']['lingotek_message_2c'] = array( '#markup' => '<p>The Title module will then respond to requests for node titles.</p>' );
  //$form['title_group']['lingotek_message_2n'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form['title_group']['lingotek_message_2o'] = array( '#markup' => '<p style="padding-top: 10px;"><strong>This change will be made to the following content types:</strong></p>' );
  $form['title_group']['lingotek_message_2q'] = array( '#markup' => '<ul style="padding-left: 0px;">' );
  $form['title_group']['lingotek_message_2r'] = array( '#markup' => '<li>A Node Name</li>' );
  $form['title_group']['lingotek_message_2s'] = array( '#markup' => '</ul>' );
  $form['title_group'][ 'transition_node_titles' ] = array(
    '#type' => 'checkbox',
    '#title' => t( 'Yes, Enable Multilingual Node Titles' ),
    '#prefix' => '<div style="padding-left: 30px; padding-top: 10px;">',
    '#suffix' => '</div>',
  );

  $form['language_group'] = array( '#type' => 'fieldset', '#title' => t('Update Nodes with Undefined Languages'), '#collapsible' => false, '#collapsed' => false );
  $form['language_group']['lingotek_message_3a'] = array( '#markup' => '<p>Currently you have X nodes that do not identify what language their content is in.</p>' );
  $form['language_group']['lingotek_message_3b'] = array( '#markup' => '<p>To properly translate a node it needs to have a language defined.</p>' );
  $form['language_group']['lingotek_message_3c'] = array( '#markup' => '<p>To simplify the process, we can set the language setting for any nodes with an undefined language.</p>' );
  $form['language_group'][ 'update_node_language' ] = array(
    '#type' => 'checkbox',
    '#title' => t( 'Yes, Set Nodes with no Specified Language to ' .$source_language .'.' ),
    '#prefix' => '<div style="padding-left: 30px; padding-top: 10px;">',
    '#suffix' => '</div>',
  );

  $form['lingotek_middle_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );


  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Next' )
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/language-settings' ) .'">Previous Step</a></span>' );

  return $form;

}

/**
 * Future Page - Form Submit
 */
function lingotek_setup_node_updates_form_submit( $form, $form_state ) {

  $transition_node_titles = $form_state[ 'values' ][ 'transition_node_titles' ];
  $update_node_language = $form_state[ 'values' ][ 'update_node_language' ];

  if ( $transition_node_titles == 1 ) {
    dpm( 'Do the Node Titles' );
  }

  if ( $update_node_language == 1 ) {
    dpm( 'Update The Node Languages' );
  }

  //drupal_goto( 'admin/config/lingotek/setup-complete' );
}

/**
 * Outputs the support footer html.
 */
function lingotek_support_footer() {
  $html = '
    <div style="margin-top: 20px; width: 500px;">
      <table class="table table-bordered tab">
        <tbody>
          <tr>
            <td><strong>Support Hours:</strong><br>9am - 6pm MDT</td>
            <td><strong>Phone:</strong><br> (801) 331-7777</td>
            <td><strong>Email:</strong><br> <a href="mailto:support@lingotek.com">support@lingotek.com</a></td>
          </tr>
        </tbody>
      </table>
    </div>
  ';
  return $html;
}

/*
 * Get the Lingotek user's current Communities.  Use the V3 API.
 */
function lingotek_get_communities_v3( $login, $passwd, $url ) {
  global $_lingotek_client;

  $_lingotek_client->login_id = $login;
  $_lingotek_client->password = $passwd;
  $_lingotek_client->url = $url;

  $options = array();

  if (!$_lingotek_client->canLogIn()) {
    return false;
  }

  $list_communities = $_lingotek_client->request("listCommunities", array());
  if ($list_communities->results == "success") {
    foreach ($list_communities->communities as $community) {
      //$options[$community->id] = t($community->name);
      $options[$community->id] = t( $community->id );
    }
  }

  return $options;
}

/*
 * Get the Lingotek user's current Projects.  Use the V3 API.
 */
function lingotek_get_projects_v3( $login, $passwd, $url ) {
  global $_lingotek_client;

  $_lingotek_client->login_id = $login;
  $_lingotek_client->password = $passwd;
  $_lingotek_client->url = $url;

  $options = array();

  if (!$_lingotek_client->canLogIn()) {
    return false;
  }

  /*
    stdClass::__set_state(array(
       'results' => 'success',
       'projects' => 
      array (
        0 => 
        stdClass::__set_state(array(
           'id' => 391,
           'name' => 'D1 Local',
           'state' => 'Active',
           'companyId' => 0,
           'percentComplete' => 0,
           'createdDate' => '2012-11-15',
           'documents' => 
          array (
          ),
        )),
  */

  $list_projects = $_lingotek_client->request("listProjects", array());
  if ($list_projects->results == "success") {
    foreach ($list_projects->projects as $project) {
      $options[$project->id] = t( $project->name );
    }
  }

  return $options;
}

/**
 * Activation
 */
function lingotek_activation( $first_name, $last_name, $email ) {
  $url = 'https://www.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8';
  $fields = array(
    'last_name'=>urlencode( $last_name ),
    'first_name'=>urlencode( $first_name ),
    'email'=>urlencode( $email ),
    'oid' => '00D80000000bjBA',
  );
  $data = '';
  foreach( $fields as $key=>$value ) { $data .= $key.'='.$value.'&'; }
  rtrim($data,'&');
  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_URL, $url );
  curl_setopt( $ch, CURLOPT_POST, count( $fields ) );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
  curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
  $result = curl_exec($ch);
  curl_close($ch);
} // END:  activation

/**
 * Implement hook_mail()
 */
function lingotek_mail ($key, &$message, $params) {

  switch ($key) {
    case 'activation': // Activation Email
      $date = date( 'm/d/Y h:i a' );
      $message['subject'] = t('New Community Provisioned - @date', array( '@date' => $date ));
      $message['body'][] = t('A new community was provisioned on @date.', array( '@date' => $date ));
      $message['body'][] = t('First Name: @firstname', array('@firstname' => $params['first_name']));
      $message['body'][] = t('Last Name: @lastname', array('@lastname' => $params['last_name']));
      $message['body'][] = t('Email: @email', array('@email' => $params['email']));
      $message['body'][] = t('');
      break;
  }

} // END:  mail
