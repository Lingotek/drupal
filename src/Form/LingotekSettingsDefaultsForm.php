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

    foreach($this->defaults_labels as $key => $label){
      // If there's more than one choice, query the user (for workflow, just pick machine translation)
      if (count($resources[$key]) > 1 && $key != 'workflow') { 
        asort($resources[$key]);
        $form[$key] = array(
          '#title' => $label,
          '#type' => 'select',
          '#options' => $resources[$key],
          '#default_value' => $defaults[$key],
          '#required' => TRUE,
        );
      }
      else {
        if ($key === 'workflow') {
          $value = array_search('Machine Translation', $resources[$key]);
        }
        else {
          $value = current(array_keys($resources[$key]));
        }
        $this->L->set('default.' . $key, $value);
      }
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

    $this->checkCallBackUrl();
    $form_state->setRedirect('lingotek.dashboard');
    parent::submitForm($form, $form_state);
  }

  protected function checkCallBackUrl() {
    $project_id = $this->L->get('default.project');
    $response = $this->L->getProject($project_id);
    $callback_url = $response['properties']['callback_url'];

    // Assign a callback_url if the user's project doesn't have one
    if (!$callback_url) {
      $new_callback_url = \Drupal::urlGenerator()->generate('<none>', [], ['absolute' => TRUE]) . 'lingotek';
      $this->L->set('account.callback_url', $new_callback_url);
      $response['properties']['callback_url'] = $new_callback_url;
      $new_response = $this->L->setProjectCallBackUrl($project_id, $new_callback_url);
    }
  }
}
