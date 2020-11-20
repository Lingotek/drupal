<?php

namespace Drupal\lingotek;

/**
 * Service for managing Lingotek interface translations.
 */
interface LingotekInterfaceTranslationServiceInterface {

  /**
   * Checks the source is uploaded correctly.
   *
   * @param string $component
   *   The component which status we want to check.
   *
   * @return bool
   *   True if the component is uploaded successfully.
   */
  public function checkSourceStatus($component);

  /**
   * Gets the source status of the given component.
   *
   * @param string $component
   *   The component which status we want to check.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getSourceStatus($component);

  /**
   * Sets the translation status of a given component.
   *
   * @param string $component
   *   The component which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek class constants.
   *
   * @return string
   */
  public function setSourceStatus($component, $status);

  /**
   * Gets the current status of all the target translations.
   *
   * @param string $component
   *   The component which status we want to check.
   */
  public function checkTargetStatuses($component);

  /**
   * Gets the current status of the target translation.
   *
   * @param string $component
   *   The component which status we want to check.
   * @param string $langcode
   *   Translation language we want to check.
   *
   * @return bool
   *   True if the component is uploaded succesfully.
   */
  public function checkTargetStatus($component, $langcode);

  /**
   * Gets the translation status of a given component translation for a locale.
   *
   * @param string $component
   *   The component which status we want to get.
   * @param string $locale
   *   Lingotek translation language which we want to get.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getTargetStatus($component, $locale);

  /**
   * Gets the translation statuses of a given component translation for all locales.
   *
   * @param string $component
   *   The component which status we want to get.
   *
   * @return array
   *   The statuses of the target translation keyed by langcode
   *   (see Lingotek class constants for the values)
   */
  public function getTargetStatuses($component);

  /**
   * Sets the translation status of a given component translation for a locale.
   *
   * @param string $component
   *   The component which status we want to change.
   * @param string $langcode
   *   Language code which we want to modify.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return string
   */
  public function setTargetStatus($component, $langcode, $status);

  /**
   * Sets the translation status of all translations of a given component.
   *
   * @param string $component
   *   The component which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return string
   */
  public function setTargetStatuses($component, $status);

  /**
   * Marks the translation status as dirty if they exist.
   *
   * @param string $component
   *   The component which status we want to change.
   *
   * @return string
   */
  public function markTranslationsAsDirty($component);

  /**
   * Gets the document id in the Lingotek platform for a given component.
   *
   * @param string $component
   *   The component which we want the document id.
   *
   * @return string
   *   The document id in the Lingotek platform.
   */
  public function getDocumentId($component);

  /**
   * Sets the Lingotek document id for a given component.
   *
   * @param string $component
   *   The component which we want to set a document id.
   * @param $doc_id
   *   The document id in the Lingotek platform.
   *
   * @return string
   *   The component.
   */
  public function setDocumentId($component, $doc_id);

  /**
   * Gets the translation source locale of a given component.
   *
   * @param string $component
   *   The component which we want to get the source locale.
   *
   * @return string
   *   The locale as expected by the Lingotek service.
   */
  public function getSourceLocale($component);

  /**
   * Returns the source data that will be uploaded to the Lingotek service.
   *
   * Only those fields that have actual translatable text, and have marked for upload will
   * be included.
   *
   * @param string $component
   *   The component which we want the source data.
   *
   * @return array
   */
  public function getSourceData($component);

  /**
   * Updates the component hash.
   *
   * @param string $component
   *   The component being checked.
   */
  public function updateEntityHash($component);

  /**
   * Checks if the source component data has changed from last time we uploaded it.
   *
   * @param string $component
   *   The component being checked.
   *
   * @return bool
   *   TRUE if the component has changed, false if not.
   */
  public function hasEntityChanged($component);

  /**
   * Request a translation for a given component in the given locale.
   *
   * @param string $component
   *   The component which target we want to add.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function addTarget($component, $locale);

  /**
   * Requests translations of a document in all the enabled locales.
   *
   * @param string $component
   *   The component being requested for translations.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function requestTranslations($component);

  /**
   * Uploads a document to the Lingotek service.
   *
   * @param string $component
   *   The component being uploaded.
   * @param string $job_id
   *   (optional) The job ID that will be associated.
   *
   * @return bool
   *   TRUE if the document was uploaded successfully, FALSE if not.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   *
   * Propagated from @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @see ::updateDocument
   */
  public function uploadDocument($component, $job_id = NULL);

