<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Download Lingotek translation of a content entity for one language.
 *
 * @Action(
 *   id = "entity:lingotek_download_translation_action",
 *   action_label = @Translation("Download @entity_label translation to Lingotek for @language"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class DownloadTranslationLingotekAction extends LingotekContentEntityConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];
    try {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
      $result = $this->translationService->downloadDocument($entity, $locale);
    }
    catch (LingotekApiException $exception) {
      drupal_set_message(t('The download for @entity_type %title translation failed. Please try again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@langcode' => $langcode,
          '%title' => $entity->label(),
        ]), 'error');
    }
    return $result;
  }

}
