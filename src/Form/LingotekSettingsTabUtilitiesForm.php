<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsUtilitiesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

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

    $api_refresh_row = array();
    $api_refresh_row['refresh_description'] = array(
      '#markup' => '<H5>' . $this->t('Refresh Project, Workflow, and Vault Information') . '</H5>' . '<p>' . $this->t('This module locally caches the available projects, workflows, and vaults. Use this utility whenever you need to pull down names for any newly created projects, workflows, or vaults from the Lingotek Translation Management System.') . '</p>',
    );

    $api_refresh_row['refresh_button'] = array(
      '#type' => 'submit',
      '#value' => 'Refresh',
      '#button_type' => 'primary'
    );

    $table['api_refresh'] = $api_refresh_row;
    $form['utilities'] = array(
      '#type' => 'details',
      '#title' => t('Utilities'),
    );

    $form['utilities']['utilities_title'] = array(
      '#markup' => '<H4>' . $this->t('Lingotek Utilities' . '</H4>'),
    );

    $form['utilities']['table'] = $table;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->refreshResources();
  }

  protected function refreshResources() {
    $resources = $this->L->getResources(TRUE);
    $this->L->set('account.resources', $resources);
  }

}
