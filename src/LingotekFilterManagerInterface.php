<?php

namespace Drupal\lingotek;

/**
 * Service for managing Lingotek Filters.
 *
 * @package Drupal\lingotek
 */
interface LingotekFilterManagerInterface {

  /**
   * Get all the locally available filters.
   *
   * @return array
   *   Keyed array with filters (id => label)
   */
  public function getLocallyAvailableFilters();

  /**
   * Get the default filter ID.
   *
   * @return string
   *   ID of the default filter.
   */
  public function getDefaultFilter();

  /**
   * Get the default subfilter ID.
   *
   * @return string
   *   ID of the default subfilter.
   */
  public function getDefaultSubfilter();

  /**
   * Get the default filter label.
   *
   * @return string
   *   ID of the default filter.
   */
  public function getDefaultFilterLabel();

  /**
   * Get the default filter label.
   *
   * @return string
   *   ID of the default filter.
   */
  public function getDefaultSubfilterLabel();

  /**
   * Gets the filter that should be applied.
   *
   * Given filter provider can take precedence, or the settings default will be
   * applied.
   *
   * @param \Drupal\lingotek\LingotekFilterProviderInterface|null $profile
   *   A filter provider.
   *
   * @return string
   *   The filter ID.
   */
  public function getFilterId(LingotekFilterProviderInterface $profile = NULL);

  /**
   * Gets the subfilter that should be applied.
   *
   * Given filter provider can take precedence, or the settings default will be
   * applied.
   *
   * @param \Drupal\lingotek\LingotekFilterProviderInterface|null $profile
   *   A filter provider.
   *
   * @return string
   *   The filter ID.
   */
  public function getSubfilterId(LingotekFilterProviderInterface $profile = NULL);

}
