<?php
/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekProfileFormBase.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lingotek\LingotekProfileInterface;

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
    /** @var LingotekProfileInterface $profile */
    $profile = $this->entity;
    $form['id'] = array(
      '#type' => 'machine_name',
      '#description' => t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$profile->isNew(),
      '#default_value' => $profile->id(),
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
      '#default_value' => $profile->label(),
    );
    $form['current_future_note'] = array(
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Profile settings impacting all entities (new and existing)') . '</h3><hr />',
    );
    $form['auto_upload'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Upload Content Automatically'),
      '#description' => $this->t('When enabled, your Drupal content (including saved edits) will automatically be uploaded to Lingotek for translation. When disabled, you are required to manually upload your content by clicking the "Upload" button on the Translations tab.'),
      '#disabled' => $profile->isLocked(),
      '#default_value' => $profile->hasAutomaticUpload(),
    );
    $form['auto_download'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Download Translations Automatically'),
      '#description' => $this->t('When enabled, completed translations will automatically be downloaded from Lingotek. When disabled, you are required to manually download translations by clicking the "Download" button on the Translations tab.'),
      '#disabled' => $profile->isLocked(),
      '#default_value' => $profile->hasAutomaticDownload(),
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
      '#default_value' => $profile->getVault(),
    );

    $projects = $this->config('lingotek.settings')->get('account.resources.project');
    $default_project = $this->config('lingotek.settings')->get('default.project');

    $form['project'] = array(
      '#type' => 'select',
      '#title' => $this->t('Default Project'),
      '#options' => ['default' => 'Default ('. $projects[$default_project] . ')'] + $projects,
      '#description' => $this->t('The default Translation Memory Project where translations are saved.'),
      '#default_value' => $profile->getProject(),
    );

    $workflows = $this->config('lingotek.settings')->get('account.resources.workflow');
    $default_workflow = $this->config('lingotek.settings')->get('default.workflow');

    $form['workflow'] = array(
      '#type' => 'select',
      '#title' => $this->t('Default Workflow'),
      '#options' => ['default' => 'Default ('. $workflows[$default_workflow] . ')'] + $workflows,
      '#description' => $this->t('The default Workflow which would be used for translations.'),
      '#default_value' => $profile->getWorkflow(),
    );

    $form['language_overrides'] = array(
      '#type' => 'details',
      '#title' => $this->t('Target language specific settings'),
      '#tree' => TRUE,
    );
    $languages = \Drupal::languageManager()->getLanguages();
    // We want to have them alphabetically.
    ksort($languages);
    foreach ($languages as $langcode => $language) {
      $form['language_overrides'][$langcode] = array(
        'overrides' => array(
          '#type' => 'select',
          '#title' => $language->getName() . ' (' . $language->getId() . ')',
          '#options' => ['default' => $this->t('Use default settings'), 'custom' => $this->t('Use custom settings')],
          '#default_value' => $profile->hasCustomSettingsForTarget($langcode) ? 'custom' : 'default',
        ),
        'custom' => array(
          '#type' => 'container',
          '#attributes' => array(
            'class' => 'profile-language-overrides-container',
          ),
          '#states' => array(
            'visible' => array(
              ':input[name="language_overrides['.$langcode.'][overrides]"]' => array('value' => 'custom'),
            ),
          ),
          'workflow' => array(
            '#type' => 'select',
            '#title' => $this->t('Default Workflow'),
            '#options' => ['default' => 'Default ('. $workflows[$default_workflow] . ')'] + $workflows,
            '#description' => $this->t('The default Workflow which would be used for translations.'),
            '#default_value' => $profile->hasCustomSettingsForTarget($langcode) ? $profile->getWorkflowForTarget($langcode) : 'default',
          ),
          'auto_download' => array(
            '#type' => 'checkbox',
            '#title' => $this->t('Download Translations Automatically'),
            '#description' => $this->t('When enabled, completed translations will automatically be downloaded from Lingotek. When disabled, you are required to manually download translations by clicking the "Download" button on the Translations tab.'),
            '#disabled' => $profile->isLocked(),
            '#default_value' => $profile->hasAutomaticDownloadForTarget($langcode),
          ),
        ),
      );
    }
    $form['#attached']['library'][] = 'lingotek/lingotek.settings';
    return $form;
  }

}