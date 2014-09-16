<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;
use Drupal\lingotek\LingotekLocale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

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

  public function dashboardStatsPage(Request $request) {
    $stats_array = array(
    'method' => 'GET',
    'count' => 6,
    );
    // Get languages
    $languages = \Drupal::languageManager()->getLanguages();
    $active_languages = 0;
    $enabled_languages = 0;

    foreach (array_keys($languages) as $langcode) {
      $active_languages++; // WTD: pull from active languages
      $enabled_languages++; // WTD: pull from lingotek-enabled languages
      $locale = LingotekLocale::convertDrupal2Lingotek($langcode);

      $stats_array['languages'][$locale] = $this->getLanguageReport($langcode);
    }
    // FIXME: make source and target totals dynamic
    $stats_array['source'] = array(
      'types' => array(
        'node' => 0,
      ),
      'total' => 0,
    );
    $stats_array['target'] = array(
      'types' => array(
        'node' => 0,
      ),
      'total' => 0,
    );
    return JsonResponse::create($stats_array);
  }

  protected function getDashboardInfo() {
    global $base_url, $base_root;
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
      "endpoint_url" => $base_root . \Drupal::url('lingotek.dashboard_stats'),
    );
  }

  protected function getLanguageReport($langcode, $active = 1, $enabled = 1) {
    $locale = LingotekLocale::convertDrupal2Lingotek($langcode);
    $stat = array(
      'locale' => $locale,
      'xcode' => $langcode,
      'active' => 1,
      'enabled' => 1,
      'source' => array(
        'types' => array(),
        'total' => 0,
      ),
      'target' => array(
        'types' => array(),
        'total' => 0,
      ),
    );
    foreach ($this->getEnabledTypes() as $type) {
      $stat['source']['types'][$type] = 0;
      $stat['target']['types'][$type] = 0;
    }
    return $stat;
  }

  protected function getEnabledTypes() {
    // WTD: get the types enabled for lingotek translation
    return array('node');
  }

}
