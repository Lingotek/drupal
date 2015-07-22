<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabProfilesEditForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabProfilesEditForm extends LingotekConfigFormBase {
  protected $profile;
  protected $first_custom_id = 4;
  protected $is_custom_id;
  protected $profile_vaults;
  protected $auto_upload_disabled;
  protected $auto_download_disabled;
  protected $profile_name_disabled;
  protected $profile_index; 
  protected $profile_usage;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_profile_edit_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->retrieveProfileFromUrl();
    $this->isCustomId();
    $this->retrieveProfileVaults();
    $this->retrieveEnabledSettings();

    $form['defaults']['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Profile Name'),
      '#default_value' => $this->t(ucwords($this->profile['name'])),
      '#disabled' => $this->profile_name_disabled, 
    );

    $form['defaults']['current_future_note'] = array(
      '#type' => 'markup',
      '#markup' => '<h3>' . t('Profile settings impacting all entities (new and existing)') . '</h3><hr />',
    );

    $form['defaults']['auto_upload'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Upload Content Automatically'),
      '#description' => $this->t('When enabled, your Drupal content (including saved edits) will automatically be uploaded to Lingotek for translation. When disabled, you are required to manually upload your content by clicking the "Upload" button on the Translations tab.'),
      '#disabled' => $this->auto_upload_disabled,
      '#default_value' => $this->profile['auto_upload'],
    );

    $form['defaults']['auto_download'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Download Translations Automatically'),
      '#description' => $this->t('When enabled, completed translations will automatically be downloaded from Lingotek. When disabled, you are required to manually download translations by clicking the "Download" button on the Translations tab.'),
      '#disabled' => $this->auto_download_disabled,
      '#default_value' => $this->profile['auto_download'],
    );

    $form['defaults']['future_only_note'] = array(
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Profile settings impacting only new nodes') . '</h3><hr />',
    );

    $form['defaults']['vault'] = array(
      '#type' => 'select',
      '#title' => $this->t('Default Vault'),
      '#options' => $this->profile_vaults,
      '#description' => $this->t('The default Translation Memory Vault where translations are saved.'),
      '#default_value' => FALSE
    );

    $form['defaults']['actions']['#type'] = 'actions';
    $form['defaults']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );

    $form['defaults']['actions']['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#button_type' => 'primary',
      '#submit' => array('::cancel'),
    );

    // User cannot delete a non-custom profile
    if (!$this->is_custom_id) {
      $form['defaults']['actions']['cant_delete_button'] = array(
        '#type' => 'submit',
        '#value' => $this->t('This profile cannot be deleted'),
        '#button_type' => 'primary',
        '#disabled' => TRUE,
      );
    }
    // User cannot delete if the profile is being used
    elseif($this->profile_usage > 0 and $this->is_custom_id) {
      $form['defaults']['actions']['cant_delete_usage_button'] = array(
        '#type' => 'submit',
        '#value' => $this->t('You can only delete this profile when there are no entities or bundles using it'),
        '#button_type' => 'primary',
        '#disabled' => TRUE,
      );
    }
    // User can delete the profile
    else {
      $form['defaults']['actions']['delete'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#button_type' => 'primary',
        '#submit' => array('::deleteButton'),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    // Save a new profile
    if (!$this->profile) {
      $this->saveNewProfile(strtolower($form_values['name']), $form_values['auto_upload'], $form_values['auto_download']);
    }
    // Edit an existing custom profile
    elseif ($this->is_custom_id){
      $this->updateCustomProfile(strtolower($form_values['name']), $form_values['auto_upload'], $form_values['auto_download']);
    }

    $this->L->set('default.vault', $form_values['vault']);

    $form_state->setRedirect('lingotek.settings');
    parent::submitForm($form, $form_state);
  }

  public function deleteButton(array &$form, FormStateInterface $form_state) {
    if ($this->profile_usage == 0 and $this->is_custom_id){
      $this->deleteCustomProfile();
    }

    drupal_set_message($this->t('The profile has been deleted.'));
    $form_state->setRedirect('lingotek.settings');
  }

  public function cancel(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('lingotek.settings');
  }

  protected function retrieveProfileFromUrl() {
    if (isset($_GET['profile_choice']) and isset($_GET['profile_index']) and isset($_GET['profile_usage'])) {
      $this->profile = $_GET['profile_choice'];
      $this->profile_index = $_GET['profile_index'];
      $this->profile_usage = $_GET['profile_usage'];
    }
  }

  protected function isCustomId(){
    $this->is_custom_id = FALSE;

    if ($this->profile['id'] >= $this->first_custom_id) {
      $this->is_custom_id = TRUE;
    }  
  }

  protected function retrieveProfileVaults() {
    $personal_vault_key = $this->L->get('default.vault');
    $community_vault = $this->L->get('account.resources.vault');
    $personal_vault = array(
        $personal_vault_key => $community_vault[$personal_vault_key]
      );
    $this->profile_vaults = array(
      'Personal Vaults' => $personal_vault,
      'Community Vaults' => $community_vault,
    );
  }

  protected function retrieveEnabledSettings() {
    // Adding a new profile
    if (!$this->profile) {
      $this->auto_upload_disabled = FALSE;
      $this->auto_download_disabled = FALSE;
      $this->profile_name_disabled = FALSE;
    }
    // Automatic or Manual
    elseif (!$this->is_custom_id) { 
      $this->auto_upload_disabled = TRUE;
      $this->auto_download_disabled = TRUE;
      $this->profile_name_disabled = TRUE;
    }
    // Custom Profile
    elseif($this->is_custom_id) {
      $this->auto_upload_disabled = FALSE;
      $this->auto_download_disabled = FALSE;
      $this->profile_name_disabled = FALSE;
    }
  }

  protected function saveNewProfile($name, $auto_upload, $auto_download) {
    $profiles = $this->L->get('profile');
    end($profiles);
    $end_index = key($profiles);
    $end_id = $profiles[$end_index]['id'];
    $end_index++;
    $end_id++;
    $new_profile = array(
      'id' => $end_id,
      'name' => $name,
      'auto_upload' => $auto_upload == 0 ? NULL : $auto_upload,
      'auto_download' => $auto_download == 0 ? NULL : $auto_download,
    );
    
    $this->L->set('profile.' . $end_index, $new_profile);
  }

  protected function updateCustomProfile($name, $auto_upload, $auto_download) {
    $current_profile_id = $this->profile['id'];
    $new_profile = array(
      'id' => $current_profile_id,
      'name' => $name,
      'auto_upload' => $auto_upload == 0 ? NULL : $auto_upload,
      'auto_download' => $auto_download == 0 ? NULL : $auto_download,
    );
    
    $this->L->set('profile.' . $this->profile_index, $new_profile);
  }

  protected function deleteCustomProfile() {
    $profiles = $this->L->get('profile');
    unset($profiles[$this->profile_index]);
    $this->L->set('profile', $profiles);
  }

}
