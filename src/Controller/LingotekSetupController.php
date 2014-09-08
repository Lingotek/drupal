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
    if ($this->setupCompleted() || $this->receivedToken()) {
      $this->saveToken($this->receivedToken());
      $account_info = $this->fetchAccountInfo();
      $this->saveAccountInfo($account_info);
      return $this->getLingotekForm('LingotekSettingsAccountForm');
    }
    return $this->getLingotekForm('LingotekSettingsConnectForm');
  }

  public function communityPage() {
    return $this->getLingotekForm('LingotekSettingsCommunityForm');
  }

  public function projectVaultPage() {
    return $this->getLingotekForm('LingotekSettingsProjectVaultForm');
  }

  protected function receivedToken() {
    return $this->request->get('access_token');
  }

  protected function saveToken($token) {
    if (!empty($token)) {
      $this->config('lingotek.settings')->set('account.access_token', $token)->save();
    }
  }

  protected function saveAccountInfo($account_info) {
    if (!empty($account_info)) {
      $settings = $this->config('lingotek.settings');
      $settings->set('account.login_id', $account_info['login_id']);
      $settings->set('account.access_token', $account_info['id']);
      $settings->save();
    }
  }

  protected function fetchAccountInfo() {
    $api = $this->container->get('lingotek.api');
    return $api->getAccountInfo();
  }

}
