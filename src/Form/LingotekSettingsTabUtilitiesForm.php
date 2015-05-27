<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsUtilitiesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabUtilitiesForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_utilities_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['utilities'] = array(
      '#type' => 'details',
      '#title' => t('Utilities'),
      '#description' => t('These utilities are designed to help you prepare and maintain your multilingual content.'),
      '#group' => 'settings',
    );
    $form['utilities']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save Changes'),
    );

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Utilities!');
  }

}
