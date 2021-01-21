<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;

/**
 * @RelatedEntitiesDetector (
 *   id = "nested_cohesion_entity_reference_revisions_detector",
 *   title = @Translation("Get related cohesion entity reference revisions"),
 *   description = @translation("The default retrieval of nested cohesion entity reference revisions"),
 *   weight = 6,
 * )
 */
class NestedCohesionEntityReferenceRevisionsDetector extends EntityReferenceDetectorBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["cohesion_entity_reference_revisions"];

}
