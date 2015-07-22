<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsProfilesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\LingotekLog;
use Drupal\lingotek\LingotekSync;
use Drupal\lingotek\LingotekTranslatableEntity;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabProfilesForm extends LingotekConfigFormBase {
  protected $profiles;
  protected $profile_names;
  protected $profile_index;
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
    $this->profile_index = 0;

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
        '#markup' => $this->t(ucwords($profile['name'])),
      );
      $usage = $this->retrieveUsage($profile);
      $row['usage'] = array(
        '#markup' => $this->t($usage . ' content types'),
      );
      $row['profile_actions'] = $this->retrieveActions($profile, $usage);
      $table[$profile['name']] = $row;

      $this->profile_index++;
    }

    $form['config_parent'] = array(
      '#type' => 'details',
      '#title' => $this->t('Translation Profiles'),
    );

    $form['config_parent']['table'] = $table;
    $form['config_parent']['add_profile'] = $this->retrieveActions();

    return $form;
  }

  protected function retrieveUsage($profile) {
    $usage = 0;
    $entity_types = $this->L->get('translate.entity');
    
    // Count how many content types are using this $profile
    foreach($entity_types as $entity_id => $bundles) {
      foreach($bundles as $bundle) {
        $profile_choice = $bundle['profile'];

        if ($profile_choice == $profile['id']) {
          $usage++;
        }
      }
    }
    
    return $usage;
  }

  protected function retrieveActions($profile = NULL, $usage = NULL) {
    // Assign $url and $title depending on if it's a new profile or not
    if ($profile) {
      $title = t('Edit');
      $url = Url::fromRoute('lingotek.settings_profile', [
        'profile_choice' => $profile, 
        'profile_index' => $this->profile_index,
        'profile_usage' => $usage
      ]);
    }
    else {
      $title = t('Add New Profile');
      $url = Url::fromRoute('lingotek.settings_profile');
    }

    // If it's a disabled profile, no link is provided
    if ($profile['id'] == Lingotek::PROFILE_DISABLED) {
      $edit_link = array(
        '#markup' => $this->t('Not Editable'),
      );
    }
    else {
      $edit_link = array(
        '#type' => 'link',
        '#title' => $title,
        '#url' => $url,
        '#ajax' => array(
          'class' => array('use-ajax'),
        ),
        '#attributes' => array(
          'class' => array('use-ajax'),
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(array(
            'width' => 861,
            'height' => 700,
          )),
        ),
      );
    }

    return $edit_link;
  }

}
