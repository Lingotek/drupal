<?php

/**
 * @file
 * Lingotek batch functions
 */

use Drupal\lingotek\Lingotek;

/**
 * Wrapper function for running content uploads.
 */
function lingotek_operation_content_upload($entity_type, $entity_id, &$context) {
  if ($context) {
    $context['message'] = t('Uploading @entity_type @entity_id', array('@entity_type' => $entity_type, '@entity_id' => $entity_id));
  }

  /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
  $translation_service = \Drupal::service('lingotek.content_translation');
  // Prepare the entity's translatable content for upload.
  $entity = entity_load($entity_type, $entity_id);

  // Use upload with new entities.
  if (!$translation_service->getDocumentId($entity)) {
    $document_id = $translation_service->uploadDocument($entity);
    if ($document_id) {
      $context['results']['uploads'] = !empty($context['results']['uploads']) ? $context['results']['uploads'] + 1 : 1;
    }
    else {
      // ToDo: Log a problem happened uploading the document.
    }
  }
  
  // Use update with existing entities that have been edited. 
  elseif ($translation_service->hasEntityChanged($entity)) {
    if ($translation_service->updateDocument($entity)) {
      $context['results']['uploads'] = !empty($context['results']['uploads']) ? $context['results']['uploads'] + 1 : 1;
    }
    else {
      // ToDo: Log a problem happened updating the document.
    }
  }
}

function lingotek_operation_content_upload_finished($success, $results, $operations) {
  $count = !empty($results['uploads']) ? $results['uploads'] : 0;
  $num_documents = \Drupal::translation()->formatPlural($count, '1 document', '@count documents', array('@count' => $count));
  drupal_set_message(t('Uploaded @num_documents to Lingotek.', array('@num_documents' => $num_documents)));
}

/**
 * Wrapper function for running content downloads.
 */
function lingotek_operation_content_download($entity_type, $entity_id, $locale, &$context) {
  if ($context) {
    $context['message'] = t('Downloading translation "@locale" for @entity_type #@entity_id', array('@entity_type' => $entity_type, '@entity_id' => $entity_id, '@locale' => $locale));
  }
  /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
  $translation_service = \Drupal::service('lingotek.content_translation');
  // Prepare the entity's translatable content for upload.
  $entity = entity_load($entity_type, $entity_id);

  if ($translation_service->downloadDocument($entity, $locale)) {
    $context['results']['downloads'] = !empty($context['results']['downloads']) ? $context['results']['downloads'] + 1 : 1;
  }
  else {
    // ToDo: Log a problem happened downloading the document.
  }
}

function lingotek_operation_content_download_finished($success, $results, $operations) {
  $count = !empty($results['downloads']) ? $results['downloads'] : 0;
  $num_translations = \Drupal::translation()->formatPlural($count, '1 translation', '@count translations', array('@count' => $count));
  drupal_set_message(t('Downloaded @num_translations from Lingotek.', array('@num_translations' => $num_translations)));
}
