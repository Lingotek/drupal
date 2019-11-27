<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;

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
    catch (LingotekPaymentRequiredException $exception) {
      $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
    }
    catch (LingotekDocumentArchivedException $exception) {
      $this->messenger()->addError(t('Document @entity_type %title has been archived. Please upload again.', [
        '@entity_type' => $entity->getEntityTypeId(),
        '%title' => $entity->label(),
      ]));
    }
    catch (LingotekDocumentLockedException $exception) {
      $this->messenger()->addError(t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    catch (LingotekApiException $exception) {
      if ($alreadyUploaded) {
        $this->messenger()->addError(t('The update for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      else {
        $this->messenger()->addError(t('The upload for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    return $result;
  }

}
