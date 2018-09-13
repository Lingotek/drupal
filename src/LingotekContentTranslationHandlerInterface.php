<?php

namespace Drupal\lingotek;

/**
 * Interface for providing Lingotek content translation.
 *
 * Defines a set of methods to allow any entity to be processed by the entity
 * translation UI.
 */
interface LingotekContentTranslationHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions();

}
