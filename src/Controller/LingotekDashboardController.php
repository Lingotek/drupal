<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekDashboardController extends LingotekControllerBase {

  /**
   * Presents a dashboard overview page of translation status through Lingotek.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The page request.
   *
   * @return array
   *   The dashboard form, or a redirect to the connect page.
   */
  public function dashboardPage(Request $request) {
    $cms_data = $this->getDashboardInfo();
    $string = '<h2>' . t('Dashboard') . '</h2><script>var cms_data = ' . json_encode($cms_data, JSON_PRETTY_PRINT) . '</script> <link rel="stylesheet" href="http://gmc.lingotek.com/v2/styles/ltk.css"> <script src="http://gmc.lingotek.com/v2/ltk.min.js"></script> <div ltk-dashboard ng-app="LingotekApp" style="margin-top: -15px;"></div>';
    return $string;
  }

  protected function getDashboardInfo() {
    global $base_url;
    return array(
      "community_id"=> $this->L->get('default.community'),
      "external_id"=> $this->L->get('account.login_id'),
      "vault_id"=> $this->L->get('default.vault'),
      "workflow_id"=> $this->L->get('default.workflow'),
      "project_id"=> $this->L->get('default.project'),
      "first_name"=> 'Drupal User',
      "last_name"=> '',
      "email"=> $this->L->get('account.login_id'),
      // cms
      "cms_site_id"=> $base_url,
      "cms_site_key"=> $base_url,
      "cms_site_name"=> 'Drupal Site',
      "cms_type"=> 'Drupal',
      "cms_version"=> 'VERSION HERE',
      "cms_tag"=> 'CMS TAG HERE',
      "locale"=> "en_US", // FIX: should be currently selected locale
      "module_version" => '1.x',
      "endpoint_url" => '',
    );
  }
}
