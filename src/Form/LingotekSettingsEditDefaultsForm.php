<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for editing defaults.
 */
class LingotekSettingsEditDefaultsForm extends LingotekSettingsDefaultsForm {

  /**
   * {@inheritDoc}
   */
  public function init() {
    $this->defaults_labels['community'] = t('Default Community');
    $this->defaults_labels['project'] = t('Default Project');
    $this->defaults_labels['workflow'] = t('Default Workflow');
    $this->defaults_labels['vault'] = t('Default Vault');
    $this->defaults_labels['filter'] = t('Default Filter');
    $this->defaults_labels['subfilter'] = t('Default Subfilter');

    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $this->defaults = $config->get('default');
    $this->resources = $this->lingotek->getResources();
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // When editing, we redirect to the account form, and we don't notify
    // about callback url.
    $config = $this->configFactory()->getEditable('lingotek.settings');
    $form_values = $form_state->getValues();
    foreach ($this->defaults_labels as $key => $label) {
      $config->set('default.' . $key, $form_values[$key]);
    }
    $config->save();

    $form_state->setRedirect('lingotek.settings');
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }

}
