<?php

namespace Drupal\lingotek;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Resolver of the entity revision from which we need to extract the data from.
 */
interface LingotekContentTranslationEntityRevisionResolverInterface {

  /**
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to extract data from.
   * @param string $mode
   *   The mode to use for resolving the revision.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity resolved source revision.
   */
  public function resolve(ContentEntityInterface $entity, string $mode);

}
