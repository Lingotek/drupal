<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;

/**
 * @RelatedEntitiesDetector (
 *   id = "nested_er_viewmode_entities",
 *   title = @Translation("Get related ER viewmode entities"),
 *   description = @translation("The default retrieval of nested ER viewmode entities"),
 *   weight = 7,
 * )
 */
class NestedErViewmodeEntitiesDetector extends EntityReferenceDetectorBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["er_viewmode"];

}
