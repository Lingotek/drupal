<?php

namespace Drupal\lingotek;

use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;

/**
 * Service for checking Lingotek profiles usage.
 */
class LingotekProfileUsage implements LingotekProfileUsageInterface {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * The entity query factory service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * The configuration mapper manager.
   *
   * @var \Drupal\config_translation\ConfigMapperManagerInterface
   */
  protected $configMapperManager;

  /**
   * Constructs a new LingotekProfileUsage object.
   *
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query factory.
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $config_mapper_manager
   *   The configuration mapper manager.
   */
  public function __construct(LingotekConfigurationServiceInterface $lingotek_configuration, QueryFactory $entity_query, ConfigMapperManagerInterface $config_mapper_manager) {
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->entityQuery = $entity_query;
    $this->configMapperManager = $config_mapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsedByContent(LingotekProfileInterface $profile) {
    $entity_query = $this->entityQuery->get('lingotek_content_metadata');
    $entity_query->condition('profile', $profile->id());
    $result = $entity_query->count()->execute();
    $used = ($result > 0) ? LingotekProfileUsageInterface::USED_BY_CONTENT : LingotekProfileUsageInterface::UNUSED;
    return $used;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsedByConfig(LingotekProfileInterface $profile) {
    $mappers = $this->configMapperManager->getMappers();

    $used = LingotekProfileUsageInterface::UNUSED;
    foreach ($mappers as $plugin_id => $mapper) {
      $config_profile = $this->lingotekConfiguration->getConfigProfile($plugin_id, FALSE);
      if ($config_profile !== NULL && $config_profile->id() === $profile->id()) {
        $used |= LingotekProfileUsageInterface::USED_BY_CONFIG;
      }
    }

    if ($used !== LingotekProfileUsageInterface::USED_BY_CONFIG) {
      $entity_query = $this->entityQuery->get('lingotek_config_metadata');
      $entity_query->condition('profile', $profile->id());
      $result = $entity_query->count()->execute();
      $used = ($result > 0) ? LingotekProfileUsageInterface::USED_BY_CONFIG : LingotekProfileUsageInterface::UNUSED;
    }

    return $used;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsedByContentSettings(LingotekProfileInterface $profile) {
    $entity_types = $this->lingotekConfiguration->getEnabledEntityTypes();

    $used = LingotekProfileUsageInterface::UNUSED;
    foreach ($entity_types as $entity_type_id => $entity_type_definition) {
      $bundles = \Drupal::entityManager()->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_id => $bundle_definition) {
        $config_profile = $this->lingotekConfiguration->getDefaultProfileId($entity_type_id, $bundle_id);
        if ($config_profile === $profile->id()) {
          $used |= LingotekProfileUsageInterface::USED_BY_SETTINGS;
        }
      }
    }

    return $used;
  }

}
