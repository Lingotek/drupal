<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

class LingotekImportSettingsForm extends LingotekConfigFormBase {

  protected $import_as_article_value = 0;

  public function getFormID() {
    return 'lingotek.import_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['import_prefs'] = [
      '#type' => 'details',
      '#title' => t('Preferences'),
    ];

    $contentTypes = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();

    $format_options = [];
    foreach ($contentTypes as $contentType) {
      $format_options[$contentType->id()] = $contentType->label();
    }
    $content_cloud_import_format = $this->lingotek->get('preference.content_cloud_import_format');

    if ($content_cloud_import_format == NULL) {
      $this->lingotek->set('preference.content_cloud_import_format', 'article');
    }

    $form['import_prefs']['content_cloud_import_format'] = [
      '#type' => 'select',
      '#options' => $format_options,
      '#title' => t('Import as:'),
      '#default_value' => $content_cloud_import_format,
    ];

    /**
     * This variable is an array that sets the key => value pairs as 0 => Unpublished
     * and 1 => Published. 0 evaluates to 'unpublished' as a revision status and
     * 1 evaluates to 'published'.
     * @author Unknown
     * @var array
     **/
    $status_options = ['Unpublished', 'Published'];
    $content_cloud_import_status = $this->lingotek->get('preference.content_cloud_import_status', 0);
    if ($content_cloud_import_status == NULL) {
      $this->lingotek->set('preference.content_cloud_import_status', 0);
    }

    $form['import_prefs']['content_cloud_import_status'] = [
      '#type' => 'select',
      '#options' => $status_options,
      '#title' => t('Status:'),
      '#default_value' => $content_cloud_import_status,
    ];

    $form['import_prefs']['actions']['#type'] = 'actions';
    $form['import_prefs']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  protected function saveImportSettings($form_values) {
    $this->lingotek->set('preference.content_cloud_import_format', $form_values['content_cloud_import_format']);
    $this->lingotek->set('preference.content_cloud_import_status', $form_values['content_cloud_import_status']);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $form_values = $form_state->getValues();
    $this->saveImportSettings($form_values);

    drupal_set_message($this->t('Your preferences have been saved'));
  }

}
