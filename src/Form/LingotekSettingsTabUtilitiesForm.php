<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\lingotek\LingotekSync;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabUtilitiesForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_utilities_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $table = array(
      '#type' => 'table',
      '#empty' => $this->t('No Entries'),
    );

    // Refresh resources via API row
    $api_refresh_row = array();
    $api_refresh_row['refresh_description'] = array(
      '#markup' => '<H5>' . $this->t('Refresh Project, Workflow, and Vault Information') . '</H5>' . '<p>' . $this->t('This module locally caches the available projects, workflows, and vaults. Use this utility whenever you need to pull down names for any newly created projects, workflows, or vaults from the Lingotek Translation Management System.') . '</p>',
    );
    $api_refresh_row['actions']['refresh_button'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
      '#button_type' => 'primary',
      '#submit' => array('::refreshResources'),
    );

    // Disassociate All Translations row
    $disassociate_row = array();
    $disassociate_row['disassociate_description'] = array(
      '#markup' => '<H5>' . $this->t('Disassociate All Translations (use with caution)') . '</H5>' . '<p>' . $this->t('Should only be used to change the Lingotek project or TM vault associated with the node’s translation. Option to disassociate node translations on Lingotek’s servers from the copies downloaded to Drupal. Additional translation using Lingotek will require re-uploading the node’s content to restart the translation process.') . '</p>',
    );
    $disassociate_row['actions']['disassociate_button'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Disassociate'),
      '#button_type' => 'primary',
      '#submit' => array('::disassociateAllTranslations'),
    );

    $table['api_refresh'] = $api_refresh_row;
    $table['disassociate'] = $disassociate_row;
    $form['utilities'] = array(
      '#type' => 'details',
      '#title' => $this->t('Utilities'),
    );

    $form['utilities']['utilities_title'] = array(
      '#markup' => '<H4>' . $this->t('Lingotek Utilities' . '</H4>'),
    );
    $form['utilities']['table'] = $table;

    return $form;
  }

  public function refreshResources() {
    $resources = $this->L->getResources(TRUE);
    $this->L->set('account.resources', $resources);
    drupal_set_message($this->t('Project, workflow, and vault information have been refreshed.'));
  }

  public function updateNotificationUrl() {
    dpm('Update the URL!');
  }

  public function disassociateAllTranslations() {
    LingotekSync::disassociateAllEntities();
    drupal_set_message($this->t('All translations have been disassociated.'));
  }

}
