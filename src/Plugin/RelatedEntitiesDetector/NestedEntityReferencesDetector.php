<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;

/**
 * @RelatedEntitiesDetector (
 *   id = "nested_entity_references",
 *   title = @Translation("Get related entity references"),
 *   description = @Translation("The default retrieval of nested entities"),
 *   weight = 5,
 * )
 */
class NestedEntityReferencesDetector extends EntityReferenceDetectorBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["entity_reference"];

}
