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
    
    foreach ($this->translatable_bundles as $entity_id => $bundles) {
      foreach ($bundles as $bundle_id => $bundle) {
        $row = array();
        $row['content_type'] = array(
          '#markup' => $this->t($bundle['label']),
        );
        $row['profiles'] = $this->retrieveProfiles($bundle_id);
        $row['fields'] = $this->retrieveFields($entity_id, $bundle_id);
        $table[$bundle_id] = $row;
      }
    }

    $form['content'] = array(
      '#type' => 'details',
      '#title' => 'Translate Content Types'
    );  

    $form['content']['table'] = $table;

    $form['content']['actions']['#type'] = 'actions';
    $form['content']['actions']['submit'] = array(
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
    $table = $form_values['table'];
    $profiles = array();
    $fields = array();
    
    // For every content type, save the profile and fields in the Lingotek object
    foreach($table as $bundle_id => $bundle) {
      foreach($bundle['fields'] as $field_id => $field_choice) {
        if ($field_choice == 1) {
          $profiles[$bundle_id]['field'][$field_id] = $table[$bundle_id]['fields'][$field_id];
        }
      }
      $profiles[$bundle_id]['profile'] = $table[$bundle_id]['profiles'];  
    }
  
    $this->L->set('translate.entity', $profiles);
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

  protected function retrieveProfiles($bundle_id) {
    $option_num;

    // Find which profile the user previously selected
    if ($this->L->get('translate.entity.' . $bundle_id . '.profile')) {
      $option_num = $this->L->get('translate.entity.' . $bundle_id . '.profile');
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
        if ($this->L->get('translate.entity.' . $bundle_id . '.field.' . $field_id)) {
          $checkbox_choice = $this->L->get('translate.entity.' . $bundle_id . '.field.' . $field_id);
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
