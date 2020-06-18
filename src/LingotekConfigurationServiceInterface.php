<?php

namespace Drupal\lingotek;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\language\ConfigurableLanguageInterface;

/**
 * Defines service for accessing the Lingotek configuration.
 */
interface LingotekConfigurationServiceInterface {

  /**
   * Gets the entity types that are enabled for Lingotek content translation.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types that are enabled for Lingotek content translation.
   */
  public function getEnabledEntityTypes();

  /**
   * Determines whether the given entity type is Lingotek translatable.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   (optional) The bundle of the entity. If no bundle is provided, all the
   *   available bundles are checked.
   *
   * @returns bool
   *   TRUE if the specified bundle is configured for Lingotek translation.
   *   If no bundle is provided returns TRUE if at least one of the entity
   *   bundles is translatable.
   */
  public function isEnabled($entity_type_id, $bundle = NULL);

  /**
   * Sets whether the given entity type is Lingotek translatable.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   * @param bool $enabled
   *   Flag. Defaults to TRUE.
   */
  public function setEnabled($entity_type_id, $bundle, $enabled = TRUE);

  /**
   * Determines the default Lingotek profile for the given config object.
   *
   * @param string $plugin_id
   *   The type of the config.
   * @param bool $provide_default
   *   If TRUE, and the entity does not have a profile, will retrieve the default
   *   for this entity type and bundle. Defaults to TRUE.
   *
   * @returns string
   *   The profile id.
   */
  public function getConfigDefaultProfileId($plugin_id, $provide_default = TRUE);

  /**
   * Determines the default Lingotek profile for the given config entity type.
   *
   * @param string $plugin_id
   *   The type of the entity.
   * @param bool $provide_default
   *   If TRUE, and the entity does not have a profile, will retrieve the default
   *   for this entity type and bundle. Defaults to TRUE.
   *
   * @returns string
   *   The profile id.
   */
  public function getConfigEntityDefaultProfileId($plugin_id, $provide_default = TRUE);

  /**
   * Determines the default Lingotek profile for the given entity type.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   *
   * @returns string
   *   The profile id.
   */
  public function getDefaultProfileId($entity_type_id, $bundle);

  /**
   * Sets the default Lingotek profile for the given entity type.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   * @param string $profile_id
   *   The profile id.
   */
  public function setDefaultProfileId($entity_type_id, $bundle, $profile_id);

  /**
   * Determines the default Lingotek profile for the given entity.
   *
   * @param string $plugin_id
   *   The config plugin id.
   * @param bool $provide_default
   *   If TRUE, and the entity does not have a profile, will retrieve the default
   *   for this entity type and bundle. Defaults to TRUE.
   *
   * @returns \Drupal\lingotek\Entity\LingotekProfile
   *   The default profile.
   */
  public function getConfigProfile($plugin_id, $provide_default = TRUE);

  /**
   * Determines the default Lingotek profile for the given entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity.
   * @param bool $provide_default
   *   If TRUE, and the entity does not have a profile, will retrieve the default
   *   for this entity type and bundle. Defaults to TRUE.
   *
   * @returns \Drupal\lingotek\Entity\LingotekProfile
   *   The default profile.
   */
  public function getConfigEntityProfile(ConfigEntityInterface $entity, $provide_default = TRUE);

  /**
   * Sets the default Lingotek profile for the given config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity.
   * @param string $profile_id
   *   The profile id.
   * @param bool $save
   *   Indicates if we should save the entity after setting the value.
   */
  public function setConfigEntityProfile(ConfigEntityInterface &$entity, $profile_id, $save = TRUE);

  /**
   * Determines the default Lingotek profile for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param bool $provide_default
   *   If TRUE, and the entity does not have a profile, will retrieve the default
   *   for this entity type and bundle. Defaults to TRUE.
   *
   * @returns \Drupal\lingotek\LingotekProfileInterface
   *   The default profile.
   */
  public function getEntityProfile(ContentEntityInterface $entity, $provide_default = TRUE);

  /**
   * Sets the default Lingotek profile for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $profile_id
   *   The profile id.
   * @param bool $save
   *   Indicates if we should save the entity after setting the value.
   */
  public function setProfile(ContentEntityInterface &$entity, $profile_id, $save = TRUE);

  /**
   * Helper function for getting all the profiles as select options.
   *
   * @return array
   *   Profiles as a valid select options property.
   */
  public function getProfileOptions();

  /**
   * Gets the Lingotek enabled fields for a given bundle.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   *
   * @returns array
   *   Array of field names.
   */
  public function getFieldsLingotekEnabled($entity_type_id, $bundle);

  /**
   * Determines if the field is enabled for Lingotek translation.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   * @param string $field_name
   *   The field of the bundle.
   *
   * @returns bool
   *   TRUE if the specified field is configured for Lingotek translation.
   */
  public function isFieldLingotekEnabled($entity_type_id, $bundle, $field_name);

  /**
   * Sets the field as enabled for Lingotek translation.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   * @param string $field_name
   *   The field of the bundle.
   * @param bool $enabled
   *   Flag. Defaults to TRUE.
   */
  public function setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, $enabled = TRUE);

  /**
   * Gets the configured properties of a field that are enabled for Lingotek translation.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   * @param string $field_name
   *   The field of the bundle.
   *
   * @returns string[]
   *   Field properties that are enabled.
   */
  public function getFieldPropertiesLingotekEnabled($entity_type_id, $bundle, $field_name);

  /**
   * Sets the configured properties of a field that are enabled for Lingotek translation.
   *
   * @param string $entity_type_id
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   * @param string $field_name
   *   The field of the bundle.
   * @param string[] $properties
   *   Field properties that are enabled.
   */
  public function setFieldPropertiesLingotekEnabled($entity_type_id, $bundle, $field_name, array $properties);

  /**
   * Gets the value from the preferences configuration.
   *
   * @param string $preference_id
   *   The preference ID.
   *
   * @return mixed
   */
  public function getPreference($preference_id);

  /**
   * Sets the value for the preferences configuration.
   *
   * @param string $preference_id
   *   The preference ID.
   * @param mixed $value
   *   The preference value.
   */
  public function setPreference($preference_id, $value);

  /**
   * Returns all the languages that are Lingotek enabled.
   *
   * @return \Drupal\language\ConfigurableLanguageInterface[]
   *   The languages.
   */
  public function getEnabledLanguages();

  /**
   * Checks if a language is enabled in the Lingotek interface.
   *
   * @param \Drupal\language\ConfigurableLanguageInterface $language
   *   The language to check.
   *
   * @return bool
   *   TRUE if the language is enabled. FALSE if it is disabled.
   */
  public function isLanguageEnabled(ConfigurableLanguageInterface $language);

  /**
   * Enables a language from the Lingotek interface.
   *
   * @param \Drupal\language\ConfigurableLanguageInterface $language
   *   The language to be enabled.
   */
  public function enableLanguage(ConfigurableLanguageInterface $language);

  /**
   * Disables a language from the Lingotek interface.
   *
   * @param \Drupal\language\ConfigurableLanguageInterface $language
   *   The language to be disabled.
   */
  public function disableLanguage(ConfigurableLanguageInterface $language);

}
