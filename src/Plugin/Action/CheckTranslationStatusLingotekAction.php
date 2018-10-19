<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Check Lingotek translation status of a content entity for one language.
 *
 * @Action(
 *   id = "entity:lingotek_check_translation_action",
 *   action_label = @Translation("Check @entity_label translation status to Lingotek for @language"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class CheckTranslationStatusLingotekAction extends LingotekContentEntityConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];
    try {
      $result = $this->translationService->checkTargetStatus($entity, $langcode);
    }
    catch (LingotekApiException $exception) {
      drupal_set_message(t('The request for @entity_type %title translation status failed. Please try again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@langcode' => $langcode,
          '%title' => $entity->label(),
        ]), 'error');
    }
    return $result;
  }

}
