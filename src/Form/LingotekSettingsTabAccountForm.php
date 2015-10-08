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
    $isEnterprise = $this->t('Yes');
    $connectionStatus = $this->t('Inactive');

    if ($config->get('account.plan_type') == 'basic') {
      $isEnterprise = $this->t('No');
    }

    try {
      if ($this->lingotek->getAccountInfo()) {
        $connectionStatus = $this->t('Active');
      }
    } catch(LingotekApiException $exception) {
      drupal_set_message($this->t('There was a problem checking your account status.'), 'warning');
    }

    $statusRow = array(
      array('#markup' => $this->t('Status:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $connectionStatus),
    );
    $planRow = array(
      array('#markup' => $this->t('Enterprise:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $isEnterprise),
    );
    $activationRow = array(
      array('#markup' => $this->t('Activation Name:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $config->get('account.login_id')),
    );
    $communityRow = array(
      array('#markup' => $this->t('Community Identifier:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $config->get('default.community')),
    );
    $tokenRow = array(
      array('#markup' => $this->t('Access Token:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $config->get('account.access_token')),
    );
    $workflowRow = array(
      array('#markup' => $this->t('Workflow:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $config->get('default.workflow')),
    );
    $projectRow = array(
      array('#markup' => $this->t('Project ID:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $config->get('default.project')),
    );
    $vaultRow = array(
      array('#markup' => $this->t('Vault ID:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $config->get('default.vault')),
    );
    $tmsRow = array(
      array('#markup' => $this->t('Lingotek TMS Server:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => $config->get('account.host')),
    );
    $gmcRow = array(
      array('#markup' => $this->t('Lingotek GMC Server:'), '#prefix' => '<b>', '#suffix' => '</b>'), array('#markup' => 'https://gmc.lingotek.com'),
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
      '#title' => $this->t('Account'),
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
