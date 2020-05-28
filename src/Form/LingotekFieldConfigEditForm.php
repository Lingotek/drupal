<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lingotek\LingotekConfigurationServiceInterface;

/**
 * Allow to configure fields translation with Lingotek in the proper field forms
 *
 * @package Drupal\lingotek\Form
 */
class LingotekFieldConfigEditForm {

  use StringTranslationTrait;

  use MessengerTrait;

  /**
   * The Lingotek Configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfig;

  /**
   * Constructs a new LingotekConfigurationService object.
   *
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config
   *   The Lingotek Configuration service.
   */
  public function __construct(LingotekConfigurationServiceInterface $lingotek_config) {
    $this->lingotekConfig = $lingotek_config;
  }

  /**
   * Adds "Use Lingotek to translate this field" to each field
   * for fields with properties, "Use Lingotek to translate the ___ element" is added
   *
   * @param array $form
   *   The form definition array for the language content settings.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function form(array &$form, FormStateInterface $form_state) {
    $field = $form_state->getFormObject()->getEntity();
    $entity_id = $field->getTargetEntityTypeId();
    $bundle_id = $field->getTargetBundle();
    $field_id = $field->getName();

    // Add the option to translate the field with Lingotek
    if (!$form['translatable']['#disabled']) {
      $form['translatable_for_lingotek'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use Lingotek to translate this field'),
        '#default_value' => $this->lingotekConfig->isFieldLingotekEnabled($entity_id, $bundle_id, $field_id),
        '#weight' => -1,
        '#states' => [
          'visible' => [
            ':input[name="translatable"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['actions']['submit']['#submit'][] = [$this, 'submitForm'];
    }

    if (isset($form['third_party_settings']['content_translation'])) {
      if (isset($form['third_party_settings']['content_translation']['translation_sync'])) {
        $content_translation_options = $form['third_party_settings']['content_translation']['translation_sync']['#options'];
        $properties_checkbox_choice = $this->lingotekConfig->getFieldPropertiesLingotekEnabled($entity_id, $bundle_id, $field_id);
        $form['translatable_for_lingotek_properties'] = [
          '#type' => 'item',
          '#title' => $this->t('Lingotek Translation'),
          '#weight' => 15,
          '#states' => [
            'visible' => [
              ':input[name="translatable_for_lingotek"]' => ['checked' => TRUE],
            ],
          ],
        ];
        foreach ($content_translation_options as $content_translation_option_key => $content_translation_option) {
          $form['translatable_for_lingotek_properties_' . $content_translation_option_key] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Use Lingotek to translate the %content_translation_option element', ['%content_translation_option' => $content_translation_option]),
            '#default_value' => $properties_checkbox_choice ? $properties_checkbox_choice[$content_translation_option_key] : FALSE,
            '#weight' => 15,
            '#states' => [
              'visible' => [
                ':input[name="translatable_for_lingotek"]' => ['checked' => TRUE],
              ],
            ],
          ];
          if ($properties_checkbox_choice && $properties_checkbox_choice[$content_translation_option_key]) {
            $form['translatable_for_lingotek_properties_' . $content_translation_option_key]['#default_value'] = 1;
          }
        }
        if (!$properties_checkbox_choice) {
          $properties_checkbox_choice = [];
        }
        $field->setThirdPartySetting('lingotek', 'translation_sync', $properties_checkbox_choice);
      }
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array $form, FormStateInterface $form_state) {
    $field = $form_state->getFormObject()->getEntity();
    $entity_id = $field->getTargetEntityTypeId();
    $bundle_id = $field->getTargetBundle();
    $field_id = $field->getName();

    $value = $form_state->getValue(['third_party_settings', 'content_translation', 'translation_sync']);
    if (isset($value)) {
      $content_translation_field_properties = $form_state->getValue('third_party_settings')['content_translation']['translation_sync'];
      $filtered_field_properties = [];
      foreach ($content_translation_field_properties as $content_translation_field_property_key => $content_translation_field_property) {
        if ($content_translation_field_property && $form_state->getValue('translatable_for_lingotek_properties_' . $content_translation_field_property_key)) {
          $filtered_field_properties[$content_translation_field_property_key] = $content_translation_field_property;
        }
        else {
          $filtered_field_properties[$content_translation_field_property_key] = 0;
          if ($form_state->getValue('translatable_for_lingotek_properties_' . $content_translation_field_property_key)) {
            $this->messenger()->addWarning(t('To translate the image properties with Lingotek, you must enable them for translation first.'));
          }
        }
      }
      $this->lingotekConfig->setFieldPropertiesLingotekEnabled($entity_id, $bundle_id, $field_id, $filtered_field_properties);
    }

    if ($form_state->getValue('translatable_for_lingotek') && $form_state->getValue('translatable')) {
      if (!$this->lingotekConfig->isFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
        $this->lingotekConfig->setFieldLingotekEnabled($entity_id, $bundle_id, $field_id);
      }
    }
    else {
      if ($this->lingotekConfig->isFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
        $this->lingotekConfig->setFieldLingotekEnabled($entity_id, $bundle_id, $field_id, FALSE);
      }
    }
  }

}
