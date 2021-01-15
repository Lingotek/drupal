<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

/**
 * @RelatedEntitiesDetector (
 *   id = "entity_embed_detector",
 *   title = @Translation("Get editor embedded entities with entity_embed module"),
 *   description = @translation("Get editor embedded entities with entity_embed module"),
 *   weight = 7,
 * )
 */
class EntityEmbedDetector extends EditorDetectorBase {

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["text", "text_long", "text_with_summary"];

  /**
   * {@inheritdoc}
   */
  protected $xpath = "//drupal-entity[@data-entity-type and @data-entity-uuid]";

}
