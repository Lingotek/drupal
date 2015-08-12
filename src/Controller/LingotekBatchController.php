<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;
use Drupal\lingotek\LingotekTranslatableEntity;

class LingotekBatchController extends LingotekControllerBase {

  public function dispatch($action, $entity_type, $entity_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $L = \Drupal::service('lingotek');

    $entity = \Drupal::entityManager()->getStorage($entity_type)->load($entity_id);

    // ToDo: Remove profile functionality from LingotekTranslatableEntity.
    $lte = LingotekTranslatableEntity::load($L, $entity);

    // This forces the hash to be set.
    $translation_service->hasEntityChanged($entity);

    if (!$lte->getProfile()) {
      $lte->setProfileForNewlyIdentifiedEntities(); 
    }
    
    switch ($action) {
      case 'uploadSingle':
        return $this->uploadSingle($entity_type, $entity_id);
      case 'downloadSingle':
        return $this->downloadSingle($entity_type, $entity_id);
      default:
        return $this->noAction();
    }
  }

  public function uploadSingle($entity_type, $entity_id) {
    $batch = array(
      'title' => $this->t('Uploading content to Lingotek'),
      'operations' => $this->getUploadOperations($entity_type, array($entity_id)),
      'finished' => 'lingotek_operation_content_upload_finished',
      'file' => drupal_get_path('module', 'lingotek') . '/lingotek.batch.inc',
    );
    batch_set($batch);
    return batch_process("$entity_type/$entity_id/translations");
  }

  public function downloadSingle($entity_type, $entity_id, $locales) {
    $batch = array(
      'title' => $this->t('Downloading translations from Lingotek'),
      'operations' => $this->getDownloadOperations($entity_type, array($entity_id), $locales),
      'finished' => 'lingotek_operation_content_download_finished',
      'file' => drupal_get_path('module', 'lingotek') . '/lingotek.batch.inc',
    );
    batch_set($batch);
    return batch_process("$entity_type/$entity_id/translations");
  }

  public function checkUploadStatus($entity_type, $entity_id) {
    // TODO
  }

  public function checkTargetStatus($entity_type, $entity_id) {
    // TODO
  }

  public function addLanguageSingle($entity_type, $entity_id) {
    // TODO
  }

  protected function getUploadOperations($entity_type, $entity_ids) {
    $operations = array();
    if (is_array($entity_ids)) {
      foreach ($entity_ids as $id) {
        $operations[] = array('lingotek_operation_content_upload', array($entity_type, $id));
      }
    }
    return $operations;
  }

  protected function getDownloadOperations($entity_type, $entity_ids, $locales) {
    $operations = array();
    if (is_array($entity_ids)) {
      foreach ($entity_ids as $id) {
        foreach ($locales as $locale) {
          $operations[] = array('lingotek_operation_translation_download', array($entity_type, $id, $locale));
        }
      }
    }
    return $operations;
  }

  public function noAction() {
    $markup = $this->t('Lingotek batch operation error: You must supply a valid action.');
    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }

}
