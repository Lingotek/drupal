<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsAccountForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\lingotek\Exception\LingotekApiException;

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
    $config = $this->config('lingotek.settings');
    $isEnterprise = 'Yes';
    $connectionStatus = 'Inactive';

    if ($config->get('account.plan_type') == 'basic') {
      $isEnterprise = 'No';
    }

    try {
      if ($this->lingotek->getAccountInfo()) {
        $connectionStatus = 'Active';
      }
    } catch(LingotekApiException $exception) {
      drupal_set_message('There was a problem checking your account status.', 'warning');
    }

    $statusRow = array(
      array('#markup' => $this->t('<b>Status:</b>')), array('#markup' => $this->t($connectionStatus)),
    );
    $planRow = array(
      array('#markup' => $this->t('<b>Enterprise:</b>')), array('#markup' => $this->t($isEnterprise)),
    );
    $activationRow = array(
      array('#markup' => $this->t('<b>Activation Name:</b>')), array('#markup' => $this->t($config->get('account.login_id'))),
    );
    $communityRow = array(
      array('#markup' => $this->t('<b>Community Identifier:</b>')), array('#markup' => $this->t($config->get('default.community'))),
    );
    $tokenRow = array(
      array('#markup' => $this->t('<b>Access Token:</b>')), array('#markup' => $this->t($config->get('account.access_token'))),
    );
    $workflowRow = array(
      array('#markup' => $this->t('<b>Workflow:</b>')), array('#markup' => $this->t($config->get('default.workflow'))),
    );
    $projectRow = array(
      array('#markup' => $this->t('<b>Project ID:</b>')), array('#markup' => $this->t($config->get('default.project'))),
    );
    $vaultRow = array(
      array('#markup' => $this->t('<b>Vault ID:</b>')), array('#markup' => $this->t($config->get('default.vault'))),
    );
    $tmsRow = array(
      array('#markup' => $this->t('<b>Lingotek TMS Server:</b>')), array('#markup' => $this->t($config->get('account.host'))),
    );
    $gmcRow = array(
      array('#markup' => $this->t('<b>Lingotek GMC Server:</b>')), array('#markup' => $this->t('https://gmc.lingotek.com')),
    );
    
    $accountTable = array(
      '#type' => 'table',
      '#empty' => $this->t('No Entries'),
    );

    $accountTable['status_row'] = $statusRow;
    $accountTable['plan_row'] = $planRow;
    $accountTable['activation_row'] = $activationRow;
    $accountTable['community_row'] = $communityRow;
    $accountTable['token_row'] = $tokenRow;
    $accountTable['workflow_row'] = $workflowRow;
    $accountTable['project_row'] = $projectRow;
    $accountTable['vault_row'] = $vaultRow;
    $accountTable['tms_row'] = $tmsRow;
    $accountTable['gmc_row'] = $gmcRow;

    $form['account'] = array(
      '#type' => 'details',
      '#title' => 'Account',
    );

    $form['account']['account_table'] = $accountTable;
    $form['account']['actions']['#type'] = 'actions';
    $form['account']['actions']['disconnect'] = [
      '#type' => 'submit',
      '#value' => $this->t('Disconnect'),
      '#button_type' => 'danger',
      '#submit' => array(array($this, 'disconnect')),
    ];

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function disconnect(array &$form, FormStateInterface $form_state) {
    // Redirect to the confirmation form.
    $form_state->setRedirect('lingotek.account_disconnect');
  }

}