  /**
   * Downloads a document from the Lingotek service for a given locale.
   *
   * @param string $component
   *   The component being downloaded.
   * @param string $locale
   *   Lingotek translation language which we want to download.
   *
   * @return bool
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadDocument($component, $locale);

  /**
   * Resends a document to the translation service.
   *
   * @param string $component
   *   The component being updated.
   * @param string $job_id
   *   (optional) The job ID that will be associated.
   *
   * @return bool
   *   TRUE if the document was updated successfully, FALSE if not.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function updateDocument($component, $job_id = NULL);

  /**
   * Downloads a document from the Lingotek service for all available locales.
   *
   * @param string $component
   *   The component being downloaded.
   *
   * @return bool
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadDocuments($component);

  /**
   * Cancels a document from the server.
   *
   * @param string $component
   *   The component which we want to cancel.
   *
   * @return string
   *   The component.
   */
  public function cancelDocument($component);

  /**
   * Cancels a translation for a given component in the given locale.
   *
   * @param string $component
   *   The component which target we want to cancel.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   */
  public function cancelDocumentTarget($component, $locale);

  /**
   * Deletes all local metadata related to an component.
   *
   * @param string $component
   *   The component which we want to forget about.
   *
   * @return string
   *   The component.
   */
  public function deleteMetadata($component);

  /**
   * {@inheritdoc}
   */
  public function deleteAllMetadata();

  /**
   * Loads the component with the given document id.
   *
   * @param string $document_id
   *   The document id.
   *
   * @return string
   *   The component with the given document id.
   */
  public function loadByDocumentId($document_id);

  /**
   * Gets all local document ids.
   *
   * @return string[]
   *   Gets all local document ids.
   */
  public function getAllLocalDocumentIds();

  /**
   * Save the component translation.
   *
   * @param string $component
   *   The component we want to save a translation for.
   * @param $locale
   *   The locale of the translation being saved.
   * @param $data
   *   The data being saved.
   *
   * @return string
   *   Returns the component which translations are saved.
   */
  public function saveTargetData($component, $locale, $data);

  /**
   * Gets the job ID of a given component.
   *
   * @param string $component
   *   The component we want to get the job id.
   *
   * @return string
   *   Returns the job ID is saved.
   */
  public function getJobId($component);

  /**
   * Sets the job ID of a given component.
   *
   * @param string $component
   *   The component we want to save a job id for.
   * @param string $job_id
   *   The job ID being saved.
   * @param bool $update_tms
   *   (Optional) Flag indicating if the change should be communicated to the
   *   TMS. False by default.
   *
   * @return string
   *   Returns the component which job ID is saved.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function setJobId($component, $job_id, $update_tms = FALSE);

  /**
   * Updates the 'initial upload' time metadata to the current request time.
   *
   * @param string $component
   *   The component for which we want to update the timestamp.
   * @param int $timestamp
   *   The timestamp we want to store.
   *
   * @return string
   *   Returns the component which translations are saved.
   */
  public function setLastUploaded($component, int $timestamp);

  /**
   * Updates the 'updated date' time metadata to the current request time.
   *
   * @param string $component
   *   The component for which we want to update the timestamp.
   * @param int $timestamp
   *   The timestamp we want to store.
   *
   * @return string
   *   Returns the component which translations are saved.
   */
  public function setLastUpdated($component, int $timestamp);

  /**
 * Returns the 'initial upload' time metadata.
 *
 * @param string $component
 *   The component for which we want to get the timestamp.
   *
 * @return int
 *   Returns the unix timestamp.
 */
  public function getLastUploaded($component);

  /**
   * Returns the 'updated date' time metadata.
   *
   * @param string $component
   *   The component for which we want to get the timestamp.
   *
   * @return int
   *   Returns the unix timestamp.
   */
  public function getUpdatedTime($component);

}
