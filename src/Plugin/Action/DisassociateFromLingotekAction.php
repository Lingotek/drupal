<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Assigns ownership of a node to a user.
 *
 * @Action(
 *   id = "entity:lingotek_disassociate_action",
 *   action_label = @Translation("Disassociate @entity_label from Lingotek"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 * @deprecated in lingotek:3.1.0 and is removed from lingotek:4.0.0.
 * @see \Drupal\lingotek\Plugin\Action\CancelLingotekAction
 */
class DisassociateFromLingotekAction extends LingotekContentEntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    try {
      /** @var \Drupal\node\NodeInterface $entity */
      $result = $this->translationService->cancelDocument($entity);
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The cancellation of @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    return $result;
  }

}
