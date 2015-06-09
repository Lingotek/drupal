<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabConfigurationForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabConfigurationForm extends LingotekConfigFormBase {
  protected $profile_options;
  protected $profiles;
  protected $bundles;
  protected $translatable_bundles;
  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_configuration_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No Entries'),
    );

     return $form;
   }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Configuration!');
  }

}
