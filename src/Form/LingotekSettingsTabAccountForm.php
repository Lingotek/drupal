<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsAccountForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabAccountForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_account_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $isEnterprise = 'Yes';
    $connection_status = 'Inactive';
    $community_id = $this->L->get('default.community');

    if ($this->L->get('account.plan_type') == 'basic') {
      $isEnterprise = 'No';
    }

    if ($this->L->get('account.access_token')) {  
      $connection_status = 'Active';
    }

    $form['account'] = array(
      '#type' => 'details',
      '#title' => t('Account'),
    );
    $form['account']['connection_status'] = array(
      '#type' => 'textfield',
      '#title' => t('Status'),
      '#value' => t($connection_status),
      '#disabled' => TRUE,
    );
    $form['account']['plan_type'] = array(
      '#type' => 'textfield',
      '#title' => t('Enterprise'),
      '#value' => t($isEnterprise),
      '#disabled' => TRUE,
    );
    $form['account']['login'] = array(
      '#type' => 'textfield',
      '#title' => t('Activation Name'),
      '#value' => $this->L->get('account.login_id'),
      '#disabled' => TRUE,
    );
    $form['account']['community'] = array(
      '#title' => t('Community Identifier'),
      '#type' => 'textfield',
      '#value' => $community_id,
      '#disabled' => TRUE,
    );
    $form['account']['access_token'] = array(
      '#type' => 'textfield',
      '#title' => t('Access Token'),
      '#value' => $this->L->get('account.access_token'),
      '#disabled' => TRUE,
    );
    $form['account']['workflow'] = array(
      '#title' => t('Workflow ID'),
      '#type' => 'textfield',
      '#value' => $this->L->get('default.workflow'),
      '#disabled' => TRUE,
    );
    $form['account']['integration'] = array(
      '#title' => t('Integration Method ID'),
      '#type' => 'textfield',
      '#value' => $this->L->get('account.default_client_id'),
      '#disabled' => TRUE,
    );
    $form['account']['project'] = array(
      '#title' => t('Project ID'),
      '#type' => 'textfield',
      '#value' => $this->L->get('default.project'),
      '#disabled' => TRUE,
    );
    $form['account']['vault'] = array(
      '#title' => t('Vault ID'),
      '#type' => 'textfield',
      '#value' => $this->L->get('default.vault'),
      '#disabled' => TRUE,
    );
    $form['account']['server1'] = array(
      '#title' => t('Lingotek Servers'),
      '#type' => 'textfield',
      '#value' => 'TMS: https://myaccount.lingotek.com',
      '#disabled' => TRUE,
    );
    $form['account']['server2'] = array(
      '#type' => 'textfield',
      '#value' => 'GMC: https://gmc.lingotek.com',
      '#disabled' => TRUE,
    );

     return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Accounts!');
  }

}
