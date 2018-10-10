<?php

namespace Drupal\lingotek;

/**
 * Contract for classes providing filter information.
 *
 * @package Drupal\lingotek
 */
interface LingotekFilterProviderInterface {

  /**
   * Gets the FPRM filter of the profile.
   *
   * @return string
   *   The fprm filter identifier, used to upload documents. If the value is
   *   'drupal_default', the default site FRPM filter should be used.
   */
  public function getFilter();

  /**
   * Sets the FPRM filter of the profile.
   *
   * @param string $filter
   *   The FPRM filter identifier, used to upload documents. If the value is
   *   'drupal_default', the default site FPRM filter should be used.
   *
   * @return $this
   */
  public function setFilter($filter);

  /**
   * Gets the FPRM subfilter of the profile.
   *
   * @return string
   *   The FPRM filter identifier, used to upload documents. If the value is
   *   'drupal_default', the default site FPRM subfilter should be used.
   */
  public function getSubfilter();

  /**
   * Sets the FPRM subfilter of the profile.
   *
   * @param string $filter
   *   The FPRM filter identifier, used to upload documents. If the value is
   *   'drupal_default', the default site FPRM subfilter should be used.
   *
   * @return $this
   */
  public function setSubfilter($filter);

}
