<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigTranslationServiceInterface.
 */

namespace Drupal\lingotek;
use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigMapperManagerInterface;
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
   * Gets the default profile for a config entity.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   *
   * @return LingotekProfile
   *   The Lingotek profile.
   */
  public function getDefaultProfile($plugin_id);

  /**
   * Sets the default profile for a config entity.
   *
   * @param string $plugin_id
   *   The config entity plugin id.
   * @param string $profile_id
   *   The profile id.
   */
  public function setDefaultProfile($plugin_id, $profile_id);

  /**
   * Gets the configuration translatable properties of the given mapper.
   *
   * @param \Drupal\config_translation\ConfigEntityMapper $mapper
   *   The mapper.
   * @return array
   *   Canonical names of the translatable properties.
   */
  public function getConfigTranslatableProperties(ConfigEntityMapper $mapper);

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

}
