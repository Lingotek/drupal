<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabLoggingForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_logging_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['log'] = [
      '#type' => 'details',
      '#title' => t('Logging'),
      '#description' => t('Help troubleshoot any issues with the module. The logging enabled below will be available in the') . ' ' . $this->linkGenerator->generate(t('Drupal watchdog.'), Url::fromUri('internal:/admin/reports/dblog')),
    ];

    $form['log']['error_logging'] = [
      '#type' => 'checkbox',
      '#title' => t('Error Logging'),
      '#description' => t("This prints errors and warnings to the web server's error logs in addition to adding them to watchdog."),
      '#default_value' => $this->config('lingotek.settings')->get('logging.lingotek_error_log'),
    ];

    $form['log']['warning_logging'] = [
      '#type' => 'checkbox',
      '#title' => t('Warning Logging'),
      '#description' => t("This logs any warnings in watchdog and the web server's error logs."),
      '#default_value' => $this->config('lingotek.settings')->get('logging.lingotek_warning_log'),
    ];

    $form['log']['interaction_logging'] = [
      '#type' => 'checkbox',
      '#title' => t('API & Interaction Logging'),
      '#description' => t('Logs the timing and request/response details of all Lingotek API calls. Additionally, interaction calls (e.g., endpoint, notifications) made back to Drupal will be logged with this enabled.'),
      '#default_value' => $this->config('lingotek.settings')->get('logging.lingotek_api_debug'),
    ];

    $form['log']['trace_logging'] = [
      '#type' => 'checkbox',
      '#title' => t('Trace Logging'),
      '#description' => t("This logs trace debug messages to watchdog and the web server's error logs. (This logging is extremely verbose.)"),
      '#default_value' => $this->config('lingotek.settings')->get('logging.lingotek_trace_log'),
    ];

    $form['log']['never_cache'] = [
      '#type' => 'checkbox',
      '#title' => t('Never Cache'),
      '#description' => t('Skips caching so you can test easier. This avoids frequent polling of fresh data from Lingotek. Only those with Developer permissions will have caching disabled.'),
      '#default_value' => $this->config('lingotek.settings')->get('logging.lingotek_flush_cache'),
    ];

    $form['log']['actions']['#type'] = 'actions';
    $form['log']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('logging.lingotek_error_log', $form_values['error_logging']);
    $config->set('logging.lingotek_warning_log', $form_values['warning_logging']);
    $config->set('logging.lingotek_api_debug', $form_values['interaction_logging']);
    $config->set('logging.lingotek_trace_log', $form_values['trace_logging']);
    $config->set('logging.lingotek_flush_cache', $form_values['never_cache']);
    $config->save();
  }

}
