<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase; 
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekAccount;
use Drupal\lingotek\Remote\LingotekApi;

class LingotekSettingsController extends LingotekControllerBase {

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    // TODO: alert if no languages are enabled yet.
    // TODO: The LingotekAccount::instance() seems to be broken
    //$account = LingotekAccount::instance();
  
    // TODO: The LingotekApi class didn't have an instance() method
    //$api = LingotekApi::instance();
    //$show_advanced = $account->showAdvanced();
  
    //$form_short_id values:  config, logging, utilities, language_switcher
    $form_id = "lingotek_admin_{$form_short_id}_form";
    if (!is_null($form_short_id) && function_exists($form_id)) {
      return $this->getLingotekForm($form_id);
    }
  
    // $test_form pulls in all existing forms in the Lingotek module so I could see what they looked like
    $test_form = array(
      $this->getLingotekForm('LingotekSettingsAccountForm'),
      $this->getLingotekForm('LingotekSettingsConnectForm'),
      $this->getLingotekForm('LingotekSettingsCommunityForm'),
      $this->getLingotekForm('LingotekSettingsDefaultsForm'),
    );

    $settings_tab = array (
      // All needful forms for the settings tab are pulled in here
      $this->getLingotekForm('LingotekSettingsTabAccountForm'),
      $this->getLingotekForm('LingotekSettingsTabContentForm'),
      $this->getLingotekForm('LingotekSettingsTabConfigurationForm'),
      $this->getLingotekForm('LingotekSettingsTabProfilesForm'),
      //$this->getLingotekForm('LingotekSettingsTabPreferencesForm'),
      $this->getLingotekForm('LingotekSettingsTabLoggingForm'),
      $this->getLingotekForm('LingotekSettingsTabUtilitiesForm'),
      
      // All of the following are different iterations of tables I've tried to get working
      //$this->getLingotekForm('LingotekSettingsTabTestForm'),
      // NegForm and PUlldownForm are patterned after \Drupal\Language\Form\NegotiationConfigureForm
      //$this->getLingotekForm('LingotekSettingsTabTestPulldownForm'),
      //$this->getLingotekForm('LingotekSettingsTabTestNegForm'),
      // MyTableForm is a table from scratch using the Form API
      //$this->getLingotekForm('LingotekSettingsTabTestMyTableForm'),
      // ContentLanguageForm is patterned after \Drupal\language\Form\ContentLanguageSettingsForm.
      //$this->getLingotekForm('LingotekSettingsTabTestContentLanguageForm'),
      //The tab form is an aggregation of all forms into 1 vertical tab form. No buttons worked with this method.
      //$this->getLingotekForm('LingotekSettingsTabForm'), 
      // AdditionalForm is a form where I tried to get the vertical tabs to work.
      //$this->getLingotekForm('LingotekSettingsTabAdditionalForm'),
      // Showing the hierarchy of tables
      //$this->getLingotekForm('LingotekSettingsTabTestNewTableForm'),
    );

    return $settings_tab;
  }

  public function profileForm() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    $profiles_modal = array(
      $this->getLingotekForm('LingotekSettingsTabProfilesEditForm'),
    );

    return $profiles_modal;
  }

}
