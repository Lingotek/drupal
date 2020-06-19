<?php

namespace Drupal\lingotek;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service for managing Lingotek Filters.
 *
 * @package Drupal\lingotek
 */
class LingotekFilterManager implements LingotekFilterManagerInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new LingotekFilterManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocallyAvailableFilters() {
    $filters = $this->configFactory->get('lingotek.settings')->get('account.resources.filter');
    $filters['project_default'] = 'Project Default';
    $filters['drupal_default'] = 'Drupal Default';
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFilter() {
    $filter = $this->configFactory->get('lingotek.settings')->get('default.filter');
    $filters = $this->getLocallyAvailableFilters();
    if (!isset($filters[$filter])) {
      $filter = NULL;
    }
    return $filter;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubfilter() {
    $filter = $this->configFactory->get('lingotek.settings')->get('default.subfilter');
    $filters = $this->getLocallyAvailableFilters();
    if (!isset($filters[$filter])) {
      $filter = NULL;
    }
    return $filter;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFilterLabel() {
    $label = '';
    $filter = $this->getDefaultFilter();
    $filters = $this->getLocallyAvailableFilters();
    if (isset($filters[$filter])) {
      $label = $filters[$filter];
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubfilterLabel() {
    $label = '';
    $filter = $this->getDefaultSubfilter();
    $filters = $this->getLocallyAvailableFilters();
    if (isset($filters[$filter])) {
      $label = $filters[$filter];
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterId(LingotekFilterProviderInterface $profile = NULL) {
    $defaults = new LingotekDefaultFilterProvider();
    $defaultFilter = $defaults->getFilter();
    $filter = NULL;
    $settingsFilter = $this->getDefaultFilter();
    if ($profile !== NULL && $profileFilter = $profile->getFilter()) {
      switch ($profileFilter) {
        case 'project_default':
          $filter = NULL;
          break;

        case 'drupal_default':
          $filter = $defaultFilter;
          break;

        case 'default':
          $filter = $this->chooseAppropriateFilterID($settingsFilter, $defaultFilter);
          break;

        default:
          $filter = $profileFilter;
          break;
      }
    }
    else {
      $filter = $this->chooseAppropriateFilterID($settingsFilter, $defaultFilter);
    }
    return $filter;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubfilterId(LingotekFilterProviderInterface $profile = NULL) {
    $defaults = new LingotekDefaultFilterProvider();
    $defaultFilter = $defaults->getSubfilter();
    $filter = NULL;
    $settingsFilter = $this->getDefaultSubfilter();
    if ($profile !== NULL && $profileFilter = $profile->getSubfilter()) {
      switch ($profileFilter) {
        case 'project_default':
          $filter = NULL;
          break;

        case 'drupal_default':
          $filter = $defaultFilter;
          break;

        case 'default':
          $filter = $this->chooseAppropriateFilterID($settingsFilter, $defaultFilter);
          break;

        default:
          $filter = $profileFilter;
          break;
      }
    }
    else {
      $filter = $this->chooseAppropriateFilterID($settingsFilter, $defaultFilter);
    }
    return $filter;
  }

  /**
   * Helper used to choose the appropriate filter ID based on the one listed in settings.
   *
   * @param string $settingsFilter
   *   Either 'project_default', 'drupal_default' or the filter ID.
   * @param string $drupalDefaultFilterID
   *   The Drupal default filter ID.
   *
   * @return string|null
   *   The appropriate filter ID or NULL if project default is to be used.
   */
  protected function chooseAppropriateFilterID($settingsFilter, $drupalDefaultFilterID) {
    $filter = $settingsFilter;
    switch ($settingsFilter) {
      case 'project_default':
        $filter = NULL;
        break;

      case 'drupal_default':
        $filter = $drupalDefaultFilterID;
        break;
    }
    return $filter;
  }

}
