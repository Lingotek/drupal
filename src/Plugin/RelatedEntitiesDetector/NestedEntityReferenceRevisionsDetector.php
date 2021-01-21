<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;

/**
 * @RelatedEntitiesDetector (
 *   id = "nested_entity_reference_revisions_detector",
 *   title = @Translation("Get related entity reference revisions"),
 *   description = @translation("The default retrieval of nested entity reference revisions"),
 *   weight = 6,
 * )
 */
class NestedEntityReferenceRevisionsDetector extends EntityReferenceDetectorBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["entity_reference_revisions"];

}
