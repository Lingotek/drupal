<?php

namespace Drupal\lingotek;

use Drupal\config_translation\ConfigNamesMapper;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;

/**
 * Service for managing Lingotek configuration translations.
 */
interface LingotekConfigTranslationServiceInterface {

  /**
   * Gets the config entities that are available for Lingotek config translation.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of config entities that are enabled for Lingotek config translation.
   */
  public function getEnabledConfigTypes();

  /**
   * Checks if the given plugin is enabled for Lingotek config translation.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   *
   * @return bool
   *   TRUE if it enabled, FALSE if not.
   */
  public function isEnabled($plugin_id);

  /**
   * Sets if the given config entity is enabled for Lingotek config translation.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   *
   * @param bool $enabled
   *   Flag for enabling or disabling this config entity. Defaults to TRUE.
   */
  public function setEnabled($plugin_id, $enabled = TRUE);

  /**
   * Gets the configuration translatable properties of the given mapper.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The mapper.
   *
   * @return array
   *   Canonical names of the translatable properties.
   */
  public function getConfigTranslatableProperties(ConfigNamesMapper $mapper);

  /**
   * Gets the document id in the Lingotek platform for a given entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which we want the document id.
   *
   * @return string
   *   The document id in the Lingotek platform.
   */
  public function getDocumentId(ConfigEntityInterface $entity);

  /**
   * Sets the document id in the Lingotek platform for a given entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which we want the document id.
   *
   * @param string $document_id
   *   The document id in the Lingotek platform.
   */
  public function setDocumentId(ConfigEntityInterface &$entity, $document_id);

  /**
   * Gets the source status of the given entity.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity which status we want to check.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getSourceStatus(ConfigEntityInterface &$entity);

  /**
   * Sets the translation status of a given entity.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek class constants.
   *
   * @return \Drupal\Core\Config\ConfigEntityInterface
   */
  public function setSourceStatus(ConfigEntityInterface &$entity, $status);

  /**
   * Gets the translation status of a given entity translation for a locale.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity which status we want to get.
   * @param string $locale
   *   Lingotek translation language which we want to get.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getTargetStatus(ConfigEntityInterface &$entity, $locale);

  /**
   * Gets the translation status of a given entity translation for all locales.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity which statuses we want to get.
   *
   * @return array
   *   The status of the target translations (see Lingotek class constants)
   */
  public function getTargetStatuses(ConfigEntityInterface &$entity);

  /**
   * Sets the translation status of a given entity translation for a locale.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   * @param bool $save
   *   If FALSE, the entity is not saved yet. Defaults to TRUE.
   *
   * @return \Drupal\Core\Config\ConfigEntityInterface
   */
  public function setTargetStatus(ConfigEntityInterface &$entity, $locale, $status, $save = TRUE);

  /**
   * Sets the translation status of all translations of a given entity.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return \Drupal\Core\Config\ConfigEntityInterface
   */
  public function setTargetStatuses(ConfigEntityInterface &$entity, $status);

  /**
   * Marks the translation status as dirty if they exist.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   *
   * @return \Drupal\Core\Config\ConfigEntityInterface
   */
  public function markTranslationsAsDirty(ConfigEntityInterface &$entity);

  /**
   * Checks if the source entity data has changed from last time we uploaded it.
   *
   * @param \Drupal\Core\Config\ConfigEntityInterface &$entity
   *   The entity being checked
   *
   * @return bool
   *   TRUE if the entity has changed, false if not.
   */
  public function hasEntityChanged(ConfigEntityInterface &$entity);

  public function getSourceData(ConfigEntityInterface $entity);

  /**
   * Gets the translation source locale of a given entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which we want to get the source locale.
   *
   * @return string
   *   The locale as expected by the Lingotek service.
   */
  public function getSourceLocale(ConfigEntityInterface &$entity);

  /**
   * Uploads a document to the Lingotek service.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
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
  public function uploadDocument(ConfigEntityInterface $entity, $job_id = NULL);

  /**
   * Checks the source is uploaded correctly.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which status we want to check.
   *
   * @return bool
   *   True if the entity is uploaded succesfully.
   */
  public function checkSourceStatus(ConfigEntityInterface &$entity);

  /**
   * Resends a document to the translation service.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
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
  public function updateDocument(ConfigEntityInterface &$entity, $job_id = NULL);

  /**
   * Request a translation for a given entity in the given locale.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which target we want to add.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function addTarget(ConfigEntityInterface &$entity, $locale);

  /**
   * Requests translations of a document in all the enabled locales.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being requested for translations.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function requestTranslations(ConfigEntityInterface &$entity);

  /**
   * Checks the status of the translation in the Lingotek service.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which status we want to check.
   * @param string $locale
   *   Lingotek translation language which we want to download.
   *
   * @return bool
   *   True if the entity is checked successfully.
   */
  public function checkTargetStatus(ConfigEntityInterface &$entity, $locale);

