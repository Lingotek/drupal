<?php

namespace Drupal\lingotek;

/**
 * Filter provider returning the default filters.
 *
 * @package Drupal\lingotek
 */
class LingotekDefaultFilterProvider implements LingotekFilterProviderInterface {

  /**
   * Filter 'okf_json@with-html-subfilter.fprm'.
   */
  const FPRM_ID = '4f91482b-5aa1-4a4a-a43f-712af7b39625';

  /**
   * Filter 'okf_html@drupal8-subfilter.fprm'.
   */
  const FPRM_SUBFILTER_ID = '0e79f34d-f27b-4a0c-880e-cd9181a5d265';

  /**
   * {@inheritdoc}
   */
  public function getFilter() {
    return static::FPRM_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilter($filter) {
    // Do nothing. Filter is static.
  }

  /**
   * {@inheritdoc}
   */
  public function getSubfilter() {
    return static::FPRM_SUBFILTER_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubfilter($filter) {
    // Do nothing. Filter is static.
  }

}
