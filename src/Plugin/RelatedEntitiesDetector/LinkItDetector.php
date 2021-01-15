<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

/**
 * @RelatedEntitiesDetector (
 *   id = "linkit_detector",
 *   title = @Translation("Get editor linked entities with LinkIt module"),
 *   description = @translation("Get editor linked entities with LinkIt module"),
 *   weight = 7,
 * )
 */
class LinkItDetector extends EditorDetectorBase {

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["text", "text_long", "text_with_summary"];

  /**
   * {@inheritdoc}
   */
  protected $xpath = "//a[@data-entity-type and @data-entity-uuid]";

}
