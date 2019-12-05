<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;

/**
 * Request Lingotek translation of a content entity for one language.
 *
 * @Action(
 *   id = "entity:lingotek_request_translation_action",
 *   action_label = @Translation("Request @entity_label translation to Lingotek for @language"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class RequestTranslationLingotekAction extends LingotekContentEntityConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];
    try {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
      $result = $this->translationService->addTarget($entity, $locale);
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
      $this->messenger()->addError(t('The request for @entity_type %title translation failed. Please try again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@langcode' => $langcode,
          '%title' => $entity->label(),
        ]));
    }
    return $result;
  }

}
