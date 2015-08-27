<?php

/**
 * @file
 * Contains \Drupal\lingotek\Plugin\Derivative\ContentTranslationLocalTasks.
 */

namespace Drupal\lingotek\Plugin\Derivative;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic local tasks for Lingotek content translation management.
 */
class ContentTranslationLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The base plugin ID
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Constructs a new ContentTranslationLocalTasks.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct($base_plugin_id, LingotekConfigurationServiceInterface $lingotek_configuration) {
    $this->basePluginId = $base_plugin_id;
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('lingotek.configuration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Create tabs for all possible entity types.
    foreach ($this->lingotekConfiguration->getEnabledEntityTypes() as $entity_type_id => $entity_type) {
      $translation_route_name = "lingotek.manage.$entity_type_id";

      $base_route_name = "lingotek.manage";
      $this->derivatives[$translation_route_name] = array(
          'entity_type_id' => $entity_type_id,
          'title' => $entity_type->getLabel(),
          'route_name' => $translation_route_name,
          'base_route' => $base_route_name,
          'id' => $translation_route_name,
        ) + $base_plugin_definition;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
