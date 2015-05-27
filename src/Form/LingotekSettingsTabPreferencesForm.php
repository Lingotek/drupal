<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsPreferencesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabPreferencesForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_preferences_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['preferences'] = array(
      '#type' => 'details',
      '#title' => t('Preferences'),
      '#description' => t('These are your preferred settings.'),
      '#group' => 'settings',
    );
    $form['preferences']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save Changes'),
    );

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Prefs!');
  }

}
