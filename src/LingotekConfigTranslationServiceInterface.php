<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigTranslationServiceInterface.
 */

namespace Drupal\lingotek;
use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\config_translation\ConfigNamesMapper;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\lingotek\Entity\LingotekProfile;

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
   * @param ConfigEntityInterface &$entity
   *   The entity which status we want to check.
   *
   * @return int
   *   The status of the target translation (see Lingotek class constants)
   */
  public function getSourceStatus(ConfigEntityInterface &$entity);

  /**
   * Sets the translation status of a given entity.
   *
   * @param ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek class constants.
   *
   * @return ConfigEntityInterface
   */
  public function setSourceStatus(ConfigEntityInterface &$entity, $status);

  /**
   * Gets the translation status of a given entity translation for a locale.
   *
   * @param ConfigEntityInterface &$entity
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
   * @param ConfigEntityInterface &$entity
   *   The entity which statuses we want to get.
   *
   * @return array
   *   The status of the target translations (see Lingotek class constants)
   */
  public function getTargetStatuses(ConfigEntityInterface &$entity);

  /**
   * Sets the translation status of a given entity translation for a locale.
   *
   * @param ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   * @param bool $save
   *   If FALSE, the entity is not saved yet. Defaults to TRUE.
   *
   * @return ConfigEntityInterface
   */
  public function setTargetStatus(ConfigEntityInterface &$entity, $locale, $status, $save = TRUE);

  /**
   * Sets the translation status of all translations of a given entity.
   *
   * @param ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return ConfigEntityInterface
   */
  public function setTargetStatuses(ConfigEntityInterface &$entity, $status);

  /**
   * Marks the translation status as dirty if they exist.
   *
   * @param ConfigEntityInterface &$entity
   *   The entity which status we want to change.
   *
   * @return ConfigEntityInterface
   */
  public function markTranslationsAsDirty(ConfigEntityInterface &$entity);

  /**
   * Checks if the source entity data has changed from last time we uploaded it.
   *
   * @param ConfigEntityInterface &$entity
   *   The entity being checked
   *
   * @return boolean
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
   *
   * @return boolean
   *   TRUE if the document was uploaded successfully, FALSE if not.
   */
  public function uploadDocument(ConfigEntityInterface $entity);

  /**
   * Checks the source is uploaded correctly.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which status we want to check.
   *
   * @return boolean
   *   True if the entity is uploaded succesfully.
   */
  public function checkSourceStatus(ConfigEntityInterface &$entity);

  /**
   * Resends a document to the translation service.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being updated.
   *
   * @return boolean
   *   TRUE if the document was updated successfully, FALSE if not.
   */
  public function updateDocument(ConfigEntityInterface &$entity);

  /**
   * Request a translation for a given entity in the given locale.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which target we want to add.
   * @param string $locale
   *   Lingotek translation language which we want to modify.
   */
  public function addTarget(ConfigEntityInterface &$entity, $locale);

  /**
   * Requests translations of a document in all the enabled locales.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being requested for translations.
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
   * @return boolean
   *   True if the entity is checked successfully.
   */
  public function checkTargetStatus(ConfigEntityInterface &$entity, $locale);

  /**
   * Checks the status of all the translations in the Lingotek service.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which status we want to check.
   *
   * @return boolean
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
   * @return boolean
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadDocument(ConfigEntityInterface $entity, $locale);

  /**
   * Deletes a document from the server and all related local data.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity which we want to delete.
   *
   * @return ContentEntityInterface
   *   The entity.
   */
  public function deleteDocument(ConfigEntityInterface &$entity);

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
   * @param ConfigNamesMapper $mapper
   *   The entity which we want the document id.
   *
   * @return string
   *   The document id in the Lingotek platform.
   */
  public function getConfigDocumentId(ConfigNamesMapper $mapper);

  /**
   * Sets the document id in the Lingotek platform for a given entity.
   *
   * @param ConfigNamesMapper $mapper
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
   * @param ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   *
   * @return int
   *   Status of the source. Use Lingotek class constants.
   */
  public function getConfigSourceStatus(ConfigNamesMapper $mapper);

  /**
   * Sets the translation status of a given entity.
   *
   * @param ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek class constants.
   *
   * @return ConfigEntityInterface
   */
  public function setConfigSourceStatus(ConfigNamesMapper $mapper, $status);

  /**
   * Gets the translation status of a given entity translation for all locales.
   *
   * @param ConfigNamesMapper $mapper
   *   The entity which status we want to get.
   *
   * @return array
   *   The status of the target translations (see Lingotek class constants)
   */
  public function getConfigTargetStatuses(ConfigNamesMapper $mapper);

  /**
   * Gets the translation status of a given entity translation for a locale.
   *
   * @param ConfigNamesMapper $mapper
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
   * @param ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   * @param string $locale
   *   Lingotek translation language which we want to get.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return ConfigEntityInterface
   */
  public function setConfigTargetStatus(ConfigNamesMapper $mapper, $locale, $status);

  /**
   * Sets the translation status of all translations of a given entity.
   *
   * @param ConfigNamesMapper $mapper
   *   The entity which status we want to change.
   * @param int $status
   *   Status of the translation. Use Lingotek constants.
   *
   * @return ConfigEntityInterface
   */
  public function setConfigTargetStatuses(ConfigNamesMapper $mapper, $status);

  /**
   * Gets the translation source locale of a given entity.
   *
   * @param ConfigNamesMapper $mapper
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
   * @param ConfigNamesMapper $mapper
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
   *
   * @return boolean
   *   TRUE if the document was uploaded successfully, FALSE if not.
   */
  public function uploadConfig($mapper_id);

  /**
   * Checks the source is uploaded correctly.
   *
   * @param string $mapper_id
   *   The entity which status we want to check.
   *
   * @return boolean
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
   */
  public function addConfigTarget($mapper_id, $locale);

  /**
   * Request all translations for a given mapper in all locales.
   *
   * @param string $mapper_id
   *   The entity which target we want to add.
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
   * @return boolean
   *   True if the entity is available for download.
   */
  public function checkConfigTargetStatus($mapper_id, $locale);

  /**
   * Checks the status of the translations in the Lingotek service.
   *
   * @param string $mapper_id
   *   The entity which status we want to check.
   *
   * @return boolean
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
   * @return boolean
   *   TRUE if the document was downloaded successfully, FALSE if not.
   */
  public function downloadConfig($mapper_id, $locale);

  /**
   * Deletes a document from the server and all related local data.
   *
   * @param string $mapper_id
   *   The entity being uploaded.
   *
   */
  public function deleteConfigDocument($mapper_id);

  /**
   * Deletes metadata.
   *
   * @param string $mapper_id
   *   The entity being uploaded.
   *
   */
  public function deleteConfigMetadata($mapper_id);

  /**
   * Resends a document to the translation service.
   *
   * @param $mapper_id
   *   The entity being updated.
   *
   * @return boolean
   *   TRUE if the document was updated successfully, FALSE if not.
   */
  public function updateConfig($mapper_id);

  /**
   * Marks the translation status as dirty if they exist.
   *
   * @param ConfigNamesMapper $mapper
   *   The mapper which status we want to change.
   *
   * @return ConfigNamesMapper
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

}
