<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Moderation handler managing the Lingotek integration.
 *
 * @package Drupal\lingotek\Moderation
 */
interface LingotekModerationHandlerInterface extends LingotekModerationServiceInterface {

  /**
   * Checks if we should prevent upload based on content moderation settings.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity.
   *
   * @return bool
   *   Returns TRUE if we should prevent the upload based on content moderation.
   */
  public function shouldModerationPreventUpload(EntityInterface $entity);

  /**
   * Performs a moderation transition if needed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   */
  public function performModerationTransitionIfNeeded(ContentEntityInterface &$entity);

  /**
   * Gets the moderation state ID.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return string
   *   The moderation state ID.
   */
  public function getModerationState(ContentEntityInterface $entity);

  /**
   * Sets the moderation state ID.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $state
   *   The moderation state ID.
   */
  public function setModerationState(ContentEntityInterface $entity, $state);

  /**
   * Checks if the moderation is enabled for this entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity.
   *
   * @return bool
   *   If moderation is enabled, returns TRUE. Returns FALSE otherwise.
   */
  public function isModerationEnabled(EntityInterface $entity);

}
