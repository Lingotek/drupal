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
    $filter = $defaultFilter;
    $settingsFilter = $this->getDefaultFilter();
    if ($profile !== NULL && $profileFilter = $profile->getFilter()) {
      if ($profileFilter !== 'project_default' && $settingsFilter !== 'project_default') {
        if ($profileFilter === 'default') {
          $filter = $settingsFilter;
        }
        else {
          $filter = $profileFilter;
        }
      }
    }
    return $filter;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubfilterId(LingotekFilterProviderInterface $profile = NULL) {
    $defaults = new LingotekDefaultFilterProvider();
    $defaultFilter = $defaults->getSubfilter();
    $filter = $defaultFilter;
    $settingsFilter = $this->getDefaultSubfilter();
    if ($profile !== NULL && $profileFilter = $profile->getSubfilter()) {
      if ($profileFilter !== 'project_default' && $settingsFilter !== 'project_default') {
        if ($profileFilter === 'default') {
          $filter = $settingsFilter;
        }
        else {
          $filter = $profileFilter;
        }
      }
    }
    return $filter;
  }

}
