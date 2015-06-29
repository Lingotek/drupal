<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabContentForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\lingotek\Lingotek;

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
    $this->profiles = $this->L->get('profile');

    // Get the profiles
    $this->retrieveProfileOptions();

    // Retrieve bundles
    $this->retrieveBundles();
    
    // Retrieve translatable bundles
    $this->retrieveTranslatableBundles();

    $form['parent_details'] = array(
      '#type' => 'details',
      '#title' => 'Translate Content Types'
    );
    
    $form['parent_details']['list']['#type'] = 'container';
    $form['parent_details']['list']['#attributes']['class'][] = 'entity-meta';

    // If user specifies no translatable entities, post this message
    if (empty($this->translatable_bundles)) {
      $form['parent_details']['empty_message'] = array(
        '#markup' => t('There are no translatable content types specified'),
      );
    }
    
    // I. Loop through all entities and create a details container for each
    foreach ($this->translatable_bundles as $entity_id => $bundles) {
      $entity_key = 'entity-' . $entity_id;
      $form['parent_details']['list'][$entity_key] = array(
        '#type' => 'details',
        '#title' => $entity_id,
        'content' => array(),
      );

      $header = array(
        $this->t('Content Type'),
        $this->t('Translation Profile'),
        $this->t('Fields'),
      );

      $table = array(
        '#type' => 'table',
        '#header' => $header,
        '#empty' => $this->t('No Entries'),
      );

      // II. Loop through bundles per entity and make a table
      foreach ($bundles as $bundle_id => $bundle) {
        $row = array();
        $row['content_type'] = array(
          '#markup' => $this->t($bundle['label']),
        );
        $row['profiles'] = $this->retrieveProfiles($entity_id, $bundle_id);
        $row['fields'] = $this->retrieveFields($entity_id, $bundle_id);
        $table[$bundle_id] = $row;
      }

      // III. Add table to respective details 
      $form['parent_details']['list'][$entity_key]['content'][$entity_id] = $table;
    }

    if (!empty($this->translatable_bundles)) {
      $form['parent_details']['note'] = array(
        '#markup' => t('Note: changing the profile will update all settings for existing nodes except for the project, workflow, vault, and storage method (e.g. node/field)'),
      );

      $form['parent_details']['actions']['#type'] = 'actions';
      $form['parent_details']['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $data = array();
    
    // For every content type, save the profile and fields in the Lingotek object
    foreach($form_values as $entity => $bundles) {
      foreach($bundles as $bundle_id => $bundle) {
        foreach($bundle['fields'] as $field_id => $field_choice) {
          if ($field_choice == 1) {
            $data[$entity][$bundle_id]['field'][$field_id] = $bundles[$bundle_id]['fields'][$field_id];
          }
        }
        $data[$entity][$bundle_id]['profile'] = $bundles[$bundle_id]['profiles'];  
      }
    }
    $this->L->set('translate.entity', $data);
    parent::submitForm($form, $form_state);
  }

  protected function retrieveProfileOptions() {
    $this->profile_options = array();

    foreach ($this->profiles as $profile) {
      $this->profile_options[$profile['id']] = ucwords($profile['name']);
    }
  }

  protected function retrieveBundles() {
    $entities = \Drupal::entityManager()->getDefinitions();
    $this->bundles = array();

    foreach ($entities as $entity) {
      if ($entity instanceof \Drupal\Core\Entity\ContentEntityType) {
        $bundle = \Drupal::entityManager()->getBundleInfo($entity->id());
        $this->bundles[$entity->id()] = $bundle;
      }
    }
  }

  protected function retrieveTranslatableBundles() {
    $this->translatable_bundles = array();

    foreach ($this->bundles as $bundle_group_id => $bundle_group) {
      foreach ($bundle_group as $bundle_id => $bundle) {
        if ($bundle['translatable']) {
          $this->translatable_bundles[$bundle_group_id][$bundle_id] = $bundle;
        }
      }
    }
  }

  protected function retrieveProfiles($entity_id, $bundle_id) {
    $option_num;

    // Find which profile the user previously selected
    if ($this->L->get('translate.entity.' . $entity_id . '.' . $bundle_id . '.profile')) {
      $option_num = $this->L->get('translate.entity.' . $entity_id . '.' . $bundle_id . '.profile');
    }
    else {
      $option_num = Lingotek::PROFILE_AUTOMATIC;
    }
    
    $select = array(
      '#type' => 'select',
      '#options' => $this->profile_options,
      '#default_value' => $option_num,
    );
    
    return $select;
  }

  protected function retrieveFields($entity_id, $bundle_id) {
    $fields = \Drupal::entityManager()->getFieldDefinitions($entity_id, $bundle_id);
    $field_checkboxes = array ();
    $checkbox_choice;
    
    // Find which fields the user previously selected
    foreach($fields as $field_id => $field) {
      if ($field->isTranslatable()) {
        if ($this->L->get('translate.entity.' . $entity_id . '.' . $bundle_id . '.field.' . $field_id)) {
          $checkbox_choice = $this->L->get('translate.entity.' . $entity_id . '.' . $bundle_id . '.field.' . $field_id);
        }
        else {
          $checkbox_choice = 0;
        }

        $field_checkbox = array(
          '#type' => 'checkbox',
          '#title' => $this->t($field->getLabel()),
          '#default_value' => $checkbox_choice,
        );
        $field_checkboxes[$field_id] = $field_checkbox;
      }
    }

    return $field_checkboxes;
  }

}
