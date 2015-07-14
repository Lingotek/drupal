<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekSetupController extends LingotekControllerBase {

  /**
   * Presents a connection page to Lingotek Services
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The page request.
   *
   * @return array
   *   The connection form.
   */
  public function accountPage() {
    if ($this->setupCompleted()) {
      return $this->getLingotekForm('LingotekSettingsAccountForm');
    }
    if ($this->receivedToken()) {
      $this->saveToken($this->receivedToken());
      $account_info = $this->fetchAccountInfo();
      $this->saveAccountInfo($account_info);
      drupal_set_message($this->t('Your account settings have been saved.'));
      
      // No need to show the username and token if every worked ok
      return $this->redirect('lingotek.setup_community');
      
      //return $this->getLingotekForm('LingotekSettingsAccountForm');
    }
    return array(
      '#type' => 'markup',
      'markup' => $this->getLingotekForm('LingotekSettingsConnectForm'),
    );
  }

  public function communityPage() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $communities = $this->L->getCommunities();
    if (empty($communities)) {
      // TODO: Log an error that no communities exist.
      return $this->redirect('lingotek.setup_account');
    }
    $this->L->set('account.resources.community', $communities);
    if (count($communities) == 1) {
      // No choice necessary. Save and advance to next page.
      $this->L->set('default.community', current(array_keys($communities)));
      $this->L->getResources(TRUE); // update resources based on newly selected community
      return $this->redirect('lingotek.setup_defaults');
    }
    return array(
      '#type' => 'markup',
      'markup' => $this->getLingotekForm('LingotekSettingsCommunityForm'),
    );
  }

  public function defaultsPage() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $projects = $this->L->getProjects();
    $vaults = $this->L->getVaults();
    // No choice necessary. Save and advance to the next page.
    if (count($projects) == 1 && count($vaults) == 1) {
      return $this->redirect('lingotek.dashboard');
    }
    return array(
      '#type' => 'markup',
      'markup' => $this->getLingotekForm('LingotekSettingsDefaultsForm'),
    );
  }

  protected function receivedToken() {
    return $this->request->get('access_token');
  }

  protected function saveToken($token) {
    if (!empty($token)) {
      $this->L->set('account.access_token', $token);
    }
  }

  protected function  saveAccountInfo($account_info) {
    if (!empty($account_info)) {
      $this->L->set('account.login_id', $account_info['login_id']);
      $this->L->set('account.access_token', $account_info['id']);
    }
  }

  protected function fetchAccountInfo() {
    return $this->L->getAccountInfo();
  }

}
