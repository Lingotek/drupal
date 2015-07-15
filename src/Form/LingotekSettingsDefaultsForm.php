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
  protected $defaults;
  protected $resources;

  public function init(){
    $this->defaults = $this->L->getDefaults();
    $this->resources = $this->L->getResources();
    $this->defaults_labels = array();

    // Make visible only those options that have more than one choice
    if (count($this->resources['project']) > 1) {
      $this->defaults_labels['project'] = t('Default Project');
    } 
    elseif (count($this->resources['project']) == 1) {
      $this->L->set('default.project', current(array_keys($this->resources['project'])));
    }

    if (count($this->resources['vault']) > 1) {
      $this->defaults_labels['vault'] = t('Default Vault');
    }
    elseif (count($this->resources['vault']) == 1) {
      $this->L->set('default.vault', current(array_keys($this->resources['vault'])));
    }

    //Set workflow to machine translation every time regardless if there's more than one choice
    $this->L->set('default.workflow', array_search('Machine Translation', $this->resources['workflow']));
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

    foreach($this->defaults_labels as $key => $label){ 
      asort($this->resources[$key]);
      $form[$key] = array(
        '#title' => $label,
        '#type' => 'select',
        '#options' => $this->resources[$key],
        '#default_value' => $this->defaults[$key],
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
      $this->L->set('default.'. $key, $form_values[$key]);
    }

    $this->checkCallBackUrl();
    $form_state->setRedirect('lingotek.dashboard');
    parent::submitForm($form, $form_state);
  }

  protected function checkCallBackUrl() {
    $project_id = $this->L->get('default.project');
    $response = $this->L->getProject($project_id);
    $callback_url = $response['properties']['callback_url'];

    // Assign a callback_url if the user's project doesn't have one
    if ($callback_url) {
      $this->L->set('account.callback_url', $callback_url);
    }
    elseif (!$callback_url) {
      $new_callback_url = \Drupal::urlGenerator()->generate('<none>', [], ['absolute' => TRUE]) . 'lingotek/notify';
      $this->L->set('account.callback_url', $new_callback_url);
      $response['properties']['callback_url'] = $new_callback_url;
      $new_response = $this->L->setProjectCallBackUrl($project_id, $new_callback_url);
    }
  }

}
