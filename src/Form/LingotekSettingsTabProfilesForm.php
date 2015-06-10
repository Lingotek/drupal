<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsProfilesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\lingotek\Lingotek;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabProfilesForm extends LingotekConfigFormBase {
  protected $profiles;
  protected $profile_names;
  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_profiles_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->profiles = $this->L->get('profile');

    // Get the profiles
    $this->retrieveProfileOptions();

    $header = array(
      $this->t('Profile Name'),
      $this->t('Usage'),
      $this->t('Actions'),
    );

    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No Entries'),
    );
    
    foreach ($this->profiles as $profile) {
      $row = array();
      $row['profile_name'] = array(
        '#markup' => $this->t(ucfirst($profile['name'])),
      );
      $count = $this->retrieveUsage($profile);
      $row['usage'] = array(
        '#markup' => $this->t($count . ' content types'),
      );
      $row['profile_actions'] = $this->retrieveActions($profile);
      $table[$profile['name']] = $row;
    }

    $form['config_parent'] = array(
      '#type' => 'details',
      '#title' => $this->t('Translation Profiles'),
      '#collapsible' => FALSE,
    );

    $form['config_parent']['table'] = $table;

    $form['config_parent']['add_profile'] = array(
      '#markup' => \Drupal::l(t('Add New Profile'), new Url('lingotek.dashboard')),
    );
  
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Profiles!');
  }

  protected function retrieveProfileOptions() {
    $this->profile_names = array();

    foreach ($this->profiles as $profile) {
      $this->profile_names[$profile['id']] = ucwords($profile['name']);
    }
  }

  protected function retrieveUsage($profile) {
    $count = 0;
    $content_types = $this->L->get('translate.entity');
    
    // Count how many content types are using this $profile
    foreach($content_types as $type_id => $profile_choice) {
      if ($profile_choice == $profile['id']) {
        $count++;
      }
    }
  
    return $count;
  }

  protected function retrieveActions($profile) {
    $edit_link;

    if ($profile['id'] == Lingotek::PROFILE_DISABLED) {
      $edit_link = '';
    }
    else {
      $edit_link = array(
        '#markup' => \Drupal::l(t('Edit'), new Url('lingotek.dashboard')),
      );
    }

    return $edit_link;
  }

}
