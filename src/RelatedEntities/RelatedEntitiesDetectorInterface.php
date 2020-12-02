<?php

namespace Drupal\lingotek\RelatedEntities;

use Drupal\Core\Entity\ContentEntityInterface;

interface RelatedEntitiesDetectorInterface {

  /**
   * Extract nested and related content.
   *
   * @param Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity node to parse for related entities.
   * @param array $entities
   *   Entities found.
   * @param array $related
   *   Related entities.
   * @param int $depth
   *   Recursion depth of entity node.
   * @param array $visited
   *   Array of visited field definitions.
   *
   * @return array
   *   An array of the nested content
   */
  public function extract(ContentEntityInterface &$entity, array &$entities, array &$related, $depth, array $visited);

}
