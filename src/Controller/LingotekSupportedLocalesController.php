<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class LingotekSupportedLocalesController extends LingotekControllerBase {

  public function content() {
    $locales = $this->lingotek->getLocalesInfo();
    ksort($locales);

    $build = [];
    $build['#attached']['library'][] = 'lingotek/lingotek.locales_listing';

    $build['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['table-filter', 'js-show'],
      ],
    ];

    $build['filters']['text'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter'),
      '#title_display' => 'invisible',
      '#size' => 60,
      '#placeholder' => $this->t('Filter per language, locale, or country'),
      '#attributes' => [
        'class' => ['locales-filter-text'],
        'data-table' => '.locales-listing-table',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a code or name of any language, locale, or country to filter by.'),
      ],
    ];

    $build['locales'] = [
      '#type' => 'table',
      '#header' => [
        'code' => $this->t('Code'),
        'language_code' => $this->t('Language Code'),
        'title' => $this->t('Title'),
        'language' => $this->t('Language'),
        'country_code' => $this->t('Country Code'),
        'country' => $this->t('Country'),
      ],
      '#rows' => $locales,
      '#attributes' => ['class' => ['locales-listing-table']],
    ];

    return $build;
  }

  /**
   * Callback for the autocomplete of supported locales.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function autocomplete(Request $request) {
    // Get autocomplete query.
    $q = $request->query->get('q') ?: '';
    if ($q == '') {
      return new JsonResponse([]);
    }

    $locales = $this->lingotek->getLocalesInfo();
    ksort($locales);
    $matches = [];
    foreach ($locales as $locale => $locale_info) {
      $fieldsToSearch = [
        'code' => $this->t('Code'),
        'language_code' => $this->t('Language Code'),
        // 'title' => $this->t('Title'),
        // 'language' => $this->t('Language'),
        'country_code' => $this->t('Country Code'),
        // 'country' => $this->t('Country'),
      ];
      foreach ($fieldsToSearch as $field => $fieldDescription) {
        if (stripos($locale_info[$field], $q) !== FALSE) {
          $matches[] = [
            'value' => $locale,
            'label' => new FormattableMarkup('@title (@code) [matched: @match: %value]', [
              '@title' => Html::escape($locale_info['title']),
              '@code' => Html::escape($locale_info['code']),
              '@match' => Html::escape($fieldDescription),
              '%value' => Html::escape($locale_info[$field]),
            ]),
          ];
          continue 2;
        }
      }
    }
    return new JsonResponse($matches);
  }

}
