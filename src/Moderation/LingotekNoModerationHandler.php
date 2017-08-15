<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Moderation handler when no other integration applies.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekNoModerationHandler implements LingotekModerationHandlerInterface {

  use LingotekNoModerationCheckTrait;

  /**
   * {@inheritdoc}
   */
  public function shouldModerationPreventUpload(EntityInterface $entity) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function performModerationTransitionIfNeeded(ContentEntityInterface &$entity) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationState(ContentEntityInterface $entity) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function setModerationState(ContentEntityInterface $entity, $state) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function isModerationEnabled(EntityInterface $entity) {
    return FALSE;
  }

}
