<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigurationService.
 */

namespace Drupal\lingotek;


use Drupal\Core\Config\Entity\ConfigEntityInterface;
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
      $profile_id = Lingotek::PROFILE_AUTOMATIC;
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
    if ($provide_default && $profile_id === NULL) {
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
  public function getConfigProfile($plugin_id, $provide_default = TRUE) {
    $profile_id = $this->getConfigDefaultProfileId($plugin_id, $provide_default);
    return $profile_id ? LingotekProfile::load($profile_id) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigEntityProfile(ConfigEntityInterface $entity, $provide_default = TRUE) {
    $entity_type_id = $entity->getEntityTypeId();
    if ('field_config' === $entity_type_id) {
      $entity_type_id = $entity->getTargetEntityTypeId() . '_fields';
    }
    $profile_id = $this->getConfigEntityDefaultProfileId($entity_type_id, $provide_default);
    return $profile_id ? LingotekProfile::load($profile_id) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityProfile(ContentEntityInterface $entity, $provide_default = TRUE) {
    $profile_id = $this->getDefaultProfileId($entity->getEntityTypeId(), $entity->bundle(), $provide_default);
    if ($entity->lingotek_profile && $entity->lingotek_profile->target_id) {
      $profile_id = $entity->lingotek_profile->target_id;
    }
    return $profile_id ? LingotekProfile::load($profile_id) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function setProfile(ContentEntityInterface &$entity, $profile_id, $save = TRUE) {
    $entity->lingotek_profile->target_id = $profile_id;
    if ($save) {
      $entity->save();
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getProfileOptions() {
    $profiles = \Drupal::entityManager()->getListBuilder('lingotek_profile')->load();
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