  /**
   * Checks the status of all the translations in the Lingotek service.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which status we want to check.
   *
   * @return bool
   *   True if the entity is checked successfully.
   */
  public function checkTargetStatuses(ConfigEntityInterface &$entity);

  /**
   * Downloads a document from the Lingotek service for a given locale.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being downloaded.
   * @param string $locale
   *   Lingotek translation language which we want to download.
   *
   * @return bool
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadDocument(ConfigEntityInterface $entity, $locale);

  /**
   * Cancels a document from the server.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface &$entity
   *   The entity which we want to cancel.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The entity.
   */
  public function cancelDocument(ConfigEntityInterface &$entity);

  /**
   * Cancels a translation for a given entity in the given locale.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface &$entity
   *   The entity which target we want to cancel.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   */
  public function cancelDocumentTarget(ConfigEntityInterface &$entity, $locale);

  /**
   * Deletes metadata.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which we want to delete.
   *
   * @return ContentEntityInterface
   *   The entity.
   */
  public function deleteMetadata(ConfigEntityInterface &$entity);

  /**
   * Gets the document id in the Lingotek platform for a given entity.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which we want the document id.
   *
   * @return string
   *   The document id in the Lingotek platform.
   */
  public function getConfigDocumentId(ConfigNamesMapper $mapper);

  /**
   * Sets the document id in the Lingotek platform for a given entity.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which we want to set the document id.
   * @param string $document_id
   *   The document id.
   *
   * @return string
   *   The document id in the Lingotek platform.
   */
  public function setConfigDocumentId(ConfigNamesMapper $mapper, $document_id);

  /**
   * Gets the source status of a given entity.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   *
   * @return int
   *   Status of the source. Use Lingotek class constants.
   */
  public function getConfigSourceStatus(ConfigNamesMapper $mapper);

  /**
   * Sets the translation status of a given entity.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek class constants.
   *
   * @return \Drupal\Core\Config\ConfigEntityInterface
   */
  public function setConfigSourceStatus(ConfigNamesMapper $mapper, $status);

  /**
   * Gets the translation status of a given entity translation for all locales.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which status we want to get.
   *
   * @return array
   *   The status of the target translations (see Lingotek class constants)
   */
  public function getConfigTargetStatuses(ConfigNamesMapper $mapper);

  /**
   * Gets the translation status of a given entity translation for a locale.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which status we want to get.
   * @param string $locale
   *   Lingotek translation language which we want to get.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getConfigTargetStatus(ConfigNamesMapper $mapper, $locale);

  /**
   * Sets the translation status of all translations of a given entity.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   * @param string $locale
   *   Lingotek translation language which we want to get.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return \Drupal\Core\Config\ConfigEntityInterface
   */
  public function setConfigTargetStatus(ConfigNamesMapper $mapper, $locale, $status);

  /**
   * Sets the translation status of all translations of a given entity.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return \Drupal\Core\Config\ConfigEntityInterface
   */
  public function setConfigTargetStatuses(ConfigNamesMapper $mapper, $status);

  /**
   * Gets the translation source locale of a given entity.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which we want to get the source locale.
   *
   * @return string
   *   The locale as expected by the Lingotek service.
   */
  public function getConfigSourceLocale(ConfigNamesMapper $mapper);

  /**
   * Returns the source data that will be uploaded to the Lingotek service.
   *
   * Only those fields that have actual translatable text, and have marked for upload will
   * be included.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The entity which we want the source data.
   *
   * @return mixed
   */
  public function getConfigSourceData(ConfigNamesMapper $mapper);

  /**
   * Uploads a document to the Lingotek service.
   *
   * @param string $mapper_id
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
   * Propagated from @see ::updateConfig :
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   */
  public function uploadConfig($mapper_id, $job_id = NULL);

  /**
   * Checks the source is uploaded correctly.
   *
   * @param string $mapper_id
   *   The entity which status we want to check.
   *
   * @return bool
   *   True if the entity is uploaded successfully.
   */
  public function checkConfigSourceStatus($mapper_id);

  /**
   * Request a translation for a given entity in the given locale.
   *
   * @param string $mapper_id
   *   The entity which target we want to add.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function addConfigTarget($mapper_id, $locale);

  /**
   * Request all translations for a given mapper in all locales.
   *
   * @param string $mapper_id
   *   The entity which target we want to add.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function requestConfigTranslations($mapper_id);

  /**
   * Checks the status of the translation in the Lingotek service.
   *
   * @param string $mapper_id
   *   The entity which status we want to check.
   * @param string $locale
   *   Lingotek translation language which we want to check.
   *
   * @return bool
   *   True if the entity is available for download.
   */
  public function checkConfigTargetStatus($mapper_id, $locale);

  /**
   * Checks the status of the translations in the Lingotek service.
   *
   * @param string $mapper_id
   *   The entity which status we want to check.
   *
   * @return bool
   *   True if the entity is available for download.
   */
  public function checkConfigTargetStatuses($mapper_id);

