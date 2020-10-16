<?php

namespace Drupal\lingotek_cohesion_test\Entity;

use Drupal\cohesion_elements\Entity\CohesionLayout;

/**
 * CohesionLayout class override for rendering values for tests.
 *
 * @package Drupal\lingotek_cohesion_test\Entity
 */
class TestCohesionLayout extends CohesionLayout {

  /**
   * {@inheritdoc}
   */
  public function getTwig($theme = 'current') {
    // Just render the contents, we don't care about formatting.
    return 'JSON: ' . $this->getJsonValues() . PHP_EOL .
      'ID: ' . $this->id() . PHP_EOL .
      'LANGCODE: ' . $this->language()->getId() . PHP_EOL;
  }

}
