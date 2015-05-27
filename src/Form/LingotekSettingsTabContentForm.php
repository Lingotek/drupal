<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabContentForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabContentForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_content_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $login = $this->L->get('profile');
    $form['content'] = array(
      '#type' => 'details',
      '#title' => t('Translate Content Types'),
      '#description' => t('Help troubleshoot any issues with the module. The logging enabled below will be available in the Drupal watchdog.'),
      '#open' => FALSE,
      '#group' => 'settings',
    );
    $form['content']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
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
