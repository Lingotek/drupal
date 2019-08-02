<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Request Lingotek translation of a content entity for one language.
 *
 * @Action(
 *   id = "entity:lingotek_cancel_translation_action",
 *   action_label = @Translation("Cancel @entity_label translation in Lingotek for @language"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class CancelTranslationLingotekAction extends LingotekContentEntityConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];
    try {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
      $result = $this->translationService->cancelDocumentTarget($entity, $locale);
    }
    catch (LingotekApiException $exception) {
      $this->messenger()
        ->addError(t('The cancellation of @entity_type %title translation to @language failed. Please try again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '%title' => $entity->label(),
          '@language' => $langcode,
        ]));
    }
    return $result;
  }

}
