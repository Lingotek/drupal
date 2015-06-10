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
    $connectionStatus = 'Inactive';

    if ($this->L->get('account.plan_type') == 'basic') {
      $isEnterprise = 'No';
    }

    if ($this->L->getAccountInfo()) {  
      $connectionStatus = 'Active';
    }

    $statusRow = array(
      array('#markup' => $this->t('Status:')), array('#markup' => $this->t($connectionStatus)),
    );
    $planRow = array(
      array('#markup' => $this->t('Enterprise:')), array('#markup' => $this->t($isEnterprise)),
    );
    $activationRow = array(
      array('#markup' => $this->t('Activation Name:')), array('#markup' => $this->t($this->L->get('account.login_id'))),
    );
    $communityRow = array(
      array('#markup' => $this->t('Community Identifier:')), array('#markup' => $this->t($this->L->get('default.community'))),
    );
    $tokenRow = array(
      array('#markup' => $this->t('Access Token:')), array('#markup' => $this->t($this->L->get('account.access_token'))),
    );
    $workflowRow = array(
      array('#markup' => $this->t('Workflow:')), array('#markup' => $this->t($this->L->get('default.workflow'))),
    );
    $integrationRow = array(
      array('#markup' => $this->t('Integration Method:')), array('#markup' => $this->t($this->L->get('account.default_client_id'))),
    );
    $projectRow = array(
      array('#markup' => $this->t('Project ID:')), array('#markup' => $this->t($this->L->get('default.project'))),
    );
    $vaultRow = array(
      array('#markup' => $this->t('Vault ID:')), array('#markup' => $this->t($this->L->get('default.vault'))),
    );
    $tmsRow = array(
      array('#markup' => $this->t('Lingotek TMS Server:')), array('#markup' => $this->t($this->L->get('account.host'))),
    );
    $gmcRow = array(
      array('#markup' => $this->t('Lingotek GMC Server:')), array('#markup' => $this->t('https://gmc.lingotek.com')),
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
    $accountTable['integration_row'] = $integrationRow;
    $accountTable['project_row'] = $projectRow;
    $accountTable['vault_row'] = $vaultRow;
    $accountTable['tms_row'] = $tmsRow;
    $accountTable['gmc_row'] = $gmcRow;

    $form['account'] = array(
      '#type' => 'details',
      '#title' => 'Account',
    );

    $form['account']['account_table'] = $accountTable;

     return $form;
  }

}
