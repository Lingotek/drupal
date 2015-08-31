<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigurationService.
 */

namespace Drupal\lingotek;


use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Service for managing lingotek configuration.
 */
class LingotekConfigurationService implements LingotekConfigurationServiceInterface {

  /**
   * {@inheritDoc}
   */
  public function getEnabledEntityTypes() {
    $enabled = array();
    foreach (\Drupal::entityManager()->getDefinitions() as $entity_type_id => $entity_type) {
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
      $bundles = \Drupal::entityManager()->getBundleInfo($entity_type_id);
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
  public function setEnabled($entity_type_id, $bundle, $enabled = TRUE) {
    $needs_updates = $needs_router_rebuild = FALSE;
    if ($enabled && !$this->isEnabled($entity_type_id)) {
      $needs_updates = TRUE;
      $needs_router_rebuild = TRUE;
    }
    if (!$enabled && $this->isEnabled($entity_type_id)) {
      $needs_router_rebuild = TRUE;
    }
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.enabled';
    $config->set($key, $enabled);
    $config->save();

    if ($needs_updates) {
      drupal_static_reset();
      \Drupal::entityManager()->clearCachedDefinitions();
      if (\Drupal::service('entity.definition_update_manager')->needsUpdates()) {
        $storage_definitions = \Drupal::entityManager()->getFieldStorageDefinitions($entity_type_id);
        $installed_storage_definitions = \Drupal::entityManager()->getLastInstalledFieldStorageDefinitions($entity_type_id);
        foreach (array_diff_key($storage_definitions, $installed_storage_definitions) as $storage_definition) {
          /** @var $storage_definition \Drupal\Core\Field\FieldStorageDefinitionInterface */
          if ($storage_definition->getProvider() == 'lingotek') {
            \Drupal::entityManager()->onFieldStorageDefinitionCreate($storage_definition);
          }
        }
      }
    }
    if ($needs_router_rebuild) {
      \Drupal::service('router.builder')->rebuild();
    }

  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultProfileId($entity_type_id, $bundle) {
    $config = \Drupal::config('lingotek.settings');
    $profile_id = $config->get('translate.entity.' . $entity_type_id . '.' . $bundle . '.profile');
    if ($profile_id === NULL) {
      $profile_id = Lingotek::PROFILE_AUTOMATIC;
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
  public function getEntityProfile(ContentEntityInterface $entity) {
    $profile_id = $this->getDefaultProfileId($entity->getEntityTypeId(), $entity->bundle());
    if ($entity->lingotek_profile && $entity->lingotek_profile->target_id) {
      $profile_id = $entity->lingotek_profile->target_id;
    }
    return LingotekProfile::load($profile_id);
  }

  /**
   * {@inheritDoc}
   */
  public function setProfile(ContentEntityInterface &$entity, $profile_id) {
    $entity->lingotek_profile->target_id = $profile_id;
    $entity->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getProfileOptions() {
    $profiles = \Drupal::entityManager()->getListBuilder('profile')->load();
    foreach ($profiles as $profile) {
      /** \Drupal\lingotek\LingotekProfileInterface $profile */
      $options[$profile->id()] = $profile->label();
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function isFieldLingotekEnabled($entity_type_id, $bundle, $field_name) {
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.entity.' . $entity_type_id . '.' . $bundle . '.field.' . $field_name;
    return !!$config->get($key);
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
    else if (!$enabled && $config->get($key)) {
      $config->clear($key);
      $config->save();
    }
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
    $config = \Drupal::config('lingotek.settings');
    return $config->get('preference.delete_tms_documents_upon_disassociation');
  }

  /**
   * {@inheritDoc}
   */
  public function setDeleteRemoteAfterDisassociation($delete) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('preference.delete_tms_documents_upon_disassociation', $delete)->save();
  }


}