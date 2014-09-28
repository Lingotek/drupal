<?php

/**
 * @file
 * Lingotek batch functions
 */

use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekTranslatableEntity;

/**
 * Wrapper function for running content uploads.
 */
function lingotek_operation_content_upload($entity_type, $entity_id, &$context) {
  if ($context) {
    $context['message'] = t('Uploading @entity_type #@entity_id', array('@entity_type' => $entity_type, '@entity_id' => $entity_id));
  }

  // Prepare the entity's translatable content for upload.
  $container = \Drupal::getContainer();
  $entity = entity_load($entity_type, $entity_id);
  $te = LingotekTranslatableEntity::load($container, $entity);
  $source_data = json_encode($te->getSourceData());

  $L = $container->get('lingotek');
  $doc_id = $L->uploadDocument($entity->bundle() . ' (' . $entity->getEntityTypeId() . '): ' . $entity->label(), $source_data, 'en_US');
  if ($doc_id) {
    $te->setDocId($doc_id);
    $te->setSourceStatus(Lingotek::STATUS_PENDING);
    $context['results']['uploads'] = !empty($context['results']['uploads']) ? $context['results']['uploads'] + 1 : 1;
  }
}

function lingotek_operation_content_upload_finished($success, $results, $operations) {
  $count = !empty($results['uploads']) ? $results['uploads'] : 0;
  $num_documents = format_plural($count, '1 document', '@count documents', array('@count' => $count));
  drupal_set_message(t('Uploaded @num_documents to Lingotek.', array('@num_documents' => $num_documents)));
}

