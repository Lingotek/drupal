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
  
    //$form_short_id values:  config, logging, utilities, language_switcher
    $form_id = "lingotek_admin_{$form_short_id}_form";
    if (!is_null($form_short_id) && function_exists($form_id)) {
      return $this->getLingotekForm($form_id);
    }

    $settings_tab = array (
      $this->getLingotekForm('LingotekSettingsTabAccountForm'),
      $this->getLingotekForm('LingotekSettingsTabContentForm'),
      $this->getLingotekForm('LingotekSettingsTabConfigurationForm'),
      $this->getLingotekForm('LingotekSettingsTabProfilesForm'),
      $this->getLingotekForm('LingotekSettingsTabPreferencesForm'),
      $this->getLingotekForm('LingotekSettingsTabLoggingForm'),
      $this->getLingotekForm('LingotekSettingsTabUtilitiesForm'),
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
