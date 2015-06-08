<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsPreferencesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabPreferencesForm extends LingotekConfigFormBase {
  protected $advanced_taxonomy_terms_value = 0;
  protected $show_translate_tabs_value = 0;
  protected $advanced_parsing_value = 0;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_preferences_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->retrieveCheckboxValues();
    
    $form['preferences'] = array(
      '#type' => 'details',
      '#title' => t('Preferences'),
    );

    $form['preferences']['advanced_taxonomy_terms'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable advanced handling of taxonomy terms'),
      '#description' => $this->t('This option is used to handle translation of custom fields assigned to taxonomy terms.'),
      '#default_value' => $this->advanced_taxonomy_terms_value,
    );

    $form['preferences']['always_show_translate_tabs'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Always show non-Lingotek translate tabs'),
      '#description' => $this->t('If checked, edit-form tabs for both Content Translation and Entity Translation will not be hidden, even if the entity is managed by Lingotek.'),
      '#default_value' => $this->show_translate_tabs_value,
    );

    $form['preferences']['advanced_parsing'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable advanced features'),
      '#description' => $this->t('Some features may not be available without an Enterprise license for the Lingotek TMS. Call 801.331.7777 for details.'),
      '#default_value' => $this->advanced_parsing_value,
    );
    
    $form['preferences']['actions']['#type'] = 'actions';
    $form['preferences']['actions']['submit'] = array(
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
    $form_values = $form_state->getValues();
    $this->L->set('preference.advanced_taxonomy_terms', $form_values['advanced_taxonomy_terms']);
    $this->L->set('preference.always_show_translate_tabs', $form_values['always_show_translate_tabs']);
    $this->L->set('preference.advanced_parsing', $form_values['advanced_parsing']);
  }

  protected function retrieveCheckboxValues(){
    // Poll user choices and assign them to the checkboxes
    $choices = $this->L->get('preference');
    if ($choices) {
      if ($choices['advanced_parsing'] == 1) {
        $this->advanced_parsing_value = 1;
      }
      if ($choices['advanced_taxonomy_terms'] == 1) {
        $this->advanced_taxonomy_terms_value = 1;
      }
      if ($choices['always_show_translate_tabs'] == 1) {
        $this->show_translate_tabs_value = 1;
      }
    }
  }

}
