<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsLoggingForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabLoggingForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_logging_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $login = $this->L->get('profile');
    $form['log'] = array(
      '#type' => 'details',
      '#title' => t('Logging'),
      '#description' => t('Help troubleshoot any issues with the module. The logging enabled below will be available in the Drupal watchdog.'),
      '#open' => FALSE,
      '#group' => 'settings',
    );
    $form['log']['actions']['#type'] = 'actions';
    $form['log']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Logging!');
  }

}
