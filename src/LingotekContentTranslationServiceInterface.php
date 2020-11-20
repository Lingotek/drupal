<?php

namespace Drupal\lingotek;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Service for managing Lingotek content translations.
 */
interface LingotekContentTranslationServiceInterface {

  /**
   * Checks the source is uploaded correctly.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to check.
   *
   * @return bool
   *   True if the entity is uploaded successfully.
   */
  public function checkSourceStatus(ContentEntityInterface &$entity);

  /**
   * Gets the source status of the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to check.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getSourceStatus(ContentEntityInterface &$entity);

  /**
   * Sets the translation status of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek class constants.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   */
  public function setSourceStatus(ContentEntityInterface &$entity, $status);

  /**
   * Gets the current status of all the target translations.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to check.
   */
  public function checkTargetStatuses(ContentEntityInterface &$entity);

  /**
   * Gets the current status of the target translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to check.
   * @param string $langcode
   *   Translation language we want to check.
   *
   * @return bool
   *   True if the entity is uploaded succesfully.
   */
  public function checkTargetStatus(ContentEntityInterface &$entity, $langcode);

  /**
   * Gets the translation status of a given entity translation for a locale.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to get.
   * @param string $locale
   *   Lingotek translation language which we want to get.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getTargetStatus(ContentEntityInterface &$entity, $locale);

  /**
   * Gets the translation statuses of a given entity translation for all locales.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to get.
   *
   * @return array
   *   The statuses of the target translation keyed by langcode
   *   (see Lingotek class constants for the values)
   */
  public function getTargetStatuses(ContentEntityInterface &$entity);

  /**
   * Sets the translation status of a given entity translation for a locale.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to change.
   * @param string $langcode
   *   Language code which we want to modify.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   * @param bool $save
   *   If FALSE, the entity is not saved yet. Defaults to TRUE.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   */
  public function setTargetStatus(ContentEntityInterface &$entity, $langcode, $status, $save = TRUE);

  /**
   * Sets the translation status of all translations of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   */
  public function setTargetStatuses(ContentEntityInterface &$entity, $status);

  /**
   * Marks the translation status as dirty if they exist.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which status we want to change.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   */
  public function markTranslationsAsDirty(ContentEntityInterface &$entity);

  /**
   * Gets the document id in the Lingotek platform for a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want the document id.
   *
   * @return string
   *   The document id in the Lingotek platform.
   */
  public function getDocumentId(ContentEntityInterface &$entity);

  /**
   * Sets the Lingotek document id for a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want to set a document id.
   * @param $doc_id
   *   The document id in the Lingotek platform.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function setDocumentId(ContentEntityInterface &$entity, $doc_id);

  /**
   * Gets the translation source locale of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want to get the source locale.
   *
   * @return string
   *   The locale as expected by the Lingotek service.
   */
  public function getSourceLocale(ContentEntityInterface &$entity);

  /**
   * Returns the source data that will be uploaded to the Lingotek service.
   *
   * Only those fields that have actual translatable text, and have marked for upload will
   * be included.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want the source data.
   *
   * @return mixed
   */
  public function getSourceData(ContentEntityInterface &$entity);

  /**
   * Updates the entity hash.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being checked
   *
   * @return $this
   */
  public function updateEntityHash(ContentEntityInterface $entity);

  /**
   * Checks if the source entity data has changed from last time we uploaded it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being checked
   *
   * @return bool
   *   TRUE if the entity has changed, false if not.
   */
  public function hasEntityChanged(ContentEntityInterface &$entity);

  /**
   * Request a translation for a given entity in the given locale.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which target we want to add.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function addTarget(ContentEntityInterface &$entity, $locale);

  /**
   * Requests translations of a document in all the enabled locales.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being requested for translations.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function requestTranslations(ContentEntityInterface &$entity);

  /**
   * Uploads a document to the Lingotek service.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being uploaded.
   * @param string $job_id
   *   (optional) The job ID that will be associated.
   *
   * @return bool
   *   TRUE if the document was uploaded successfully, FALSE if not.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   *
   * Propagated from @see ::updateDocument :
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   */
  public function uploadDocument(ContentEntityInterface $entity, $job_id = NULL);

  /**
   * Downloads a document from the Lingotek service for a given locale.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being downloaded.
   * @param string $locale
   *   Lingotek translation language which we want to download.
   *
   * @return bool
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadDocument(ContentEntityInterface &$entity, $locale);

  /**
   * Downloads a document from the Lingotek service for all available locales.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being downloaded.
   *
   * @return bool
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadDocuments(ContentEntityInterface &$entity);

  /**
   * Resends a document to the translation service.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being updated.
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
  public function updateDocument(ContentEntityInterface &$entity, $job_id = NULL);

  /**
   * Cancels a document from the server.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want to cancel.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function cancelDocument(ContentEntityInterface &$entity);

  /**
   * Cancels a translation for a given entity in the given locale.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which target we want to cancel.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   */
  public function cancelDocumentTarget(ContentEntityInterface &$entity, $locale);

  /**
   * Deletes all local metadata related to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want to forget about.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function deleteMetadata(ContentEntityInterface &$entity);

  /**
   * Loads the entity with the given document id.
   *
   * @param string $document_id
   *   The document id.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity with the given document id.
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
   * Save the entity translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity we want to save a translation for.
   * @param $locale
   *   The locale of the translation being saved.
   * @param $data
   *   The data being saved.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns the entity which translations are saved.
   */
  public function saveTargetData(ContentEntityInterface &$entity, $locale, $data);

  /**
   * Sets the job ID of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity we want to save a job id for.
   * @param string $job_id
   *   The job ID being saved.
   * @param bool $update_tms
   *   (Optional) Flag indicating if the change should be communicated to the
   *   TMS. False by default.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns the entity which job ID is saved.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function setJobId(ContentEntityInterface $entity, $job_id, $update_tms = FALSE);

  /**
   * Gets the job ID of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity we want to get the job id.
   *
   * @return string
   *   Returns the job ID is saved.
   */
  public function getJobId(ContentEntityInterface $entity);

  /**
   * Updates the 'initial upload' time metadata to the current request time.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity which we want the document id.
   * @param int $timestamp
   *   The timestamp we want to store.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns the entity.
   */
  public function setLastUploaded(ContentEntityInterface $entity, int $timestamp);

  /**
   * Updates the 'updated date' time metadata to the current request time.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity which we want the document id.
   * @param int $timestamp
   *   The timestamp we want to store.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns the entity.
   */
  public function setLastUpdated(ContentEntityInterface $entity, int $timestamp);

  /**
   * Gets the 'initial upload' time metadata for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity which we want the document id.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns the timestamp.
   */
  public function getLastUploaded(ContentEntityInterface $entity);

  /**
   * Gets the 'updated date' time metadata for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity which we want the document id.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns the timestamp.
   */
  public function getLastUpdated(ContentEntityInterface $entity);

}
