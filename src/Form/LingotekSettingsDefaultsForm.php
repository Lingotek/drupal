<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsProjectVaultForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure text display settings for this page.
 */
class LingotekSettingsDefaultsForm extends LingotekConfigFormBase {

  protected $defaults_labels;

  public function init(){
    $this->defaults_labels = array(
      'project' => $this->t('Default Project'),
      'workflow' => $this->t('Default Workflow'),
      'vault' => $this->t('Default Vault')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_defaults';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->init();

    $defaults = $this->L->getDefaults();
    $resources = $this->L->getResources();
    //dpm("defaults:"); dpm($defaults);
    //dpm("resources:"); dpm($resources);

    foreach($this->defaults_labels as $key => $label){
      $form[$key] = array(
        '#title' => $label,
        '#type' => 'select',
        '#options' => $resources[$key],
        '#default_value' => $defaults[$key],
        '#required' => TRUE,
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    foreach($this->defaults_labels as $key => $label){
      $this->L->set('default.'.$key, $form_values[$key]);
    }
    $form_state->setRedirect('lingotek.dashboard');
    parent::submitForm($form, $form_state);
  }
}