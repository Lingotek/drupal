<?php

namespace Drupal\lingotek;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Service for managing lingotek configuration.
 */
class LingotekConfigurationService implements LingotekConfigurationServiceInterface, LingotekMultipleContentConfigurationServiceInterface {

  /**
   * {@inheritDoc}
   */
  public function getEnabledEntityTypes() {
    $enabled = [];
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->isEnabled($entity_type_id)) {
        $enabled[$entity_type_id] = $entity_type;
      }
    }
    return $enabled;
  }

  /**
   * {@inheritDoc}
   */
  public function isEnabled($entity_type_id, $bundle = NULL) {
    $result = FALSE;
    $config = \Drupal::config('lingotek.settings');
    if ($bundle === NULL) {
      // Check if any bundle is enabled.
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_id => $bundle_definition) {
        $result = $this->isEnabled($entity_type_id, $bundle_id);
        if ($result) {
          break;
        }
      }
    }
    else {
      $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.enabled';
      $result = !!$config->get($key);
    }
    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function setContentTranslationSettings(array $contentData) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');

    foreach ($contentData as $entity_type_id => $entityTypeData) {
      foreach ($entityTypeData as $bundle_id => $bundleData) {
        if (isset($bundleData['enabled'])) {
          $key = 'translate.entity.' . $entity_type_id . '.' . $bundle_id . '.enabled';
          $config->set($key, $bundleData['enabled']);
        }
        if (isset($bundleData['fields'])) {
          foreach ($bundleData['fields'] as $field_id => $fieldValue) {
            if (strpos($field_id, ':properties') === FALSE) {
              $key = 'translate.entity.' . $entity_type_id . '.' . $bundle_id . '.field.' . $field_id;
              if ($fieldValue && !$config->get($key)) {
                $config->set($key, $fieldValue);
              }
              elseif (!$fieldValue && $config->get($key)) {
                $config->clear($key);
              }
            }
            else {
              // If it's properties, we set whatever we got.
              $key = 'translate.entity.' . $entity_type_id . '.' . $bundle_id . '.field.' . $field_id;
              $config->set($key, $fieldValue);
            }
          }
        }
        if (isset($bundleData['profile'])) {
          $config->set('translate.entity.' . $entity_type_id . '.' . $bundle_id . '.profile', $bundleData['profile']);
        }
      }
    }
    $config->save();
  }

  /**
   * {@inheritDoc}
   */
  public function setEnabled($entity_type_id, $bundle, $enabled = TRUE) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.enabled';
    $config->set($key, $enabled);
    $config->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigDefaultProfileId($plugin_id, $provide_default = TRUE) {
    $config = \Drupal::config('lingotek.settings');
    $profile_id = $config->get('translate.config.' . $plugin_id . '.profile');
    if ($provide_default && $profile_id === NULL) {
      $profile_id = Lingotek::PROFILE_MANUAL;
    }
    return $profile_id;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigEntityDefaultProfileId($plugin_id, $provide_default = TRUE) {
    $config = \Drupal::config('lingotek.settings');
    $profile_id = $config->get('translate.config.' . $plugin_id . '.profile');
    if ($provide_default && $profile_id === NULL) {
      $profile_id = Lingotek::PROFILE_AUTOMATIC;
    }
    return $profile_id;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfigEntityDefaultProfileId($plugin_id, $profile_id) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('translate.config.' . $plugin_id . '.profile', $profile_id);
    $config->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultProfileId($entity_type_id, $bundle, $provide_default = TRUE) {
    $config = \Drupal::config('lingotek.settings');
    $profile_id = $config->get('translate.entity.' . $entity_type_id . '.' . $bundle . '.profile');
    if ($provide_default && $profile_id === NULL && $this->isEnabled($entity_type_id, $bundle)) {
      if (in_array($entity_type_id, ['paragraph', 'cohesion_layout'])) {
        $profile_id = Lingotek::PROFILE_DISABLED;
      }
      else {
        $profile_id = Lingotek::PROFILE_AUTOMATIC;
      }
    }
    return $profile_id;
  }

  /**
   * {@inheritDoc}
   */
  public function setDefaultProfileId($entity_type_id, $bundle, $profile_id) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('translate.entity.' . $entity_type_id . '.' . $bundle . '.profile', $profile_id);
    $config->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigProfile($plugin_id, $provide_default = TRUE) {
    $profile_id = NULL;
    /** @var \Drupal\config_translation\ConfigMapperManager $mapper_manager */
    $mapper_manager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mappers = $mapper_manager->getMappers();
    $mapper = isset($mappers[$plugin_id]) ? $mappers[$plugin_id] : NULL;
    if ($mapper !== NULL) {
      $config_names = $mapper->getConfigNames();
      foreach ($config_names as $config_name) {
        $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
        $profile_id = $metadata->getProfile();
        if ($profile_id === NULL) {
          $profile_id = $this->getConfigDefaultProfileId($plugin_id, $provide_default);
        }
      }
    }
    return $profile_id ? LingotekProfile::load($profile_id) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigEntityProfile(ConfigEntityInterface $entity, $provide_default = TRUE) {
    $entity_type_id = $entity->getEntityTypeId();
    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata $metadata */
    $metadata = LingotekConfigMetadata::loadByConfigName($entity_type_id . '.' . $entity->id());
    $profile_id = $metadata->getProfile();
    if ($profile_id === NULL) {
      // Use mapper id.
      $mapper_id = $entity_type_id;
      if ($entity instanceof FieldConfig) {
        $mapper_id = $entity->getTargetEntityTypeId() . '_fields';
      }
      $profile_id = $this->getConfigEntityDefaultProfileId($mapper_id, $provide_default);
    }
    return $profile_id ? LingotekProfile::load($profile_id) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityProfile(ContentEntityInterface $entity, $provide_default = TRUE) {
    $default_profile_id = $this->getDefaultProfileId($entity->getEntityTypeId(), $entity->bundle(), $provide_default);
    $profile_id = $default_profile_id;
    if ($entity->lingotek_metadata !== NULL && $entity->lingotek_metadata->entity !== NULL) {
      if ($entity->lingotek_metadata->entity->getProfile() !== NULL) {
        $profile_id = $entity->lingotek_metadata->entity->getProfile();
      }
      else {
        // If we have a NULL profile set on the entity and we don't want to
        // provide a default, let's respect that.
        $profile_id = $provide_default ? $profile_id : NULL;
      }
    }
    $profile = $profile_id ? LingotekProfile::load($profile_id) : NULL;
    if ($profile === NULL && $provide_default) {
      $profile = $default_profile_id ? LingotekProfile::load($default_profile_id) : NULL;
    }
    if ($profile === NULL && $provide_default) {
      // If we still didn't get a profile, return an agnostic profile that won't
      // auto upload or auto download anything.
      $profile = LingotekProfile::create([]);
    }

    // Allow other modules to alter the calculated profile.
    \Drupal::moduleHandler()->invokeAll('lingotek_content_entity_get_profile', [$entity, &$profile, $provide_default]);

    return $profile;
  }

  /**
   * {@inheritDoc}
   */
  public function setProfile(ContentEntityInterface &$entity, $profile_id, $save = TRUE) {
    // ToDo: deprecate second argument?
    // If it's the first save of this content, we don't have an ID yet. Wait for
    // saving yet.
    if (!$entity->lingotek_metadata->entity) {
      $entity->lingotek_metadata->entity = LingotekContentMetadata::create();
    }
    $entity->lingotek_metadata->entity->setProfile($profile_id);
    $entity->lingotek_metadata->entity->save();
  }

  /**
   * {@inheritDoc}
   */
  public function setConfigEntityProfile(ConfigEntityInterface &$entity, $profile_id, $save = TRUE) {
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $metadata->setProfile($profile_id);
    if ($save) {
      $metadata->save();
    }
    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfigProfile($mapper_id, $profile_id, $save = TRUE) {
    /** @var \Drupal\config_translation\ConfigMapperManager $mapper_manager */
    $mapper_manager = \Drupal::service('plugin.manager.config_translation.mapper');
    $mappers = $mapper_manager->getMappers();
    $mapper = $mappers[$mapper_id];
    $config_names = $mapper->getConfigNames();
    foreach ($config_names as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $metadata->setProfile($profile_id);
      $metadata->save();
    }
    return $mapper;
  }

  /**
   * {@inheritDoc}
   */
  public function getProfileOptions() {
    $profiles = \Drupal::entityTypeManager()->getListBuilder('lingotek_profile')->load();
    foreach ($profiles as $profile) {
      /** \Drupal\lingotek\LingotekProfileInterface $profile */
      $options[$profile->id()] = $profile->label();
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldsLingotekEnabled($entity_type_id, $bundle) {
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.field';
    $data = $config->get($key);
    $fields = [];
    foreach ($data as $field_name => $properties) {
      if ($properties == 1) {
        $fields[] = $field_name;
      }
      if (is_array($properties)) {
        $fields[] = substr($field_name, 0, strpos($field_name, ':properties'));
      }
    }
    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function isFieldLingotekEnabled($entity_type_id, $bundle, $field_name) {
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.field.' . $field_name;

    // We allow non-translatable entity_reference_revisions fields through.
    // See https://www.drupal.org/node/2788285
    // We also support paths even if they are not translatable (which may happen
    // if they are computed fields.
    $excluded_types = ['path', 'entity_reference_revisions', 'cohesion_entity_reference_revisions'];
    return (!empty($field_definitions[$field_name])
      && ($field_definitions[$field_name]->isTranslatable() || (in_array($field_definitions[$field_name]->getType(), $excluded_types)))
      && !!$config->get($key));
  }

  /**
   * {@inheritDoc}
   */
  public function shouldFieldLingotekEnabled($entity_type_id, $bundle, $field_name) {
    $should = FALSE;
    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_type_id, $bundle);
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.field.' . $field_name;
    $saved_value = $config->get($key);

    $default_enabled = [
      'string',
      'image',
      'text',
      'text_with_summary',
      'text_long',
      'string_long',
      'path',
      'entity_reference_revisions',
      'cohesion_entity_reference_revisions',
    ];
    if ($saved_value === NULL) {
      $type = $field_definitions[$field_name]->getType();
      $should = !empty($field_definitions[$field_name])
        && in_array($type, $default_enabled);
    }
    return $should;
  }

  /**
   * {@inheritDoc}
   */
  public function setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, $enabled = TRUE) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.field.' . $field_name;
    if ($enabled && !$config->get($key)) {
      $config->set($key, $enabled);
      $config->save();
    }
    elseif (!$enabled && $config->get($key)) {
      $config->clear($key);
      $config->save();
    }
  }

  public function getDefaultFieldPropertiesLingotekEnabled($entity_type_id, $bundle, $field_name) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_type_id, $bundle);
    $field_definition = $field_definitions[$field_name];
    $definition = \Drupal::service('plugin.manager.field.field_type')
      ->getDefinition($field_definition->getType());
    $column_groups = $definition['column_groups'];
    $properties = [];
    foreach ($column_groups as $property_id => $property) {
      if (isset($property['translatable']) && $property['translatable']) {
        $property_definitions = $definition['class']::propertyDefinitions($field_definition->getFieldStorageDefinition());
        if (isset($property_definitions[$property_id])) {
          $property_definition = $property_definitions[$property_id];
          if ($property_definition['type'] === 'string') {
            $properties[$property_id] = $property_id;
          }
          else {
            $properties[$property_id] = FALSE;
          }
        }
      }
    }
    return $properties;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldPropertiesLingotekEnabled($entity_type_id, $bundle, $field_name) {
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.field.' . $field_name . ':properties';
    return $config->get($key);
  }

  /**
   * {@inheritDoc}
   */
  public function setFieldPropertiesLingotekEnabled($entity_type_id, $bundle, $field_name, array $properties) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.field.' . $field_name . ':properties';
    $config->set($key, $properties);
    $config->save();
  }

  /**
   * {@inheritDoc}
   */
  public function mustDeleteRemoteAfterDisassociation() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function setDeleteRemoteAfterDisassociation($delete) {
    // Do nothing.
  }

  /**
   * {@inheritDoc}
   */
  public function getPreference($preference_id) {
    $config = \Drupal::config('lingotek.settings');
    return $config->get('preference.' . $preference_id);
  }

  /**
   * {@inheritDoc}
   */
  public function setPreference($preference_id, $value) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('preference.' . $preference_id, $value)->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getEnabledLanguages() {
    $languages = \Drupal::languageManager()->getLanguages();
    // Filter the disabled languages.
    $languages = array_filter($languages, function (LanguageInterface $language) {
      $configLanguage = ConfigurableLanguage::load($language->getId());
      return $this->isLanguageEnabled($configLanguage);
    });
    return $languages;
  }

  /**
   * {@inheritDoc}
   */
  public function isLanguageEnabled(ConfigurableLanguageInterface $language) {
    return !$language->getThirdPartySetting('lingotek', 'disabled', FALSE);
  }

  /**
   * {@inheritDoc}
   */
  public function enableLanguage(ConfigurableLanguageInterface $language) {
    $language->setThirdPartySetting('lingotek', 'disabled', FALSE)->save();
  }

  /**
   * {@inheritDoc}
   */
  public function disableLanguage(ConfigurableLanguageInterface $language) {
    $language->setThirdPartySetting('lingotek', 'disabled', TRUE)->save();
  }

}
