<?php

/**
 * @file
 * Lingotek batch functions
 */

use Drupal\lingotek\Lingotek;
use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Wrapper function for running content uploads.
 */
function lingotek_operation_content_upload($entity_type_id, $entity_id, &$context) {
  if ($context) {
    $context['message'] = t('Uploading @entity_type @entity_id', ['@entity_type' => $entity_type_id, '@entity_id' => $entity_id]);
  }

  /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
  $translation_service = \Drupal::service('lingotek.content_translation');

  /** @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
  $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
  // Prepare the entity's translatable content for upload.
  $entity = $entity_storage->load($entity_id);

  // Use upload with new entities.
  if (!$translation_service->getDocumentId($entity)) {
    $document_id = NULL;
    try {
      $document_id = $translation_service->uploadDocument($entity);
    }
    catch (LingotekApiException $exception) {
      // Mark the document as failed.
      $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
    }
    if ($document_id) {
      $context['results']['uploads'] = !empty($context['results']['uploads']) ? $context['results']['uploads'] + 1 : 1;
    }
    else {
      // Mark the document as failed.
      $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      $context['results']['error'][] = t('The upload for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]);
    }
  }

  // Use update with existing entities that have been edited.
  else {
    try {
      if ($translation_service->updateDocument($entity)) {
        $context['results']['uploads'] = !empty($context['results']['uploads']) ? $context['results']['uploads'] + 1 : 1;
      }
      else {
        // ToDo: Log a problem happened updating the document.
      }
    }
    catch (LingotekApiException $exception) {
      // Mark the document as failed.
      $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      $context['results']['error'][] = t('The update for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]);
    }
  }
}

function lingotek_operation_content_upload_finished($success, $results, $operations) {
  if (isset($results['error'])) {
    $error_message = [
      '#type' => 'inline_template',
      '#template' => 'Some operations failed: {{ errors }}',
      '#context' => [
        'errors' => [
          '#theme' => 'item_list',
          '#items' => $results['error'],
        ],
      ],
    ];
    \Drupal::messenger()->addError(\Drupal::service('renderer')->renderPlain($error_message));
  }
  $count = !empty($results['uploads']) ? $results['uploads'] : 0;
  $message = \Drupal::translation()->formatPlural($count, 'Uploaded 1 document to Lingotek.', 'Uploaded @num documents to Lingotek.', ['@num' => $count]);
  if (isset($results['error'])) {
    \Drupal::messenger()->addWarning($message);
  }
  else {
    \Drupal::messenger()->addStatus($message);
  }
}

/**
 * Wrapper function for running content downloads.
 */
function lingotek_operation_content_download($entity_type_id, $entity_id, $locale, &$context) {
  if ($context) {
    $context['message'] = t('Downloading translation "@locale" for @entity_type #@entity_id', ['@entity_type' => $entity_type_id, '@entity_id' => $entity_id, '@locale' => $locale]);
  }
  /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
  $translation_service = \Drupal::service('lingotek.content_translation');

  /** @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
  $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
  // Prepare the entity's translatable content for upload.
  $entity = $entity_storage->load($entity_id);

  try {
    if ($translation_service->downloadDocument($entity, $locale)) {
      $context['results']['downloads'] = !empty($context['results']['downloads']) ? $context['results']['downloads'] + 1 : 1;
    }
    else {
      $context['results']['error'][] = t('The download for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]);
    }
  }
  catch (LingotekApiException $exception) {
    $context['results']['error'][] = t('The download for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]);
  }
}

function lingotek_operation_content_download_finished($success, $results, $operations) {
  if (isset($results['error'])) {
    $error_message = [
      '#type' => 'inline_template',
      '#template' => 'Some operations failed: {{ errors }}',
      '#context' => [
        'errors' => [
          '#theme' => 'item_list',
          '#items' => $results['error'],
        ],
      ],
    ];
    \Drupal::messenger()->addError(\Drupal::service('renderer')->renderPlain($error_message));
  }
  $count = !empty($results['downloads']) ? $results['downloads'] : 0;
  $message = \Drupal::translation()->formatPlural($count, 'Downloaded a document from Lingotek.', 'Downloaded @num documents from Lingotek.', ['@num' => $count]);
  if (isset($results['error'])) {
    \Drupal::messenger()->addWarning($message);
  }
  else {
    \Drupal::messenger()->addStatus($message);
  }

}
