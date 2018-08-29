<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Assigns ownership of a node to a user.
 *
 * @Action(
 *   id = "entity:lingotek_upload_action",
 *   action_label = @Translation("Upload @entity_label to Lingotek"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class UploadToLingotekAction extends LingotekContentEntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    $alreadyUploaded = $this->translationService->getDocumentId($entity);
    try {
      /** @var \Drupal\node\NodeInterface $entity */
      $result = $this->translationService->uploadDocument($entity);
    }
    catch (LingotekApiException $exception) {
      if ($alreadyUploaded) {
        drupal_set_message(t('The update for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
      else {
        drupal_set_message(t('The upload for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
    }
    return $result;
  }

}
