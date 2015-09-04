<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigTranslationService.
 */

namespace Drupal\lingotek;

use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Service for managing Lingotek configuration translations.
 */
class LingotekConfigTranslationService implements LingotekConfigTranslationServiceInterface {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * A array of configuration mapper instances.
   *
   * @var \Drupal\config_translation\ConfigMapperInterface[]
   */
  protected $mappers;

  /**
   * Constructs a new LingotekConfigTranslationService object.
   *
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $mapper_manager
   *   The configuration mapper manager.
   */
  public function __construct(LingotekConfigurationServiceInterface $lingotek_configuration, ConfigMapperManagerInterface $mapper_manager) {
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->mappers = $mapper_manager->getMappers();
  }

  /**
   * {@inheritDoc}
   */
  public function getEnabledConfigTypes() {
    $mappers = array();
    // ToDo: Implement.
    foreach ($this->mappers as $mapper) {
      if ($row = $this->buildRow($mapper)) {
        $mappers[$mapper->getWeight()][] = $row;
      }
    }
  }


  /**
   * {@inheritDoc}
   */
  public function isEnabled($plugin_id) {
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.config.' . $plugin_id  . '.enabled';
    $result = !!$config->get($key);
    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function setEnabled($plugin_id, $enabled = TRUE) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.config.' . $plugin_id  . '.enabled';
    $config->set($key, $enabled)->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultProfile($plugin_id) {
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.config.' . $plugin_id  . '.profile';
    $profile_id = $config->get($key);
    return $profile_id ? LingotekProfile::load($profile_id) : LingotekProfile::load(Lingotek::PROFILE_AUTOMATIC);
  }

  /**
   * {@inheritDoc}
   */
  public function setDefaultProfile($plugin_id, $profile_id) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.config.' . $plugin_id  . '.profile';
    $config->set($key, $profile_id)->save();
  }

}
