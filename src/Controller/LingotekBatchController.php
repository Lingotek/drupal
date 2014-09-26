<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;

class LingotekBatchController extends LingotekControllerBase {

  public function dispatch($action, $entity_type, $entity_id) {
    switch ($action) {
      case 'uploadSingle':
        return $this->uploadSingle($entity_type, $entity_id);
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

  public function noAction() {
    $markup = $this->t('Lingotek batch operation error: You must supply a valid action.');
    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }

}
