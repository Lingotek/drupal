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
class LingotekSettingsTabMainForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_main_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $plan_type = 'Yes';
    $connection_status = 'Inactive';
    $community_id = $this->L->get('default.community');

    $form['settings'] = array(
      '#type' => 'vertical_tabs',
    );

    $form['account'] = array(
      '#type' => 'details',
      '#title' => t('Account'),
      '#group' => 'settings',
    );
    $form['account']['connection_status'] = array(
      '#type' => 'textfield',
      '#title' => t('Status' . $connection_status),
      '#value' => t($connection_status),
      '#disabled' => TRUE,
    );
    $form['account']['plan_type'] = array(
      '#type' => 'textfield',
      '#title' => t('Enterprise'),
      '#value' => t($plan_type),
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

    $form['nodes'] = array(
      '#type' => 'details',
      '#title' => t('Translate Nodes'),
      '#description' => t('The default automation settings and resources that should be used for this site. These settings can be overriden using translation profiles and content type configuration.'),
      '#group' => 'settings',
    );
    $form['nodes']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save Changes'),
      '#submit' => array('::test'),
    );

    $form['comments'] = array(
      '#type' => 'details',
      '#title' => t('Translate Comments'),
      '#description' => t('Translation of comments'),
      '#group' => 'settings',
    );
    $form['comments']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => array('::commentsSubmit'),
    );

     return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Accounts!');
  }

  public function test(array &$form, FormStateInterface $form_state) {
    dpm('Behold!');
  }

  public function commentsSubmit(array &$form, FormStateInterface $form_state) { 
    dpm('Behold Comments!');
  }

}
