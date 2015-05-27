<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabAdditionalForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_additional_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    //Translate Nodes
    $form['add'] = array(
      '#type' => 'details',
      '#title' => t('Additional man!'),
      '#description' => t('Hopefully this works'),
      '#weight' => 1,
      '#group' => 'settings',
    );
    $form['add']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save Changes'),
    );
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Additional!');
  }

}
