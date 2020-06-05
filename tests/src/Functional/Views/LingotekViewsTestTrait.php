<?php

namespace Drupal\Tests\lingotek\Functional\Views;

/**
 * Trait Lingotek views test. Overrides some methods from LingotekTestBase.
 */
trait LingotekViewsTestTrait {

  /**
   * {@inheritdoc}
   */
  protected function goToConfigBulkManagementForm($filter = NULL) {
    throw new \BadMethodCallException('Not implemented yet.');
  }

  /**
   * {@inheritdoc}
   */
  protected function assertManagementFormProfile($index, $profile) {
    throw new \BadMethodCallException('Not implemented yet.');
  }

  /**
   * {@inheritdoc}
   */
  protected function getContentBulkManagementFormUrl($entity_type_id = 'node', $prefix = NULL) {
    return ($prefix === NULL ? '' : '/' . $prefix) . '/lingotek/views/' . $entity_type_id . '_and_lingotek_metadata';
  }

  protected function getBulkSelectionKey($langcode, $revision_id, $entity_type_id = 'node') {
    return $entity_type_id . '_bulk_form[' . ($revision_id - 1) . ']';
  }

  protected function getBulkOperationFormName() {
    return 'action';
  }

  protected function getBulkOperationNameForUpload($entity_type_id) {
    return $entity_type_id . '_lingotek_upload_action';
  }

  protected function getBulkOperationNameForCheckUpload($entity_type_id) {
    return $entity_type_id . '_lingotek_check_upload_action';
  }

  protected function getBulkOperationNameForRequestTranslations($entity_type_id) {
    return $entity_type_id . '_lingotek_request_translations_action';
  }

  protected function getBulkOperationNameForRequestTranslation($langcode, $entity_type_id) {
    return $entity_type_id . '_' . $langcode . '_lingotek_request_translation_action';
  }

  protected function getBulkOperationNameForCheckTranslations($entity_type_id) {
    return $entity_type_id . '_lingotek_check_translations_action';
  }

  protected function getBulkOperationNameForCheckTranslation($langcode, $entity_type_id) {
    return $entity_type_id . '_' . $langcode . '_lingotek_check_translation_action';
  }

  protected function getBulkOperationNameForDownloadTranslations($entity_type_id) {
    return $entity_type_id . '_lingotek_download_translations_action';
  }

  protected function getBulkOperationNameForDownloadTranslation($langcode, $entity_type_id) {
    return $entity_type_id . '_' . $langcode . '_lingotek_download_translation_action';
  }

  protected function getBulkOperationNameForCancel($entity_type_id) {
    return $entity_type_id . '_lingotek_cancel_action';
  }

  protected function getBulkOperationNameForCancelTarget($langcode, $entity_type_id) {
    return $entity_type_id . '_' . $langcode . '_lingotek_cancel_translation_action';
  }

  protected function getBulkOperationNameForDeleteTranslation($langcode, $entity_type_id) {
    return $entity_type_id . '_' . $langcode . '_lingotek_delete_translation_action';
  }

  protected function getBulkOperationNameForDeleteTranslations($entity_type_id) {
    return $entity_type_id . '_lingotek_delete_translations_action';
  }

  protected function getApplyActionsButtonLabel() {
    return t('Apply to selected items');
  }

}