  /**
   * Downloads a document to the Lingotek service.
   *
   * @param string $mapper_id
   *   The entity being uploaded.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   *
   * @return bool
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadConfig($mapper_id, $locale);

  /**
   * Cancels a document from the server.
   *
   * @param string $mapper_id
   *   The entity being cancelled.
   */
  public function cancelConfigDocument($mapper_id);

  /**
   * Cancels a translation for a given entity in the given locale.
   *
   * @param string $mapper_id
   *   The entity being cancelled.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   */
  public function cancelConfigDocumentTarget($mapper_id, $locale);

  /**
   * Deletes metadata.
   *
   * @param string $mapper_id
   *   The entity being uploaded.
   */
  public function deleteConfigMetadata($mapper_id);

  /**
   * Resends a document to the translation service.
   *
   * @param $mapper_id
   *   The entity being updated.
   * @param string $job_id
   *   (optional) The job ID that will be associated.
   *
   * @return bool
   *   TRUE if the document was updated successfully, FALSE if not.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   */
  public function updateConfig($mapper_id, $job_id = NULL);

  /**
   * Marks the translation status as dirty if they exist.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper_id
   *   The mapper which status we want to change.
   *
   * @return \Drupal\config_translation\ConfigNamesMapper
   */
  public function markConfigTranslationsAsDirty(ConfigNamesMapper $mapper_id);

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
   * Sets the job ID of a given entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which we want the document id.
   * @param $job_id
   *   The job ID being saved.
   * @param bool $update_tms
   *   (Optional) Flag indicating if the change should be communicated to the
   *   TMS. False by default.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   Returns the entity which job ID is saved.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function setJobId(ConfigEntityInterface $entity, $job_id, $update_tms = FALSE);

  /**
   * Gets the job ID of a given entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which we want the document id.
   *
   * @return string
   *   Returns the job ID is saved.
   */
  public function getJobId(ConfigEntityInterface $entity);

  /**
   * Sets the job ID of a given mapper.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The mapper we want to save a job id for.
   * @param $job_id
   *   The job ID being saved.
   * @param bool $update_tms
   *   (Optional) Flag indicating if the change should be communicated to the
   *   TMS. False by default.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns the mapper which job ID is saved.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function setConfigJobId(ConfigNamesMapper $mapper, $job_id, $update_tms = FALSE);

  /**
   * Gets the job ID of a given mapper.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The mapper we want to get the job id.
   *
   * @return string
   *   Returns the job ID is saved.
   */
  public function getConfigJobId(ConfigNamesMapper $mapper);

  /**
   * Get the translatable properties for this schema.
   *
   * @param \Drupal\Core\TypedData\TraversableTypedDataInterface $schema
   *   The schema we need to extract the properties from.
   * @param string $prefix
   *   The prefix to be used for constructing the canonical name.
   *
   * @return array
   *   An array of the canonical-named properties.
   */
  public function getTranslatableProperties(TraversableTypedDataInterface $schema, $prefix);

  /**
   * Sets the timestamp for the last time the config was uploaded.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The mapper for which we want to save the timestamp.
   * @param int $timestamp
   *   The timestamp being saved.
   */
  public function setConfigLastUploaded(ConfigNamesMapper $mapper, int $timestamp);

  /**
   * Sets the timestamp for the last time the config was updated.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The mapper for which we want to save the timestamp.
   * @param int $timestamp
   *   The timestamp being saved.
   */
  public function setConfigLastUpdated(ConfigNamesMapper $mapper, int $timestamp);

  /**
   * Gets the timestamp for the last time the config was uploaded.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The mapper for which we want to get the timestamp.
   *
   * @return int
   *   The timestamp or NULL.
   */
  public function getConfigLastUploaded(ConfigNamesMapper $mapper);

  /**
   * Gets the timestamp for the last time the config was updated.
   *
   * @param \Drupal\config_translation\ConfigNamesMapper $mapper
   *   The mapper for which we want to get the timestamp.
   *
   * @return int
   *   The timestamp or NULL.
   */
  public function getConfigLastUpdated(ConfigNamesMapper $mapper);

  /**
   * Gets the 'initial upload' time metadata.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity for which we want the timestamp.
   *
   * @return int
   *   Returns the timestamp or NULL.
   */
  public function getLastUploaded(ConfigEntityInterface $entity);

  /**
   * Gets the 'updated date' time metadata.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity for which we want the timestamp.
   *
   * @return int
   *   Returns the timestamp or NULL.
   */
  public function getLastUpdated(ConfigEntityInterface $entity);

  /**
   * Updates the 'initial upload' time metadata to the current request time.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity to which we want to save the timestamp.
   * @param int $timestamp
   *   The timestamp we want to store.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   Returns the entity for which timestamp is saved.
   */
  public function setLastUploaded(ConfigEntityInterface $entity, int $timestamp);

  /**
   * Updates the 'updated date' time metadata to the current request time.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity to which we want to save the timestamp.
   * @param int $timestamp
   *   The timestamp we want to store.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   Returns the entity for which timestamp is saved.
   */
  public function setLastUpdated(ConfigEntityInterface $entity, int $timestamp);

}
