<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Trait with Lingotek management form helpers.
 */
trait LingotekManagementTestTrait {

  /**
   * Asserts there is a link for uploading the content.
   *
   * @param int|string $entity_id
   *   The entity ID. Optional, defaults to 1.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertLingotekUploadLink($entity_id = 1, $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/upload/' . $entity_type_id . '/' . $entity_id;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertLinkByHref($href);
  }

  /**
   * Asserts there is no link for uploading the content.
   *
   * @param int|string $entity_id
   *   The entity ID. Optional, defaults to 1.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertNoLingotekUploadLink($entity_id = 1, $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/upload/' . $entity_type_id . '/' . $entity_id;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertNoLinkByHref($href);
  }

  /**
   * Asserts there is a link for updating the content.
   *
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertLingotekUpdateLink($document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/update/' . $document_id;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertLinkByHref($href);
  }

  /**
   * Asserts there is a link for checking the content source status.
   *
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertLingotekCheckSourceStatusLink($document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/check_upload/' . $document_id;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertLinkByHref($href);
  }

  /**
   * Asserts there is NOT a link for checking the content source status.
   *
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertNoLingotekCheckSourceStatusLink($document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/check_upload/' . $document_id;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertNoLinkByHref($href);
  }

  /**
   * Asserts there is a link for requesting translation for a given locale.
   *
   * @param string $locale
   *   The locale.
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertLingotekRequestTranslationLink($locale, $document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/add_target/' . $document_id . '/' . $locale;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertLinkByHref($href);
  }

  /**
   * Asserts there is NOT a link for requesting translation for a given locale.
   *
   * @param string $locale
   *   The locale.
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertNoLingotekRequestTranslationLink($locale, $document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/add_target/' . $document_id . '/' . $locale;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertNoLinkByHref($href);
  }

  /**
   * Asserts there is a link for checking the translation status for a given locale.
   *
   * @param string $locale
   *   The locale.
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertLingotekCheckTargetStatusLink($locale, $document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/check_target/' . $document_id . '/' . $locale;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertLinkByHref($href);
  }

  /**
   * Asserts there is NOT a link for checking the translation status for a given locale.
   *
   * @param string $locale
   *   The locale.
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertNoLingotekCheckTargetStatusLink($locale, $document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/check_target/' . $document_id . '/' . $locale;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertNoLinkByHref($href);
  }

  /**
   * Asserts there is a link for downloading the translation for a given locale.
   *
   * @param string $locale
   *   The locale.
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertLingotekDownloadTargetLink($locale, $document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/download/' . $document_id . '/' . $locale;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertLinkByHref($href);
  }

  /**
   * Asserts there is NOT a link for downloading the translation for a given locale.
   *
   * @param string $locale
   *   The locale.
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   * @param string $entity_type_id
   *   The entity type ID. Optional, defaults to node.
   * @param string|null $prefix
   *   Language prefix if any. Optional, defaults to NULL.
   */
  protected function assertNoLingotekDownloadTargetLink($locale, $document_id = 'dummy-document-hash-id', $entity_type_id = 'node', $prefix = NULL, $destination_entity_type_id = NULL, $destination_entity_id = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $languagePrefix = ($prefix === NULL ? '' : '/' . $prefix);
    $destination_entity_type_id = $destination_entity_type_id ?: $entity_type_id;
    $href = $basepath . $languagePrefix . '/admin/lingotek/entity/download/' . $document_id . '/' . $locale;
    if ($destination = $this->getDestination($destination_entity_type_id, $prefix)) {
      $href .= $destination;
    }
    $this->assertNoLinkByHref($href);
  }

  /**
   * Asserts there is a link to the Lingotek workbench in a new tab.
   *
   * @param string $locale
   *   The locale.
   * @param string $document_id
   *   The Lingotek document ID. Optional, defaults to 'dummy-document-hash-id'.
   */
  protected function assertLingotekWorkbenchLink($locale, $document_id = 'dummy-document-hash-id', $text = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/' . $document_id . '/' . $locale);
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/$document_id/$locale' and @target='_blank']");
    if ($text !== NULL) {
      $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/$document_id/$locale' and @target='_blank' and text()='$text']");
    }
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }

  /**
   * Gets the bulk checkbox selection key in a table.
   *
   * @param string $langcode
   *   The langcode.
   * @param int $revision_id
   *   The revision ID.
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string
   *   The table checkbox key.
   */
  protected function getBulkSelectionKey($langcode, $revision_id, $entity_type_id = 'node') {
    return 'table[' . $revision_id . ']';
  }

  /**
   * Gets the bulk operation form name for selection.
   *
   * @return string
   */
  protected function getBulkOperationFormName() {
    return 'operation';
  }

  protected function getBulkOperationNameForUpload($entity_type_id) {
    return 'upload';
  }

  protected function getBulkOperationNameForCheckUpload($entity_type_id) {
    return 'check_upload';
  }

  protected function getBulkOperationNameForRequestTranslations($entity_type_id) {
    return 'request_translations';
  }

  protected function getBulkOperationNameForRequestTranslation($langcode, $entity_type_id) {
    return 'request_translation:' . $langcode;
  }

  protected function getBulkOperationNameForCheckTranslations($entity_type_id) {
    return 'check_translations';
  }

  protected function getBulkOperationNameForCheckTranslation($langcode, $entity_type_id) {
    return 'check_translation:' . $langcode;
  }

  protected function getBulkOperationNameForDownloadTranslations($entity_type_id) {
    return 'download';
  }

  protected function getBulkOperationNameForDownloadTranslation($langcode, $entity_type_id) {
    return 'download:' . $langcode;
  }

  protected function getBulkOperationNameForCancel($entity_type_id) {
    return 'cancel';
  }

  protected function getBulkOperationNameForCancelTarget($langcode, $entity_type_id) {
    return 'cancel:' . $langcode;
  }

  protected function getBulkOperationNameForDeleteTranslation($langcode, $entity_type_id) {
    return 'delete_translation:' . $langcode;
  }

  protected function getBulkOperationNameForDeleteTranslations($entity_type_id) {
    return 'delete_translations';
  }

  protected function getBulkOperationNameForAssignJobId($entity_type_id) {
    return 'assign_job';
  }

  protected function getBulkOperationNameForClearJobId($entity_type_id) {
    return 'clear_job';
  }

  protected function getApplyActionsButtonLabel() {
    return t('Execute');
  }

}
