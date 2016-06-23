<?php

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * @defgroup lingotek_api Entity API
 * @{
 * TBD
 * @}
 */


/**
 * @addtogroup hooks
 * @{
 */

/**
 * Act on an translation of an entity before it is saved or updated after being
 * downloaded from Lingotek.
 *
 * @param \Drupal\Core\Entity\ContentEntityInterface &$translation
 *   The entity that is going to be saved.
 * @param string $langcode
 *   Drupal language code that has been downloaded.
 * @param array $data
 *   Data returned from the Lingotek service when asking for the translation.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_content_entity_translation_presave(ContentEntityInterface &$translation, $langcode, $data) {
  // In this example, we avoid press releases to be published when downloaded.
  if ($translation->getEntityTypeId() === 'node' && $translation->bundle() === 'press_release') {
    if ($translation->isNewTranslation()) {
      /** @var \Drupal\node\NodeInterface $translation */
      $translation->setPublished(FALSE);
    }
  }
}

/**
 * Act on the data extracted from an an entity before it is uploaded to Lingotek.
 *
 * @param array &$source_data
 *   The data that will be uploaded, as an associative array.
 * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
 *   The entity where the data is extracted from and will be associated to the Lingotek document.
 * @param string &$url
 *   The url which will be associated to this document, e.g. for context review.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_content_entity_document_upload(array &$source_data, ContentEntityInterface &$entity, &$url) {
  // In this example, press releases pages are always displayed in a view, so we
  // want to send a different url for in context review.
  if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'press_release') {
    $url = \Drupal::request()->getBasePath() . '/press-release/view-path/' . $entity->id();
  }

}
