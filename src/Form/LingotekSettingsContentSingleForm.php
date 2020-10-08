<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Configure Lingotek
 */
class LingotekSettingsContentSingleForm extends LingotekConfigFormBase {

  protected $profile_options;
  protected $profiles;
  protected $bundles;
  protected $translatable_bundles;

  protected $entity_type_id = NULL;
  protected $bundle_id = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_content_single_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $bundle = NULL) {
    $this->entity_type_id = $entity_type;
    $this->bundle_id = $bundle;

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');

    $entity_type_definitions = \Drupal::entityTypeManager()->getDefinitions();

    // Get the profiles
    $this->retrieveProfileOptions();

    // Retrieve bundles
    $this->retrieveBundles();

    // Retrieve translatable bundles
    $this->retrieveTranslatableBundles();

    $form['parent_details'] = [
      '#type' => 'details',
      '#title' => t('Translate Content Entities'),
      '#open' => TRUE,
    ];

    $form['parent_details']['list']['#type'] = 'container';
    $form['parent_details']['list']['#attributes']['class'][] = 'entity-meta';

    $entity_key = 'entity-' . $entity_type;

    /** @var \Drupal\lingotek\Moderation\LingotekModerationFactoryInterface $moderationFactory */
    $moderationFactory = \Drupal::service('lingotek.moderation_factory');
    /** @var \Drupal\lingotek\Moderation\LingotekModerationSettingsFormInterface $moderationForm */
    $moderationForm = $moderationFactory->getModerationSettingsForm();

    $bundle_label = $entity_type_definitions[$entity_type]->getBundleLabel();
    $header = [
      $this->t('Enable'),
      $bundle_label,
      $this->t('Translation Profile'),
      'moderation' => $moderationForm->getColumnHeader(),
      $this->t('Fields'),
    ];

    if (!$moderationForm->needsColumn($entity_type)) {
      unset($header['moderation']);
    }

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No Entries'),
    ];

    $row = [];
    $row['enabled'] = [
      '#type' => 'checkbox',
      '#label' => $this->t('Enabled'),
      '#default_value' => $lingotek_config->isEnabled($entity_type, $bundle),
      '#ajax' => [
        'callback' => [$this, 'ajaxRefreshEntityFieldsForm'],
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
        'wrapper' => 'container-' . str_replace('_', '-', $entity_type) . '-' . $bundle,
      ],
    ];

    $row['content_type'] = [
      '#markup' => $this->bundles[$entity_type][$bundle]['label'],
    ];

    $row['profiles'] = $this->retrieveProfiles($entity_type, $bundle);

    $moderation = $moderationForm->form($entity_type, $bundle);
    if (!empty($moderation)) {
      $row['moderation'] = $moderation;
    }

    $row['fields_container'] = $this->generateFieldsForm($form_state, $entity_type, $bundle);
    $table[$bundle] = $row;

    $form['parent_details']['list'][$entity_key]['content'][$entity_type] = $table;

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#id' => 'submit-settings-content-single-form',
      '#value' => $this->t('Save Lingotek content settings'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'lingotek/lingotek.settings';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');

    $form_values = $form_state->getValues();

    $contentSettingsData = [];

    $entity_id = $this->entity_type_id;

    foreach ($form_values[$entity_id] as $bundle_id => $bundle) {
      // Only process if we have marked the checkbox.
      if ($bundle['enabled']) {
        if (!$lingotek_config->isEnabled($entity_id, $bundle_id)) {
          $contentSettingsData[$entity_id][$bundle_id]['enabled'] = TRUE;
        }
        foreach ($bundle['fields_container']['fields'] as $field_id => $ignore) {
          $field_choice = isset($bundle['fields'][$field_id]) ? $bundle['fields'][$field_id] : 0;
          if ($field_choice == 1) {
            $contentSettingsData[$entity_id][$bundle_id]['fields'][$field_id] = TRUE;
            if (isset($form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties'])) {
              // We need to add both arrays, as the first one only includes the checked properties.
              $property_values = $form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties'] +
                $form_values[$entity_id][$bundle_id]['fields_container']['fields'][$field_id . ':properties'];
              $contentSettingsData[$entity_id][$bundle_id]['fields'][$field_id . ':properties'] = $property_values;
            }
          }
          elseif ($field_choice == 0) {
            $contentSettingsData[$entity_id][$bundle_id]['fields'][$field_id] = FALSE;
            if (isset($form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties'])) {
              $properties = array_keys($form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties']);
              $properties = array_fill_keys($properties, 0);
              $contentSettingsData[$entity_id][$bundle_id]['fields'][$field_id . ':properties'] = $properties;
            }
          }
        }
        if (isset($form_values[$entity_id][$bundle_id]['profiles'])) {
          $contentSettingsData[$entity_id][$bundle_id]['profile'] = $form_values[$entity_id][$bundle_id]['profiles'];
        }

        /** @var \Drupal\lingotek\Moderation\LingotekModerationFactoryInterface $moderationFactory */
        $moderationFactory = \Drupal::service('lingotek.moderation_factory');
        /** @var \Drupal\lingotek\Moderation\LingotekModerationSettingsFormInterface $moderationForm */
        $moderationForm = $moderationFactory->getModerationSettingsForm();
        $moderationForm->submitHandler($entity_id, $bundle_id, $bundle);
      }
      else {
        // If we removed it, unable it.
        $contentSettingsData[$entity_id][$bundle_id]['enabled'] = FALSE;
      }
    }
    $lingotek_config->setContentTranslationSettings($contentSettingsData);

    $form_state->setRedirect('lingotek.settings');
    parent::submitForm($form, $form_state);
  }

  protected function retrieveProfileOptions() {
    $this->profiles = \Drupal::entityTypeManager()->getListBuilder('lingotek_profile')->load();

    foreach ($this->profiles as $profile) {
      $this->profile_options[$profile->id()] = $profile->label();
    }
  }

  protected function retrieveBundles() {
    $entities = \Drupal::entityTypeManager()->getDefinitions();
    $this->bundles = [];

    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityType && $entity->hasKey('langcode')) {
        $bundle = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity->id());
        $this->bundles[$entity->id()] = $bundle;
      }
    }
  }

  protected function retrieveTranslatableBundles() {
    $this->translatable_bundles = [];

    foreach ($this->bundles as $bundle_group_id => $bundle_group) {
      foreach ($bundle_group as $bundle_id => $bundle) {
        if ($bundle['translatable']) {
          $this->translatable_bundles[$bundle_group_id][$bundle_id] = $bundle;
        }
      }
    }
  }

  protected function retrieveProfiles($entity_id, $bundle_id) {
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $enable_bulk_management = $lingotek_config->getPreference('contrib.paragraphs.enable_bulk_management');

    if (!in_array($entity_id, ['paragraph', 'cohesion_layout']) || $enable_bulk_management) {
      $select = [
        '#type' => 'select',
        '#options' => $lingotek_config->getProfileOptions(),
        '#default_value' => $lingotek_config->getDefaultProfileId($entity_id, $bundle_id),
      ];
    }
    elseif ($entity_id === 'paragraph' || $enable_bulk_management) {
      $select = [
        '#markup' => $this->t("A profile doesn't apply for paragraphs. Not recommended, but you may want to <a href=':link'>translate paragraphs independently</a>.", [':link' => '#edit-contrib-paragraphs-enable-bulk-management']),
      ];
    }
    elseif ($entity_id === 'cohesion_layout') {
      $select = [
        '#markup' => $this->t("A profile doesn't apply for cohesion layouts."),
      ];
    }
    return $select;
  }

  protected function retrieveFields(FormStateInterface $form_state, $entity_id, $bundle_id) {
    $provideDefaults = $form_state->getTemporaryValue('provideDefaults') ?: [];
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_id);
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_id);
    $field_checkboxes = [];

    if ($content_translation_manager->isSupported($entity_id)) {
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_id, $bundle_id);
      // Find which fields the user previously selected
      foreach ($fields as $field_id => $field_definition) {
        $checkbox_choice = 0;

        // We allow non-translatable entity_reference_revisions fields through.
        // See https://www.drupal.org/node/2788285
        if (!empty($storage_definitions[$field_id]) &&
              $storage_definitions[$field_id]->getProvider() != 'content_translation' &&
              !in_array($storage_definitions[$field_id]->getName(), [$entity_type->getKey('langcode'), $entity_type->getKey('default_langcode'), 'revision_translation_affected']) &&
          ($field_definition->isTranslatable() || ($field_definition->getType() == 'cohesion_entity_reference_revisions' || $field_definition->getType() == 'entity_reference_revisions' || $field_definition->getType() == 'path')) && !$field_definition->isComputed() && !$field_definition->isReadOnly()) {

          $checkbox_choice = 0;
          if ($value = $lingotek_config->isFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
            $checkbox_choice = $value;
          }
          if (isset($provideDefaults[$entity_id][$bundle_id]) && $provideDefaults[$entity_id][$bundle_id] && $lingotek_config->shouldFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
            $checkbox_choice = '1';
          }
          $id = 'edit-' . str_replace('_', '-', $entity_id) . '-' . str_replace('_', '-', $bundle_id) . '-fields-' . str_replace('_', '-', $field_id);
          $field_checkbox = [
            '#type' => 'checkbox',
            '#title' => $field_definition->getLabel(),
            '#default_value' => $checkbox_choice,
            '#checked' => $checkbox_choice,
            '#name' => $entity_id . '[' . $bundle_id . '][fields][' . $field_id . ']',
            '#id' => $id,
            '#attributes' => [
              'data-drupal-selector' => $id,
              'id' => $id,
              'name' => $entity_id . '[' . $bundle_id . '][fields][' . $field_id . ']',
            ],
          ];
          $field_checkboxes[$field_id] = $field_checkbox;

          // Display the column translatability configuration widget.
          module_load_include('inc', 'content_translation', 'content_translation.admin');
          $column_element = content_translation_field_sync_widget($field_definition);
          if ($column_element) {
            $default_properties = $lingotek_config->getDefaultFieldPropertiesLingotekEnabled($entity_id, $bundle_id, $field_id);
            $properties_checkbox_choice = $lingotek_config->getFieldPropertiesLingotekEnabled($entity_id, $bundle_id, $field_id);
            if ($provideDefaults && !$properties_checkbox_choice) {
              $properties_checkbox_choice = $default_properties;
            }
            foreach ($column_element['#options'] as $property_id => $property) {
              $checked = FALSE;
              if ($checkbox_choice) {
                $checked = isset($properties_checkbox_choice[$property_id]) ?
                  ($properties_checkbox_choice[$property_id] == '1' || $properties_checkbox_choice[$property_id] === $property_id) : FALSE;
              }
              $id = 'edit-' . str_replace('_', '-', $entity_id) . '-' . str_replace('_', '-', $bundle_id) . '-fields-' . str_replace('_', '-', $field_id) . 'properties-' . str_replace('_', '-', $property_id);
              $property_checkbox = [
                '#type' => 'checkbox',
                '#title' => $property,
                '#default_value' => $checked,
                '#checked' => $checked,
                '#name' => $entity_id . '[' . $bundle_id . '][fields][' . $field_id . ':properties][' . $property_id . ']',
                '#id' => $id,
                '#attributes' => [
                  'data-drupal-selector' => $id,
                  'id' => $id,
                  'name' => $entity_id . '[' . $bundle_id . '][fields][' . $field_id . ':properties][' . $property_id . ']',
                  'class' => ['field-property-checkbox'],
                ],
              ];

              $property_checkbox['#states']['checked'] = [
                [
                ':input[name="' . $field_checkbox['#name'] . '"]' => ['checked' => TRUE],
                ':input[name="' . $property_checkbox['#name'] . '"]' => ['checked' => TRUE],
                ],
              ];
              if ($checked || (isset($default_properties[$property_id]) && $default_properties[$property_id] === $property_id)) {
                $property_checkbox['#states']['unchecked'] = [
                  ':input[name="' . $field_checkbox['#name'] . '"]' => ['unchecked' => TRUE],
                ];
              }
              $field_checkboxes[$field_id . ':properties'][$property_id] = $property_checkbox;
            }
          }
        }
        // We have an exception here, if the entity alias is a computed field we
        // may still want to translate it.
        elseif ($field_definition->getType() == 'path' && $field_definition->isComputed()) {
          if ($value = $lingotek_config->isFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
            $checkbox_choice = $value;
          }
          if (isset($provideDefaults[$entity_id][$bundle_id]) && $provideDefaults[$entity_id][$bundle_id] && $lingotek_config->shouldFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
            $checkbox_choice = '1';
          }
          $id = 'edit-' . str_replace('_', '-', $entity_id) . '-' . str_replace('_', '-', $bundle_id) . '-fields-' . str_replace('_', '-', $field_id);
          $field_checkbox = [
            '#type' => 'checkbox',
            '#title' => $field_definition->getLabel(),
            '#checked' => $checkbox_choice,
            '#default_value' => $checkbox_choice,
            '#name' => $entity_id . '[' . $bundle_id . '][fields][' . $field_id . ']',
            '#id' => $id,
            '#attributes' => [
              'data-drupal-selector' => $id,
            ],
          ];
          $field_checkboxes[$field_id] = $field_checkbox;
        }
      }
    }

    if ($entity_id === 'cohesion_layout') {
      $field_checkboxes['json_values']['#default_value'] = TRUE;
      $field_checkboxes['styles']['#default_value'] = FALSE;
      $field_checkboxes['template']['#default_value'] = FALSE;
      // field_checkboxes['json_values']['#attributes']['disabled'] = 'disabled';
      // $field_checkboxes['styles']['#attributes']['disabled'] = 'disabled';
      // $field_checkboxes['template']['#attributes']['disabled'] = 'disabled';
    }

    return $field_checkboxes;
  }

  public function ajaxRefreshEntityFieldsForm(array $form, FormStateInterface $form_state, Request $request) {
    $triggering_element = $form_state->getTriggeringElement();
    $entity_type_id = $triggering_element['#parents'][0];
    $bundle = $triggering_element['#parents'][1];
    $active = $triggering_element['#value'];

    $provideDefaults = $form_state->getTemporaryValue('provideDefaults') ?: [];
    $provideDefaults[$entity_type_id][$bundle] = $active;

    $form_state->setTemporaryValue('provideDefaults', $provideDefaults);

    // We only need to force the rebuild. No need to do anything else.
    $response = new AjaxResponse();
    // Extra divs will be added. See https://www.drupal.org/node/736066.
    $response->addCommand(new ReplaceCommand('#container-' . str_replace('_', '-', $entity_type_id) . '-' . $bundle, $this->generateFieldsForm($form_state, $entity_type_id, $bundle)));
    return $response;
  }

  /**
   * @param $entity_id
   * @param $bundle_id
   * @param $row
   * @return mixed
   */
  protected function generateFieldsForm(FormStateInterface $form_state, $entity_type_id, $bundle_id) {
    $fields_container = [
      '#type' => 'container',
      '#id' => 'container-' . str_replace('_', '-', $entity_type_id) . '-' . $bundle_id,
      '#attributes' => ['id' => 'container-' . str_replace('_', '-', $entity_type_id) . '-' . $bundle_id],
      'fields' => $this->retrieveFields($form_state, $entity_type_id, $bundle_id),
    ];
    return $fields_container;
  }

}
