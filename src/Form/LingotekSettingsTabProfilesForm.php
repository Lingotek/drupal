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

/**
 * Configure Lingotek
 */
class LingotekSettingsTabProfilesForm extends LingotekConfigFormBase {

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
    
    $form['profiles'] = array(
      '#type' => 'details',
      '#title' => t('Translation Profiles'),
      '#description' => t('Translation profiles allow you to quickly configure and re-use translation settings.'),
      '#group' => 'settings',
    );
    $form['profiles']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Add New Profile'),
    );

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Profiles!');
  }

}
