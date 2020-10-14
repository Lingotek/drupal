<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure text display settings for this page.
 */
class LingotekSettingsDefaultsForm extends LingotekConfigFormBase {
  protected $defaults_labels = [];
  protected $defaults;
  protected $resources;

  public function init() {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');

    $this->defaults = $config->get('default');
    $this->resources = $this->lingotek->getResources();

    // Make visible only those options that have more than one choice
    if (count($this->resources['project']) > 1) {
      $this->defaults_labels['project'] = t('Default Project');
    }
    elseif (count($this->resources['project']) == 1) {
      $config->set('default.project', current(array_keys($this->resources['project'])));
    }

    if (count($this->resources['vault']) > 1) {
      $this->defaults_labels['vault'] = t('Default Vault');
    }
    elseif (count($this->resources['vault']) == 1) {
      $config->set('default.vault', current(array_keys($this->resources['vault'])));
    }

    if (count($this->resources['workflow']) > 1) {
      $this->defaults_labels['workflow'] = t('Default Workflow');
    }
    elseif (count($this->resources['workflow']) == 1) {
      $config->set('default.workflow', current(array_keys($this->resources['workflow'])));
    }

    if (count($this->resources['filter']) > 1) {
      $this->defaults_labels['filter'] = t('Default Filter');
      $this->defaults_labels['subfilter'] = t('Default Subfilter');
    }
    else {
      $config->set('default.filter', 'drupal_default');
      $config->set('default.subfilter', 'drupal_default');
    }

    // Set workflow to machine translation every time regardless if there's more than one choice
    $machine_translation = array_search('Machine Translation', $this->resources['workflow']);
    if ($machine_translation) {
      $config->set('default.workflow', $machine_translation);
    }
    $config->save();
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

    foreach ($this->defaults_labels as $key => $label) {
      $resources_key = ($key === 'subfilter') ? 'filter' : $key;
      asort($this->resources[$resources_key]);
      switch ($key) {
        case 'filter':
        case 'subfilter':
          $options = [
            'project_default' => $this->t('Use Project Default'),
            'drupal_default' => $this->t('Use Drupal Default'),
          ] + $this->resources[$resources_key];
          break;

        case 'workflow':
          $options = [
            'project_default' => $this->t('Project Default'),
          ] + $this->resources[$resources_key];
          break;

        default:
          $options = $this->resources[$key];
          break;
      }
      $form[$key] = [
        '#title' => $label,
        '#type' => 'select',
        '#options' => $options,
        '#required' => TRUE,
      ];
      if (isset($this->defaults[$key])) {
        $form[$key]['#default_value'] = $this->defaults[$key];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('lingotek.settings');
    $form_values = $form_state->getValues();
    foreach ($this->defaults_labels as $key => $label) {
      $config->set('default.' . $key, $form_values[$key]);
    }
    $config->save();

    // Since the lingotek module is newly installed, assign the callback
    $this->setCallbackUrl($config);

    $form_state->setRedirect('lingotek.dashboard');
    parent::submitForm($form, $form_state);
  }

  /**
   * @param $config
   */
  protected function setCallbackUrl($config) {
    $new_callback_url = \Drupal::urlGenerator()
      ->generateFromRoute('lingotek.notify', [], ['absolute' => TRUE]);
    $config->set('account.callback_url', $new_callback_url);
    $new_response = $this->lingotek->setProjectCallBackUrl($config->get('default.project'), $new_callback_url);
    $config->save();
  }

}
