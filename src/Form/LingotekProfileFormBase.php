<?php
/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekProfileFormBase.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a common base class for Profile forms.
 */
class LingotekProfileFormBase extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_profile_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['id'] = array(
      '#type' => 'machine_name',
      '#description' => t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$this->entity->isNew(),
      '#default_value' => $this->entity->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\lingotek\Entity\LingotekProfile::load',
        'source' => array('label'),
        'replace_pattern' =>'([^a-z0-9_]+)|(^custom$)',
        'error' => $this->t('The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".'),
      ),
    );
    $form['label'] = array(
      '#id' => 'label',
      '#type' => 'textfield',
      '#title' => $this->t('Profile Name'),
      '#required' => TRUE,
      '#default_value' => $this->entity->label(),
    );
    $form['current_future_note'] = array(
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Profile settings impacting all entities (new and existing)') . '</h3><hr />',
    );
    $form['auto_upload'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Upload Content Automatically'),
      '#description' => $this->t('When enabled, your Drupal content (including saved edits) will automatically be uploaded to Lingotek for translation. When disabled, you are required to manually upload your content by clicking the "Upload" button on the Translations tab.'),
      '#disabled' => $this->entity->isLocked(),
      '#default_value' => $this->entity->hasAutomaticUpload(),
    );
    $form['auto_download'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Download Translations Automatically'),
      '#description' => $this->t('When enabled, completed translations will automatically be downloaded from Lingotek. When disabled, you are required to manually download translations by clicking the "Download" button on the Translations tab.'),
      '#disabled' => $this->entity->isLocked(),
      '#default_value' => $this->entity->hasAutomaticDownload(),
    );
    $form['future_only_note'] = array(
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Profile settings impacting only new nodes') . '</h3><hr />',
    );

    $vaults = $this->config('lingotek.settings')->get('account.resources.vault');
    $default_vault = $this->config('lingotek.settings')->get('default.vault');

    $form['vault'] = array(
      '#type' => 'select',
      '#title' => $this->t('Default Vault'),
      '#options' => ['default' => 'Default ('. $vaults[$default_vault] . ')'] + $vaults,
      '#description' => $this->t('The default Translation Memory Vault where translations are saved.'),
      '#default_value' => $this->entity->getVault(),
    );

    $projects = $this->config('lingotek.settings')->get('account.resources.project');
    $default_project = $this->config('lingotek.settings')->get('default.project');

    $form['project'] = array(
      '#type' => 'select',
      '#title' => $this->t('Default Project'),
      '#options' => ['default' => 'Default ('. $projects[$default_project] . ')'] + $projects,
      '#description' => $this->t('The default Translation Memory Project where translations are saved.'),
      '#default_value' => $this->entity->getProject(),
    );

    return $form;
  }

}