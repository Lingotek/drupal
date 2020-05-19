<?php

namespace Drupal\lingotek\Controller;

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

}
