<?php

namespace Drupal\lingotek\Form;

use Drupal\block\Entity\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityType;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabContentForm extends LingotekConfigFormBase {
  protected $profile_options;
  protected $profiles;
  protected $bundles;
  protected $translatable_bundles;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');

    $entity_type_definitions = \Drupal::entityManager()->getDefinitions();

    // Get the profiles
    $this->retrieveProfileOptions();

    // Retrieve bundles
    $this->retrieveBundles();

    // Retrieve translatable bundles
    $this->retrieveTranslatableBundles();

    $form['parent_details'] = [
      '#type' => 'details',
      '#title' => t('Translate Content Entities'),
    ];

    $form['parent_details']['list']['#type'] = 'container';
    $form['parent_details']['list']['#attributes']['class'][] = 'entity-meta';

    // If user specifies no translatable entities, post this message
    if (empty($this->translatable_bundles)) {
      $form['parent_details']['empty_message'] = [
        '#markup' => t('There are no translatable content entities specified. You can enable translation for the desired content entities on the <a href=":translation-entity">Content language</a> page.',
          [':translation-entity' => \Drupal::url('language.content_settings_page')]),
      ];
    }

    // I. Loop through all entities and create a details container for each
    foreach ($this->translatable_bundles as $entity_id => $bundles) {
      $entity_key = 'entity-' . $entity_id;
      $form['parent_details']['list'][$entity_key] = [
        '#type' => 'details',
        '#title' => $entity_type_definitions[$entity_id]->getLabel(),
        'content' => [],
      ];

      /** @var \Drupal\lingotek\Moderation\LingotekModerationFactoryInterface $moderationFactory */
      $moderationFactory = \Drupal::service('lingotek.moderation_factory');
      /** @var \Drupal\lingotek\Moderation\LingotekModerationSettingsFormInterface $moderationForm */
      $moderationForm = $moderationFactory->getModerationSettingsForm();

      $bundle_label = $entity_type_definitions[$entity_id]->getBundleLabel();
      $header = [
        $this->t('Enable'),
        $bundle_label,
        $this->t('Translation Profile'),
        'moderation' => $moderationForm->getColumnHeader(),
        $this->t('Fields'),
      ];

      if (!$moderationForm->needsColumn($entity_id)) {
        unset($header['moderation']);
      }

      $table = [
        '#type' => 'table',
        '#header' => $header,
        '#empty' => $this->t('No Entries'),
      ];

      // II. Loop through bundles per entity and make a table
      foreach ($bundles as $bundle_id => $bundle) {
        $row = [];
        $row['enabled'] = [
          '#type' => 'checkbox',
          '#label' => $this->t('Enabled'),
          '#default_value' => $lingotek_config->isEnabled($entity_id, $bundle_id),
        ];
        $row['content_type'] = [
          '#markup' => $bundle['label'],
        ];
        $row['profiles'] = $this->retrieveProfiles($entity_id, $bundle_id);

        $moderation = $moderationForm->form($entity_id, $bundle_id);
        if (!empty($moderation)) {
          $row['moderation'] = $moderation;
        }

        $row['fields'] = $this->retrieveFields($entity_id, $bundle_id);
        $table[$bundle_id] = $row;
      }

      // III. Add table to respective details
      $form['parent_details']['list'][$entity_key]['content'][$entity_id] = $table;
    }

    if (!empty($this->translatable_bundles)) {
      $form['parent_details']['note'] = [
        '#markup' => t('Note: changing the profile will update all settings for existing nodes except for the project, workflow, vault, and storage method (e.g. node/field)'),
      ];

      $form['parent_details']['actions']['#type'] = 'actions';
      $form['parent_details']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      ];
    }

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

    // For every content type, save the profile and fields in the Lingotek object
    foreach ($this->translatable_bundles as $entity_id => $bundles) {
      foreach ($form_values[$entity_id] as $bundle_id => $bundle) {
        // Only process if we have marked the checkbox.
        if ($bundle['enabled']) {
          if (!$lingotek_config->isEnabled($entity_id, $bundle_id)) {
            $lingotek_config->setEnabled($entity_id, $bundle_id);
          }
          foreach ($bundle['fields'] as $field_id => $field_choice) {
            if ($field_choice == 1) {
              $lingotek_config->setFieldLingotekEnabled($entity_id, $bundle_id, $field_id);
              if (isset($form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties'])) {
                $lingotek_config->setFieldPropertiesLingotekEnabled($entity_id, $bundle_id, $field_id, $form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties']);
              }
            }
            elseif ($field_choice == 0) {
              $lingotek_config->setFieldLingotekEnabled($entity_id, $bundle_id, $field_id, FALSE);
              if (isset($form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties'])) {
                $properties = array_keys($form_values[$entity_id][$bundle_id]['fields'][$field_id . ':properties']);
                $properties = array_fill_keys($properties, 0);
                $lingotek_config->setFieldPropertiesLingotekEnabled($entity_id, $bundle_id, $field_id, $properties);
              }
            }
          }
          if (isset($form_values[$entity_id][$bundle_id]['profiles'])) {
            $lingotek_config->setDefaultProfileId($entity_id, $bundle_id, $form_values[$entity_id][$bundle_id]['profiles']);
          }

          /** @var \Drupal\lingotek\Moderation\LingotekModerationFactoryInterface $moderationFactory */
          $moderationFactory = \Drupal::service('lingotek.moderation_factory');
          /** @var \Drupal\lingotek\Moderation\LingotekModerationSettingsFormInterface $moderationForm */
          $moderationForm = $moderationFactory->getModerationSettingsForm();
          $moderationForm->submitHandler($entity_id, $bundle_id, $bundle);
        }
        else {
          // If we removed it, unable it.
          $lingotek_config->setEnabled($entity_id, $bundle_id, FALSE);
        }
      }
    }

    // There is some bug than local tasks block cache is not cleared. Let's do
    // that manually.
    $this->invalidateLocalTaskCacheBlocks();

    parent::submitForm($form, $form_state);
  }

  protected function retrieveProfileOptions() {
    $this->profiles = \Drupal::entityManager()->getListBuilder('lingotek_profile')->load();

    foreach ($this->profiles as $profile) {
      $this->profile_options[$profile->id()] = $profile->label();
    }
  }

  protected function retrieveBundles() {
    $entities = \Drupal::entityManager()->getDefinitions();
    $this->bundles = [];

    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityType && $entity->hasKey('langcode')) {
        $bundle = \Drupal::entityManager()->getBundleInfo($entity->id());
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

    if ($entity_id !== 'paragraph' || $enable_bulk_management) {
      $select = [
        '#type' => 'select',
        '#options' => $lingotek_config->getProfileOptions(),
        '#default_value' => $lingotek_config->getDefaultProfileId($entity_id, $bundle_id),
      ];
    }
    else {
      $select = [
        '#markup' => $this->t("A profile doesn't apply for paragraphs. Not recommended, but you may want to <a href=':link'>translate paragraphs independently</a>.", [':link' => '#edit-contrib-paragraphs-enable-bulk-management']),
      ];
    }
    return $select;
  }

  protected function retrieveFields($entity_id, $bundle_id) {
    $entity_type = \Drupal::entityManager()->getDefinition($entity_id);
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $storage_definitions = \Drupal::entityManager()->getFieldStorageDefinitions($entity_id);
    $field_checkboxes = [];

    if ($content_translation_manager->isSupported($entity_id)) {
      $fields = \Drupal::entityManager()->getFieldDefinitions($entity_id, $bundle_id);
      // Find which fields the user previously selected
      foreach ($fields as $field_id => $field_definition) {
        $checkbox_choice = 0;

        // We allow non-translatable entity_reference_revisions fields through.
        // See https://www.drupal.org/node/2788285
        if (!empty($storage_definitions[$field_id]) &&
              $storage_definitions[$field_id]->getProvider() != 'content_translation' &&
              !in_array($storage_definitions[$field_id]->getName(), [$entity_type->getKey('langcode'), $entity_type->getKey('default_langcode'), 'revision_translation_affected']) &&
          ($field_definition->isTranslatable() || ($field_definition->getType() == 'entity_reference_revisions' || $field_definition->getType() == 'path')) && !$field_definition->isComputed() && !$field_definition->isReadOnly()) {

          if ($value = $lingotek_config->isFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
            $checkbox_choice = $value;
          }
          $field_checkbox = [
            '#type' => 'checkbox',
            '#title' => $field_definition->getLabel(),
            '#default_value' => $checkbox_choice,
          ];
          $field_checkboxes[$field_id] = $field_checkbox;

          // Display the column translatability configuration widget.
          module_load_include('inc', 'content_translation', 'content_translation.admin');
          $column_element = content_translation_field_sync_widget($field_definition);
          if ($column_element) {
            $properties_checkbox_choice = $lingotek_config->getFieldPropertiesLingotekEnabled($entity_id, $bundle_id, $field_id);
            $field_checkbox = [
              '#type' => 'checkboxes',
              '#options' => $column_element['#options'],
              '#default_value' => $properties_checkbox_choice ?: [],
              '#attributes' => ['class' => ['field-property-checkbox']],
            ];
            $field_checkboxes[$field_id . ':properties'] = $field_checkbox;
          }
        }
        // We have an exception here, if the entity alias is a computed field we
        // may still want to translate it.
        elseif ($field_definition->getType() == 'path' && $field_definition->isComputed()) {
          if ($value = $lingotek_config->isFieldLingotekEnabled($entity_id, $bundle_id, $field_id)) {
            $checkbox_choice = $value;
          }
          $field_checkbox = [
            '#type' => 'checkbox',
            '#title' => $field_definition->getLabel(),
            '#default_value' => $checkbox_choice,
          ];
          $field_checkboxes[$field_id] = $field_checkbox;
        }
      }
    }

    return $field_checkboxes;
  }

  /**
   * Invalidates the local task cache blocks.
   */
  private function invalidateLocalTaskCacheBlocks() {
    if (\Drupal::moduleHandler()->moduleExists('block')) {
      // There is some bug than local tasks block cache is not cleared. Let's do
      // that manually.
      $ids = \Drupal::entityQuery('block')
        ->condition('plugin', 'local_tasks_block')
        ->execute();
      $tags = [];
      foreach ($ids as $id) {
        $block = Block::load($id);
        $tags = array_merge($tags, $block->getCacheTagsToInvalidate());
      }
      Cache::invalidateTags($tags);
    }
  }

}
