<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabConfigurationForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabConfigurationForm extends LingotekConfigFormBase {
  protected $profile_options;
  protected $profiles;
  protected $bundles;
  protected $translatable_bundles;
  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_configuration_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->profiles = $this->L->get('profile');
    
    // Get the default profiles
    $this->retrieveProfileOptions();

    // Retrieve bundles
    $this->retrieveBundles();

    // Retrieve translatable bundles
    $this->retrieveTranslatableBundles();

    $header = array(
      $this->t('Configuration Type'),
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
        //$row['fields'] = $this->retrieveFields($entity_id, $bundle_id);
        $table[$bundle_id] = $row;
      }
    }

    $form['config'] = array(
      '#type' => 'details',
      '#title' => 'Translate Configuration Types'
    );  

    $form['config']['table'] = $table;

    $form['config']['actions']['#type'] = 'actions';
    $form['config']['actions']['submit'] = array(
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
    dpm('Configuration!');
  }

  protected function retrieveProfileOptions() {
    $this->profile_options = array();

    foreach ($this->profiles as $profile) {
      $this->profile_options[$profile['id']] = ucwords($profile['name']);
    }
  }

  protected function retrieveBundles() {
    $entities = \Drupal::entityManager()->getDefinitions();
    $entities['block']->set('translatable', 1);
    $this->bundles = array();
    foreach ($entities as $entity) {
      if ($entity instanceof \Drupal\Core\Config\Entity\ConfigEntityType) {
        $bundle = \Drupal::entityManager()->getBundleInfo($entity->id());
        $this->bundles[$entity->id()] = $bundle;
      }
    }
    //dpm($this->bundles);
    // Test until I can find how to make config enity types translatable in the GUI
    $this->bundles['block']['block']['translatable'] = 1;
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
    // if ($this->L->get('translate.entity.' . $bundle_id)) {
    //   $option_num = $this->L->get('translate.entity.' . $bundle_id);
    // }
    // else {
    //   $option_num = $this->L->PROFILE_AUTOMATIC;
    // }
    
    $select = array(
      '#type' => 'select',
      '#options' => $this->profile_options,
      '#default_value' => 1,
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
        // if ($this->L->get('field.' . $bundle_id . '.' . $field_id)) {
        //   $checkbox_choice = $this->L->get('field.' . $bundle_id . '.' . $field_id);
        // }
        // else {
        //   $checkbox_choice = 0;
        // }

        $field_checkbox = array(
          '#type' => 'checkbox',
          '#title' => $this->t($field->getLabel()),
          '#default_value' => 0,
        );
        $field_checkboxes[$field_id] = $field_checkbox;
      }
    }

    return $field_checkboxes;
  }

}
