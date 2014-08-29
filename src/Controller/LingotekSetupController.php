<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;
use Symfony\Component\HttpFoundation\Request;

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
  public function connectPage() {
    if ($this->setupCompleted()) {
      return $this->getLingotekForm('LingotekSettingsAccountForm');
    }
    elseif ($this->connected()) {
      return $this->redirect('lingotek.setup_community');
    }
    return $this->getLingotekForm('LingotekSettingsConnectForm');
  }

  public function communityPage() {
    return $this->getLingotekForm('LingotekSettingsCommunityForm');
  }

}
