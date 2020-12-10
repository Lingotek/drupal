<?php

namespace Drupal\lingotek_test_config_object\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Lingotek Translation Test Config Object Helper settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_test_config_object_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['lingotek_test_config_object.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['property_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property 1'),
      '#default_value' => $this->config('lingotek_test_config_object.settings')->get('property_1'),
    ];
    $form['property_2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property 2'),
      '#default_value' => $this->config('lingotek_test_config_object.settings')->get('property_2'),
    ];

    $form['property_3'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property 3'),
      '#default_value' => $this->config('lingotek_test_config_object.settings')->get('property_3'),
    ];
    $form['property_4'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property 4'),
      '#default_value' => $this->config('lingotek_test_config_object.settings')->get('property_4'),
    ];
    $form['property_5'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property 5'),
      '#default_value' => $this->config('lingotek_test_config_object.settings')->get('property_5'),
    ];
    $form['property_6'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property 6'),
      '#default_value' => $this->config('lingotek_test_config_object.settings')->get('property_6'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('lingotek_test_config_object.settings');
    foreach (range(1, 6) as $propertyIndex) {
      $config->set('property_' . $propertyIndex, $form_state->getValue('property_' . $propertyIndex));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
