<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsVerticalTabForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsVerticalTabForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_vertical_tab_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['settings'] = array(
      '#type' => 'vertical_tabs',
    );

    $form['settings']['account'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabAccountForm');
    $form['settings']['nodes'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabNodesForm');
    $form['settings']['comments'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabCommentsForm');
    $form['settings']['config'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabConfigurationForm');
    $form['settings']['profiles'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabProfilesForm');
    $form['settings']['prefs'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabPreferencesForm');
    $form['settings']['log'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabLoggingForm');
    $form['settings']['util'] = \Drupal::formBuilder()->getForm('Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm');

     return $form;
   }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Dude!');
  }

  public function test(array &$form, FormStateInterface $form_state) {
    dpm('Comments aggro');
  }

}
