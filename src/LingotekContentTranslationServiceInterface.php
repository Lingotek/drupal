<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekContentTranslationServiceInterface.
 */

namespace Drupal\lingotek;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Service for managing Lingotek content translations.
 */
interface LingotekContentTranslationServiceInterface {

  /**
   * Checks the source is uploaded correctly.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which status we want to check.
   *
   * @return boolean
   *   True if the entity is uploaded succesfully.
   */
  public function checkSourceStatus(ContentEntityInterface &$entity);

  /**
   * Gets the source status of the given entity.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which status we want to check.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getSourceStatus(ContentEntityInterface &$entity);

  /**
   * Sets the translation status of a given entity.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek class constants.

   *
   * @return ContentEntityInterface
   */
  public function setSourceStatus(ContentEntityInterface &$entity, $status);

  /**
   * Gets the current status of the target translation.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which status we want to check.
   * @param string $locale
   *   Lingotek translation language we want to check.
   *
   * @return boolean
   *   True if the entity is uploaded succesfully.
   */
  public function checkTargetStatus(ContentEntityInterface &$entity, $locale);

  /**
   * Gets the translation status of a given entity translation for a locale.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which status we want to get.
   * @param string $locale
   *   Lingotek translation language which we want to get.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getTargetStatus(ContentEntityInterface &$entity, $locale);

  /**
   * Sets the translation status of a given entity translation for a locale.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which status we want to change.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return ContentEntityInterface
   */
  public function setTargetStatus(ContentEntityInterface &$entity, $locale, $status);

  /**
   * Sets the translation status of all translations of a given entity.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return ContentEntityInterface
   */
  public function setTargetStatuses(ContentEntityInterface &$entity, $status);

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
   * @return ContentEntityInterface
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
   * Checks if the source entity data has changed from last time we uploaded it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being checked
   *
   * @return boolean
   *   TRUE if the entity has changed, false if not.
   */
  public function hasEntityChanged(ContentEntityInterface &$entity);

  /**
   * Request a translation for a given entity in the given locale.
   *
   * @param ContentEntityInterface &$entity
   *   The entity which target we want to add.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   */
  public function addTarget(ContentEntityInterface &$entity, $locale);

  /**
   * Requests translations of a document in all the enabled locales.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being requested for translations.
   */
  public function requestTranslations(ContentEntityInterface &$entity);

  /**
   * Uploads a document to the Lingotek service.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being uploaded.
   *
   * @return boolean
   *   TRUE if the document was uploaded successfully, FALSE if not.
   */
  public function uploadDocument(ContentEntityInterface &$entity);

  /**
   * Downloads a document from the Lingotek service for a given locale.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being downloaded.
   * @param string $locale
   *   Lingotek translation language which we want to download.
   *
   * @return boolean
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadDocument(ContentEntityInterface &$entity, $locale);

  /**
   * Resends a document to the translation service.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity being updated.
   *
   * @return boolean
   *   TRUE if the document was updated successfully, FALSE if not.
   */
  public function updateDocument(ContentEntityInterface &$entity);

  /**
   * Deletes a document from the server and all related local data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want to delete.
   *
   * @return ContentEntityInterface
   *   The entity.
   */
  public function deleteDocument(ContentEntityInterface &$entity);

  /**
   * Deletes all local metadata related to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
   *   The entity which we want to forget about.
   *
   * @return ContentEntityInterface
   *   The entity.
   */
  public function deleteMetadata(ContentEntityInterface &$entity);

  /**
   * Loads the entity with the given document id.
   *
   * @param string $document_id
   *   The document id.
   *
   * @return ContentEntityInterface
   *   The entity with the given document id.
   */
  public function loadByDocumentId($document_id);

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
   * @return ContentEntityInterface
   *   Returns the entity which translations are saved.
   */
  public function saveTargetData(ContentEntityInterface &$entity, $locale, $data);

}