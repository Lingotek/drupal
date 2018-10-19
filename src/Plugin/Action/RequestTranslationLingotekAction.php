<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

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
    catch (LingotekApiException $exception) {
      drupal_set_message(t('The request for @entity_type %title translation failed. Please try again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@langcode' => $langcode,
          '%title' => $entity->label(),
        ]), 'error');
    }
    return $result;
  }

}
