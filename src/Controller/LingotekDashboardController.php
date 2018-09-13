<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekLocale;
use Drupal\Core\Language\LanguageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekDashboardController extends LingotekControllerBase {

  /**
   * The entity query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;


  /**
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotek_configuration;

  /**
   * Constructs a LingotekControllerBase object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The lingotek service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(Request $request, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, QueryFactory $query_factory, LanguageManagerInterface $language_manager, LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, FormBuilderInterface $form_builder, LoggerInterface $logger) {
    parent::__construct($request, $config_factory, $lingotek, $language_locale_mapper, $form_builder, $logger);
    $this->queryFactory = $query_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->lingotek_configuration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity.query'),
      $container->get('language_manager'),
      $container->get('lingotek'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('lingotek.configuration'),
      $container->get('form_builder'),
      $container->get('logger.channel.lingotek')
    );
  }

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
    $build = [];
    $this->moduleHandler()->loadInclude('locale', 'install');
    $requirements = locale_requirements('runtime');
    $build['#attached']['library'][] = 'lingotek/lingotek.dashboard';
    $build['#attached']['drupalSettings']['lingotek']['cms_data'] = $cms_data;
    $build['#title'] = $this->t('Dashboard');
    $build['ltk-dashboard'] = [
      '#type' => 'container',
      '#attributes' => [
        'ltk-dashboard' => '',
        'ng-app' => 'LingotekApp',
        'style' => 'margin-top: -15px;',
      ],
    ];
    if (isset($requirements['locale_translation']) && isset($requirements['locale_translation']['description'])) {
      drupal_set_message($requirements['locale_translation']['description'], 'warning');
    }
    return $build;
  }

  public function endpoint(Request $request) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $request_method = $request->getMethod();

    $http_status_code = Response::HTTP_NOT_IMPLEMENTED;
    $response = [
        'method' => $request_method,
    ];
    switch ($request_method) {
      case 'POST':
        $languageStorage = $this->entityTypeManager->getStorage('configurable_language');
        $lingotek_locale = $request->get('code');
        $native = $request->get('native');
        $language = $request->get('language');
        $direction = $request->get('direction');
        if (isset($language, $lingotek_locale, $direction)) {
          // First, we try if there is a disabled language with that locale.
          $existingLanguage = $this->queryFactory->get('configurable_language')
            ->condition('third_party_settings.lingotek.disabled', TRUE)
            ->condition('third_party_settings.lingotek.locale', $lingotek_locale)
            ->execute();
          if (!$existingLanguage) {
            // If we didn't find it, maybe the language was the default
            // locale, and it didn't have a locale stored.
            $existingLanguage = $this->queryFactory->get('configurable_language')
              ->condition('third_party_settings.lingotek.disabled', TRUE)
              ->condition('id', LingotekLocale::convertLingotek2Drupal($lingotek_locale, FALSE))
              ->execute();
          }
          if ($existingLanguage) {
            $language = $languageStorage->load(reset($existingLanguage));
          }
          else {
            $rtl = ($direction == 'RTL') ? LanguageInterface::DIRECTION_RTL : LanguageInterface::DIRECTION_LTR;
            $langcode = LingotekLocale::generateLingotek2Drupal($lingotek_locale);
            $language = $languageStorage->create([
              'id' => $langcode,
              'label' => $language,
              'native' => $native,
              'direction' => $rtl,
            ]);
          }
          $language->setThirdPartySetting('lingotek', 'disabled', FALSE);
          $language->setThirdPartySetting('lingotek', 'locale', $lingotek_locale);
          $language->save();
          $response += $this->getLanguageReport($language);
          $http_status_code = 200;
        }

        // TO-DO: (1) add language to CMS if not enabled X, (2) add language to TMS project
        break;

      case 'DELETE':
        $content = $request->getContent();
        $parsed_content = [];
        parse_str($content, $parsed_content);
        $locale = $parsed_content['code'];
        $language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
        $response['language'] = $language->id();
        $this->lingotek_configuration->disableLanguage($language);
        $this->languageManager()->reset();
        $response['message'] = "Language disabled: $locale";
        $http_status_code = Response::HTTP_OK;
        break;

      case 'GET':
      default:
        // isset($request->get('code')) ? $_REQUEST['code'] : NULL;
        $locale_code = $request->get('code');
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
    $response = [];
    $available_languages = $this->languageManager->getLanguages();
    $source_total = 0;
    $target_total = 0;
    $source_totals = [];
    $target_totals = [];

    // If we get a parameter, only return that language. Otherwise return all languages.
    foreach ($available_languages as $language) {
      // We check if we have a saved lingotek locale.
      // If not, we default to the id conversion.
      // Language manager returns Language objects, not ConfigurableLanguage,
      // because the language manager is initiated before the config system, and
      // loads the configuration bypassing it.
      $lingotek_locale = $this->languageLocaleMapper->getLocaleForLangcode($language->getId());

      if (!is_null($lingotek_locale_requested) && $lingotek_locale_requested != $lingotek_locale) {
        continue;
      }
      $language_report = $this->getLanguageReport($language);
      if ($lingotek_locale_requested == $lingotek_locale) {
        $response = $language_report;
      }
      else {
        $response[$lingotek_locale] = $language_report;
      }
      $source_total += $language_report['source']['total'];
      $target_total += $language_report['target']['total'];
      $source_totals = self::calcLanguageTotals($source_totals, $language_report['source']['types']);
      $target_totals = self::calcLanguageTotals($target_totals, $language_report['target']['types']);
    }
    if (is_null($lingotek_locale_requested)) {
      $response = [
          'languages' => $response,
          'source' => ['types' => $source_totals, 'total' => $source_total],
          'target' => ['types' => $target_totals, 'total' => $target_total],
          'count' => count($available_languages),
      ];
    }
    return $response;
  }

  protected function getDashboardInfo() {
    global $base_url, $base_root;
    return [
      "community_id" => $this->lingotek->get('default.community'),
      "external_id" => $this->lingotek->get('account.login_id'),
      "vault_id" => $this->lingotek->get('default.vault'),
      "workflow_id" => $this->lingotek->get('default.workflow'),
      "project_id" => $this->lingotek->get('default.project'),
      "first_name" => 'Drupal User',
      "last_name" => '',
      "email" => $this->lingotek->get('account.login_id'),
      // CMS data that will be used for building the dashboard with JS.
      "cms_site_id" => $base_url,
      "cms_site_key" => $base_url,
      "cms_site_name" => 'Drupal Site',
      "cms_type" => 'Drupal',
      "cms_version" => 'VERSION HERE',
      "cms_tag" => 'CMS TAG HERE',
      // FIX: should be the currently selected locale
      "locale" => "en_US",
      "module_version" => '1.x',
      "endpoint_url" => Url::fromRoute('lingotek.dashboard_endpoint')->toString(),
    ];
  }

  protected function getLanguageReport(LanguageInterface $language, $active = 1, $enabled = 1) {
    $langcode = $language->getId();
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $configLanguage = $this->entityTypeManager->getStorage('configurable_language')->load($langcode);
    $types = $this->getEnabledTypes();

    $stat = [
        'locale' => $locale,
        'xcode' => $langcode,
        'active' => $this->lingotek_configuration->isLanguageEnabled($configLanguage) ? 1 : 0,
        'enabled' => 1,
        'source' => [
            'types' => $this->getSourceTypeCounts($langcode),
            'total' => 0,
        ],
        'target' => [
            'types' => $this->getTargetTypeCounts($langcode),
            'total' => 0,
        ],
    ];
    foreach ($types as $type) {
      $stat['source']['total'] += isset($stat['source']['types'][$type]) ? $stat['source']['types'][$type] : 0;
      $stat['target']['total'] += isset($stat['target']['types'][$type]) ? $stat['target']['types'][$type] : 0;
    }
    return $stat;
  }

  /**
   * Gets the entity type ids of entities to be translated with Lingotek.
   *
   * @return array The entity type names of content entities enabled.
   */
  protected function getEnabledTypes() {
    $types = $this->lingotek_configuration->getEnabledEntityTypes();
    return empty($types) ? $types : array_keys($types);
  }

  protected function getSourceTypeCounts($langcode, $types = NULL) {
    $types = is_null($types) ? $this->getEnabledTypes() : $types;
    $result = [];
    foreach ($types as $type) {
      $result[$type] = $this->getSourceTypeCount($langcode, $type);
    }
    return $result;
  }

  protected function getSourceTypeCount($langcode, $type) {
    $count = $this->queryFactory->get($type)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->count()
      ->execute();
    return (int) $count;
  }

  protected function getTargetTypeCounts($langcode, $types = NULL) {
    $types = is_null($types) ? $this->getEnabledTypes() : $types;
    $result = [];
    foreach ($types as $type) {
      $result[$type] = $this->getTargetTypeCount($langcode, $type);
    }
    return $result;
  }

  protected function getTargetTypeCount($langcode, $type) {
    $count = $this->queryFactory->get($type)
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
    $return = [];
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
        if (!array_key_exists($k, $return)) {
          $return[$k] = 0;
        }
        $return[$k] += $v;
      }
    }
    return $return;

    $sumArray = [];
    foreach ($myArray as $k => $subArray) {
      foreach ($subArray as $id => $value) {
        $sumArray[$id] += $value;
      }
    }
    return $sumArray;
  }

}
