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

/**
 * Configure Lingotek
 */
class LingotekSettingsTabContentForm extends LingotekConfigFormBase {
  protected $profile_options;
  protected $profiles;
  protected $user_profiles;
  protected $content_types;

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
    $this->content_types = array();

    // Get the default profiles
    $this->profile_options = array();
    foreach ($this->profiles as $profile) {
      $this->profile_options[$profile['id']] = ucwords($profile['name']);
    }
    $this->profile_options['3'] = $this->t('Disabled');

    // Only use node types the user has access to.
    foreach (\Drupal::entityManager()->getStorage('node_type')->loadMultiple() as $type) {
      if (\Drupal::entityManager()->getAccessControlHandler('node')->createAccess($type->id())) {
        $this->content_types[$type->id()] = $type;
      }
    }

    $header = array(
      $this->t('Content Types'),
      $this->t('Translation Profile'),
      $this->t('Fields'),
    );

    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No Entries'),
    );
    
    foreach ($this->content_types as $entity_type_id) {
      $row = array();
      $row['content_type'] = array(
        '#markup' => $this->t('@name', array('@name' => $entity_type_id->label())),
      );
      $row['profiles'] = $this->retrieveProfiles($entity_type_id->id());
      $row['fields'] = $this->retrieveFields($entity_type_id->id());
      $table[$entity_type_id->id()] = $row;
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
    
    // For every content type, save the profile in the L object
    foreach($this->content_types as $content_type) {
      $this->L->set('translate.entity' . '.' . $content_type->id(), $table[$content_type->id()]['profiles']);
    }
  }

  protected function retrieveProfiles($entity_type_id) {
    $option_num;

    // Find which profile the user previously selected
    if ($this->L->get('translate.entity.' . $entity_type_id)) {
      $option_num = $this->L->get('translate.entity.' . $entity_type_id);
    }
    else {
      $option_num = 1;
    }
    
    $select = array(
      '#type' => 'select',
      '#options' => $this->profile_options,
      '#default_value' => $option_num,
    );
    
    return $select;
  }

  protected function retrieveFields($entity_type_id) {
    // $entityTypes = \Drupal::entityManager()->getDefinitions();
      
    // foreach ($entityTypes as $type) {
    //   $field = \Drupal::entityManager()->getFieldDefinitions('node', 'page');
    //   $name = $field->getName();
    // }
    // $field_storage = $entity_type_id->getFieldStorageDefinition();
  }

}
