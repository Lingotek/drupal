<?php

namespace Drupal\lingotek;

/**
 * Defines service for setting content configuration in a multiple way.
 */
interface LingotekMultipleContentConfigurationServiceInterface {

  /**
   * Sets Lingotek translatability settings of multiple content.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types that are enabled for Lingotek content translation.
   */
  public function setContentTranslationSettings(array $contentData);

}
