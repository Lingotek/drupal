<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\lingotek\Controller\LingotekControllerBase;
use Drupal\lingotek\LingotekLocale;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $cms_data = $this->getDashboardInfo();
    $string = '<h2>' . t('Dashboard') . '</h2><script>var cms_data = ' . json_encode($cms_data, JSON_PRETTY_PRINT) . '</script> <link rel="stylesheet" href="http://gmc.lingotek.com/v2/styles/ltk.css"> <script src="http://gmc.lingotek.com/v2/ltk.min.js"></script> <div ltk-dashboard ng-app="LingotekApp" style="margin-top: -15px;"></div>';
    $d8_css_hack = <<<EOD
  <style>
        body {
          width: auto !important;
        }
  </style>
EOD;
    $string .= $d8_css_hack;
    return array(
      '#type' => 'markup',
      '#markup' => SafeMarkup::set($string),
    );
  }

  public function endpoint(Request $request) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $request_method = $request->getMethod();

    $http_status_code = Response::HTTP_NOT_IMPLEMENTED;
    $response = array(
        'method' => $request_method,
    );
    switch ($request_method) {
      case 'POST':
        $lingotek_locale = $request->get('code');
        $native = $request->get('native');
        $language = $request->get('language');
        $direction = $request->get('direction');
        if (isset($language, $lingotek_locale, $direction)) {
          $rtl = ($direction == 'RTL') ? LanguageInterface::DIRECTION_RTL : LanguageInterface::DIRECTION_LTR;
          $langcode = LingotekLocale::generateLingotek2Drupal($lingotek_locale);
          $l = ConfigurableLanguage::create(array(
                      'id' => $langcode,
                      'label' => $language,
                      'native' => $native,
                      'direction' => $rtl,
          ));
          $l->save();
          $response += $this->getLanguageReport($langcode);
          $http_status_code = 200;
        }

        //TO-DO: (1) add language to CMS if not enabled X, (2) add language to TMS project
        break;

      case 'DELETE':
        $l = ConfigurableLanguage::load($langcode);
        $response['language'] = $l;
        $l->delete();
        $response['message'] = "Language removed: $langcode";
        $http_status_code = Response::HTTP_OK; // language successfully removed.
        break;

      case 'GET':
      default:
        $locale_code = $request->get('code'); //isset($request->get('code')) ? $_REQUEST['code'] : NULL;
        $details = $this->getLanguageDetails($locale_code);
        if (empty($details)) {
          $response['error'] = "language code not found.";
          return JsonResponse::create($response, Response::HTTP_NOT_FOUND);
        }
        $http_status_code = Response::HTTP_OK;
        $response = array_merge($response, $details);
        break;
    }

    return JsonResponse::create($response, $http_status_code);
  }

  private function getLanguageDetails($lingotek_locale_requested = NULL) {
    $response = array();
    $available_languages = \Drupal::languageManager()->getLanguages();
    $source_total = 0;
    $target_total = 0;
    $source_totals = array();
    $target_totals = array();

    // If we get a parameter, only return that language. Otherwise return all languages.
    foreach ($available_languages as $l) {
      $lingotek_locale = LingotekLocale::convertDrupal2Lingotek($l->getId());
      if (!is_null($lingotek_locale_requested) && $lingotek_locale_requested != $lingotek_locale)
        continue;
      $language_report = $this->getLanguageReport($l->getId());
      if ($lingotek_locale_requested == $lingotek_locale) {
        $response = $language_report;
      } else {
        $response[$lingotek_locale] = $language_report;
      }
      $source_total += $language_report['source']['total'];
      $target_total += $language_report['target']['total'];
      $source_totals = self::calcLanguageTotals($source_totals, $language_report['source']['types']);
      $target_totals = self::calcLanguageTotals($target_totals, $language_report['target']['types']);
    }
    if (is_null($lingotek_locale_requested)) {
      $response = array(
          'languages' => $response,
          'source' => array('types' => $source_totals, 'total' => $source_total),
          'target' => array('types' => $target_totals, 'total' => $target_total),
          'count' => count($available_languages),
      );
    }
    return $response;
  }

  protected function getDashboardInfo() {
    global $base_url, $base_root;
    return array(
        "community_id" => $this->L->get('default.community'),
        "external_id" => $this->L->get('account.login_id'),
        "vault_id" => $this->L->get('default.vault'),
        "workflow_id" => $this->L->get('default.workflow'),
        "project_id" => $this->L->get('default.project'),
        "first_name" => 'Drupal User',
        "last_name" => '',
        "email" => $this->L->get('account.login_id'),
        // cms
        "cms_site_id" => $base_url,
        "cms_site_key" => $base_url,
        "cms_site_name" => 'Drupal Site',
        "cms_type" => 'Drupal',
        "cms_version" => 'VERSION HERE',
        "cms_tag" => 'CMS TAG HERE',
        "locale" => "en_US", // FIX: should be currently selected locale
        "module_version" => '1.x',
        "endpoint_url" => $base_root . $this->url('lingotek.dashboard_endpoint'),
    );
  }

  protected function getLanguageReport($langcode, $active = 1, $enabled = 1) {
    $locale = LingotekLocale::convertDrupal2Lingotek($langcode);
    $types = $this->getEnabledTypes();

    $stat = array(
        'locale' => $locale,
        'xcode' => $langcode,
        'active' => 1,
        'enabled' => 1,
        'source' => array(
            'types' => $this->getSourceTypeCounts($langcode),
            'total' => 0,
        ),
        'target' => array(
            'types' => $this->getTargetTypeCounts($langcode),
            'total' => 0,
        ),
    );
    foreach ($types as $type) {
      $stat['source']['total'] += isset($stat['source']['types'][$type]) ? $stat['source']['types'][$type] : 0;
      $stat['target']['total'] += isset($stat['target']['types'][$type]) ? $stat['target']['types'][$type] : 0;
    }
    return $stat;
  }

  protected function getEnabledTypes() {
    // WTD: get the types enabled for lingotek translation
    return array('node', 'comment');
  }

  protected function getSourceTypeCounts($langcode, $types = NULL) {
    $types = is_null($types) ? $this->getEnabledTypes() : $types;
    $result = array();
    foreach ($types as $type) {
      $result[$type] = $this->getSourceTypeCount($langcode, $type);
    }
    return $result;
  }

  protected function getSourceTypeCount($langcode, $type) {
    $count = \Drupal::entityQuery($type)
            ->condition('langcode', $langcode)
            ->condition('default_langcode', 1)
            ->count()
            ->execute();
    return (int) $count;
  }

  protected function getTargetTypeCounts($langcode, $types = NULL) {
    $types = is_null($types) ? $this->getEnabledTypes() : $types;
    $result = array();
    foreach ($types as $type) {
      $result[$type] = $this->getTargetTypeCount($langcode, $type);
    }
    return $result;
  }

  protected function getTargetTypeCount($langcode, $type) {
    $count = \Drupal::entityQuery($type)
            ->condition('langcode', $langcode)
            ->condition('default_langcode', 0)
            ->count()
            ->execute();
    return (int) $count;
  }

  /**
   * Sums the values of the arrays be there keys (PHP 4, PHP 5)
   * array array_sum_values ( array array1 [, array array2 [, array ...]] )
   */
  private static function calcLanguageTotals() {
    $return = array();
    $intArgs = func_num_args();
    $arrArgs = func_get_args();
    if ($intArgs < 1) {
      trigger_error('Warning: Wrong parameter count for calcLanguageTotals()', E_USER_WARNING);
    }

    foreach ($arrArgs as $arrItem) {
      if (!is_array($arrItem)) {
        trigger_error('Warning: Wrong parameter values for calcLanguageTotals()', E_USER_WARNING);
      }
      foreach ($arrItem as $k => $v) {
        if (!key_exists($k, $return)) {
          $return[$k] = 0;
        }
        $return[$k] += $v;
      }
    }
    return $return;

    $sumArray = array();
    foreach ($myArray as $k => $subArray) {
      foreach ($subArray as $id => $value) {
        $sumArray[$id]+=$value;
      }
    }
    return $sumArray;
  }

}
