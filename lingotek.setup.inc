<?php
/**
 * @file
 * Lingotek Easy Install Setup Process.
 */


/**
 * Step 1 - Choose: New Account or Returning User
 */
function lingotek_setup_choose_path_form() {

  // First, if the setup has already been run, take the user to the Dashboard
  $is_setup_complete = variable_get( 'lingotek_install_complete', 0 );
  if( $is_setup_complete == 1 ) {
    drupal_goto( 'admin/settings/lingotek' );
  }

  $form = array();

  $form['lingotek_header'] = array( '#markup' => '<h1>Lingotek Configuration Wizard</h1>' );
  $form['lingotek_instructions'] = array( '#markup' => '<div>Quick setup and site configuration.</div>' );
  $form['lingotek_header_line'] = array( '#markup' => '<hr />' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['lingotek_user_directions_1a'] = array( '#markup' => '<p>This wizard will guide you though the process of setting the Lingotek module and configuring your website to support multiple languages.</p>' );
  $form['lingotek_user_directions_1b'] = array( '#markup' => '<p>Lingotek provides advanced language translation services.  You will need an account to interact with the Lingotek translation servers.</p>' );

  $form['lingotek_user_directions_2a'] = array( '#markup' => '<h3>New Users</h3>' );
  $form['lingotek_user_directions_2b'] = array( '#markup' => '<p>If you are a new user and this is your first time using Lingotek translation, you will need to <a href="' .url( 'admin/config/lingotek/new-account-setup' ) .'">create an account</a>.</p>' );

  $form['lingotek_user_directions_3a'] = array( '#markup' => '<h3>Current Users</h3>' );
  $form['lingotek_user_directions_3b'] = array( '#markup' => '<p>If you are a returning user, you can <a href="' .url( 'admin/config/lingotek/account-settings' ) .'">enter your account information</a>.</p>' );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  return $form;

} // END:  Step 1


/**
 * Step 2a - New Account - Form
 */
function lingotek_setup_new_account_form() {

  $current_login_id = variable_get( 'lingotek_login_id', '' );
  $current_login_key = variable_get( 'lingotek_login_key', '' );

  $current_first_name = variable_get( 'lingotek_activation_first_name', '' );
  $current_last_name = variable_get( 'lingotek_activation_last_name', '' );
  $current_email = variable_get( 'lingotek_activation_email', '' );

  $form = array();

  $form['lingotek_header'] = array( '#markup' => '<h1>Create a Lingotek Account</h1>' );
  $form['lingotek_instructions'] = array( '#markup' => '<div>New to Lingotek?  Create a free account.</div>' );
  $form['lingotek_header_line'] = array( '#markup' => '<hr />' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['lingotek_user_directions_1'] = array( '#markup' => '<p>A Lingotek account is required to process your language translations.</p>' );

  $form['first_name'] = array(
    '#type' => 'textfield',
    '#title' => t('First Name'),
    '#default_value' => $current_first_name,
    '#size' => 30,
    '#maxlength' => 128,
    '#required' => true,
  );
  $form['last_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Last Name'),
    '#default_value' => $current_last_name,
    '#size' => 30,
    '#maxlength' => 128,
    '#required' => true,
  );
  $form['email'] = array(
    '#type' => 'textfield',
    '#title' => t('Email'),
    '#default_value' => $current_email,
    '#size' => 40,
    '#maxlength' => 128,
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Create Account' )
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/choose-setup-path' ) .'">Previous Step</a></span>' );

  return $form; // Return a form array

} // END:  Step 2a


/**
 * Step 2a - New Account - Form Processor
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


  $current_community_identifier = variable_get( 'lingotek_community_identifier', '' );
  $current_oauth_consumer_id = variable_get( 'lingotek_oauth_consumer_id', '' );
  $current_oauth_consumer_secret = variable_get( 'lingotek_oauth_consumer_secret', '' );

  // Only provision if they aren't setup yet.
  if (
      $current_community_identifier == '' &&
      $current_oauth_consumer_id == '' &&
      $current_oauth_consumer_secret == ''
      ) {

    // Provision a new Community / Install
    $site = variable_get('site_name', 'Drupal Site');
    $api = LingotekApi::instance();
    $community = $api->createCommunity($site);
    $settings = json_decode( $community['body'] ); 
    if ( $settings->results == 'success' ) {
      variable_set( 'lingotek_community_identifier', $settings->community );
      variable_set( 'lingotek_oauth_consumer_id', $settings->oauth_key );
      variable_set( 'lingotek_oauth_consumer_secret', $settings->oauth_secret );
    }
  
    // Send activation email.
    $to = '​cfuller@lingotek.com, sales@lingotek.com';
    $from = 'alerts@lingotek.com';
    $language = language_default();
    drupal_mail('lingotek', 'activation', $to, $language, $form_state['values'], variable_get('site_mail', $from), TRUE );

  }

  drupal_set_message( t( 'Your new Lingotek account has been setup.' ) );
  drupal_goto( 'admin/config/lingotek/language-settings' );

}


/**
 * Implement hook_mail().
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

}


/**
 * Step 2b - Account Settings (for Current Users)
 */
function lingotek_setup_account_settings_form() {

  $current_login_id = variable_get( 'lingotek_login_id', '' );
  $current_password = variable_get( 'lingotek_password', '' );
  //$current_login_key = variable_get( 'lingotek_login_key', '' );

  $form['lingotek_header'] = array( '#markup' => '<h1>Lingotek Account Settings</h1>' );
  $form['lingotek_instructions'] = array( '#markup' => '<div>Enter your Lingotek account information.</div>' );
  $form['lingotek_header_line'] = array( '#markup' => '<hr />' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['lingotek_user_directions_1'] = array( '#markup' => '<p>Link to an existing Lingotek account.</p>' );

  $form['login_id'] = array(
    '#type' => 'textfield',
    '#title' => t('Login ID'),
    '#default_value' => $current_login_id,
    '#size' => 40,
    '#maxlength' => 128,
    '#required' => true,
  );
  $form['login_password'] = array(
    '#type' => 'textfield',
    '#title' => t('Password'),
    '#default_value' => $current_password,
    '#size' => 40,
    '#maxlength' => 128,
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form['save_settings'] = array(
    '#type' => 'submit',
    '#value' => t( 'Save Settings' ),
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/choose-setup-path' ) .'">Previous Step</a></span>' );

  return $form;

} // END:  Step 2b


/**
 * Step 2b - Account Settings (for Current Users) - Form Processing
 */
function lingotek_setup_account_settings_form_submit( $form, $form_state ) {
  $login_id = $form_state[ 'values' ][ 'login_id' ];
  $password = $form_state[ 'values' ][ 'login_password' ];
  //$login_key = $form_state[ 'values' ][ 'login_key' ];

  variable_set( 'lingotek_login_id', $login_id );
  variable_set( 'lingotek_password', $password );
  //variable_set( 'lingotek_login_key', $login_key );

  drupal_set_message( t( 'Your account settings have been saved.' ) );
  drupal_goto( 'admin/config/lingotek/language-settings' );
}


/**
 * Step 3 - Language Selection - Form Layout
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
  $target_language = variable_get( 'lingotek_target_language', 0 );

  $form['lingotek_heading'] = array( '#markup' => '<h1>Choose your Languages</h1>' );
  $form['lingotek_instructions2'] = array( '#markup' => '<div>To get started, select the source and target languages.</div>' );
  $form['lingotek_line'] = array( '#markup' => '<hr />' );

  $form['lingotek_current_language_1'] = array( '#markup' => '<p>The current site default language is: ' .$default_language->name .' ( ' .$default_language->language .' )</p>' );
  $form['lingotek_current_language_2'] = array( '#markup' => '<p>In most cases, your source language should be the default language.</p>' );

  $form['lingotek_install_source_language'] = array(
    '#title' => t( 'Source Language' ),
    '#type' => 'select',
    '#options' => $languages,
    '#default_value' => $source_language,
    '#description' => t( 'The current language your site is written in.' ),
    '#required' => true,
  );

  $form['lingotek_install_target_language'] = array(
    '#title' => t( 'Target Language(s)' ),
    '#type' => 'select',
    '#options' => $languages,
    '#default_value' => $target_language,
    '#description' => t( 'The language you want your content translated into.' ),
    '#required' => true,
  );

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Save Settings' )
  );
  $previous_page = $_SERVER['HTTP_REFERER'];
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( $previous_page ) .'">Previous Step</a></span>' );

  return $form;
} // END:  lingotek_install_language_settings_form()


/**
 * Step 3 - Language Selection - Form Validation
 * Requires a source and target language to be selected.
 */
function lingotek_setup_language_settings_form_validate( $form, &$form_state ) {
  if ( $form_state[ 'values' ][ 'lingotek_install_source_language' ] == '0' ) {
    form_set_error( '', t( 'You must select a valid source language.' ) );
  }
  else if ( $form_state[ 'values' ][ 'lingotek_install_target_language' ] == '0' || $form_state[ 'values' ][ 'lingotek_install_target_language' ] == '' ) {
    form_set_error( '', t( 'You must select a valid target language.' ) );
  }
} // END:  lingotek_install_language_settings_form_validate

/**
 * Step 3 - Language Selection - Form Submit Processing
 * Save source and target language selections.
 * Activate a source or target language if it isn't currently active.
 */
function lingotek_setup_language_settings_form_submit( $form, $form_state ) {
  // These are the languages that are currently active in this Drupal installation.
  $active_languages = language_list('enabled');
  // The Source and Target Languages
  $source_language = $form_state[ 'values' ][ 'lingotek_install_source_language' ];
  $target_language = $form_state[ 'values' ][ 'lingotek_install_target_language' ];

  //dpm( $active_languages );
  //debug( $active_languages );
  //dpm( $source_language );
  //dpm( $target_language );

  // if the Source language is not active, activate it.
  if ( !array_key_exists( $source_language, $active_languages[1] ) ) {
    locale_add_language( $source_language );  // Function from the Locale module.
    drupal_set_message( t ('The source language activated.' ) );
  }

  // if the Target language is not active, activate it.
  if ( !array_key_exists( $target_language, $active_languages[1] ) ) {
    locale_add_language( $target_language );  // Function from the Locale module.
    drupal_set_message( t ('The target language activated.' ) );
  }

  variable_set( 'lingotek_source_language', $form_state[ 'values' ][ 'lingotek_install_source_language' ] );
  variable_set( 'lingotek_target_language', $form_state[ 'values' ][ 'lingotek_install_target_language' ] );

  //drupal_set_message( t( 'Language Settings Saved.' ) );

  //drupal_goto( '/admin/lingotek/' );
  //$form_state['redirect'] = 'admin/config/lingotek/node-translation-settings';

  drupal_set_message( t( 'Your language settings have been saved.' ) );
  drupal_goto( 'admin/config/lingotek/node-translation-settings' );

} // END:  lingotek_install_language_settings_form_submit()










/////  Step 4  ----------------------------------------------------------------------------

/**
 * Step 4 - Node Translation Settings - Form Layout
 * Select the Content Types and Fields to be Translated.
 */
function lingotek_setup_node_translation_settings_form( $form, $form_state ) {
  $raw_types = node_type_get_types();
  $types = array();

  $form['lingotek_header'] = array( '#markup' => '<h1>Content Types to Translate</h1>' );
  $form['lingotek_instructions'] = array( '#markup' => '<div>Which content types do you want translated?</div>' );
  $form['lingotek_header_line'] = array( '#markup' => '<hr />' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  // Arrange the Types nicely.
  foreach( $raw_types as $value ) {
    $types[ $value->type ] = t( $value->name );

    $form[ 'lingotek_content_types' ][ 'type_'.$value->type ] = array(
      '#type' => 'checkbox',
      '#title' => t( $value->name ),
      '#return_value' => $value->type,
      '#prefix' => '<div id="content_type_' .$value->type .'">',
      '#suffix' => '</div>' . '<div id="content_fields_' .$value->type .'">&nbsp;</div>',
      '#ajax' => array(
          'method' => 'append',
          //'method' => 'replace',
          //'event' => 'change',
          'event' => 'click',
          //'effect' => 'fade',
          'progress' => array('type' => 'none'),
          'callback' => 'content_type_pick_callback',
          'wrapper' => 'content_fields_' . $value->type,
      ),
    );
  } // END:  foreach content type

  $form['lingotek_button_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form[ 'submit' ] = array(
  	'#type' => 'submit', 
  	'#value' => t( 'Save Settings' )
  );
  $form['lingotek_back_button'] = array( '#markup' => '<span style="padding-left: 20px;"><a href="' .url( 'admin/config/lingotek/language-settings' ) .'">Previous Step</a></span>' );

  return $form;
} // END:  lingotek_setup_node_translation_settings_form


function content_type_pick_callback( $form, &$form_state ) {

  $items = array();

//$number = rand();
//$items['lingotek_return_anything'] = array( '#markup' => '<div>Just return something.  Anything! ' .$number .'</div>' );


  // Which Element Triggered This?
  $triggered_by = $form_state[ 'input' ][ '_triggering_element_name' ];
  $selected_type = substr( $triggered_by, 5 ); // This was the content type that was selected.

    //$items['lingotek_debug_1'] = array( '#markup' => '<div><pre>' .print_r( $triggered_by, true ) .'</pre></div>' );
    //$items['lingotek_debug_1b'] = array( '#markup' => '<div><pre>' .print_r( $selected_type, true ) .'</pre></div>' );

    //$items['lingotek_debug_2'] = array( '#markup' => '<div><pre>' .print_r( $form_state, true ) .'</pre></div>' );

    //$items['lingotek_debug_2a'] = array( '#markup' => '<div><pre>' .print_r( $form_state[ 'values' ][ $triggered_by ], true ) .'</pre></div>' );
//$items['lingotek_debug_2b'] = array( '#markup' => '<div>Testing For: <pre>' .print_r( $form_state[ 'values' ][ $triggered_by ], true ) .'</pre></div>' );

  // Did we check or uncheck this checkbox?

  // If the triggering element is zero, it is unselected and we clear its fields.
  if ( $form_state[ 'values' ][ $triggered_by ] == '0' ) {


    $items['lingotek_fieldlist_test_debug_info_33'] = array( '#markup' => '<div>Items was unchecked.   La la la</div>' );


  } else { // Show the Fields for this Content Type
    // Grab the fields for this Content Type.
    $fields = field_info_instances( 'node', $selected_type );
    $items['lingotek_fieldlist_details'] = array( '#markup' => '<div style="padding-left: 25px; padding-bottom: 5px;">Which fields would you like to include:</div>' );

    // What types of fields do we NOT want to translate.
    $skip = array( 'taxonomy_autocomplete', 'image_image' );
  
    // Loop the fields, outputting them.
    foreach ( $fields as $field ) {
      //$items['lingotek_debug_'.$cnt] = array( '#markup' => '<div><pre>' .print_r( $field, true ) .'</pre></div>' );
      $field_label = $field[ 'label' ];
      $field_type = $field[ 'widget' ][ 'type' ];

//$rand = rand();
//$items['lingotek_fieldlist_details_' . $rand] = array( '#markup' => 'lingotek_field_for_' . $selected_type . '_' . $field_type );

  
      $items[ 'lingotek_field_for_' . $selected_type . '_' . $field_type ] = array(
        '#type' => 'checkbox',
        '#title' => t( $field_label . ' [' .$field_type .']' ),
        '#prefix' => '<div style="padding-left: 35px;">',
        '#suffix' => '</div>',
      );
    }
  } // END:  show field list

  $items['lingotek_fieldlist_details_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  return $items;

}






function lingotek_setup_node_translation_settings_form_submit( $form, $form_state ) {

  

  //dpm($form_state);
  

  variable_set( 'lingotek_install_complete', 1 );

  drupal_set_message( t( 'Your content types and fields selections have been saved.' ) );
  drupal_goto( 'admin/config/lingotek/setup-complete' );

} // END:  lingotek_setup_node_translation_settings_form_submit


function lingotek_setup_complete_form( $form, $form_state ) {

  $form['lingotek_header'] = array( '#markup' => '<h1>Setup Complete</h1>' );
  //$form['lingotek_instructions'] = array( '#markup' => '<div>The Lingotek module has be</div>' );
  $form['lingotek_header_line'] = array( '#markup' => '<hr />' );
  $form['lingotek_top_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );
  $form['lingotek_message_1'] = array( '#markup' => '<p>The Lingotek configuration module has been setup and configured and is now ready to use.</p>' );
  $form['lingotek_middle_spacer'] = array( '#markup' => '<div>&nbsp;</div>' );

  $form['lingotek_support'] = array( '#markup' => '<h1>Need Help?</h1>' );
  $form['lingotek_support_line'] = array( '#markup' => '<hr />' );
  $form['lingotek_support_1'] = array( '#markup' => '<p>For additional assistance you can contact us via:</p>' );
  $form['lingotek_support_block_open'] = array( '#markup' => '<blockquote>' );
  $form['lingotek_support_hours'] = array( '#markup' => '<p><strong><div style="width: 140px; display: inline-block;">Support Hours:</div></strong>  9am - 6 pm MDT </p>' );
  $form['lingotek_support_phone'] = array( '#markup' => '<p><strong><div style="width: 140px; display: inline-block;">Phone:</div></strong>  (801) 331-7777 opt 3 </p>' );
  $form['lingotek_support_email'] = array( '#markup' => '<p><strong><div style="width: 140px; display: inline-block;">Email:</div></strong> ​ <a href="mailto:support@lingotek.com?Subject=Support%20Inquiry">support@lingotek.com</a> </p>' );
  $form['lingotek_support_block_close'] = array( '#markup' => '</blockquote>' );

  return $form;

}
