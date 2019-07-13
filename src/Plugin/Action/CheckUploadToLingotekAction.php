<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Assigns ownership of a node to a user.
 *
 * @Action(
 *   id = "entity:lingotek_check_upload_action",
 *   action_label = @Translation("Check @entity_label upload status to Lingotek"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class CheckUploadToLingotekAction extends LingotekContentEntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    try {
      /** @var \Drupal\node\NodeInterface $entity */
      $result = $this->translationService->checkSourceStatus($entity);
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The upload status check for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    return $result;
  }

}
