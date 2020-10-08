<?php

namespace Drupal\lingotek\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic menu links for Lingotek content translation management.
 */
class ContentTranslationMenuLinks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

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
    // Create menu links for all possible entity types.
    foreach ($this->lingotekConfiguration->getEnabledEntityTypes() as $entity_type_id => $entity_type) {
      $translation_route_name = "lingotek.manage.$entity_type_id";
      // If it's a paragraph, we depend on the preference.
      if ($entity_type_id === 'paragraph') {
        $config = \Drupal::config('lingotek.settings');
        $enable_bulk_management = $config->get('preference.contrib.paragraphs.enable_bulk_management', FALSE);
        if (!$enable_bulk_management) {
          // If already existed remove it.
          if (isset($this->derivatives[$translation_route_name])) {
            unset($this->derivatives[$translation_route_name]);
          }
          continue;
        }
      }
      if ($entity_type_id === 'cohesion_layout') {
        if (isset($this->derivatives[$translation_route_name])) {
          unset($this->derivatives[$translation_route_name]);
        }
        continue;
      }

      // Create the entries for the tabs in the bulk manage pages.
      $base_route_name = "lingotek.manage";
      $this->derivatives[$translation_route_name] = [
          'entity_type_id' => $entity_type_id,
          'title' => $entity_type->getLabel(),
          'route_name' => $translation_route_name,
          'base_route' => $base_route_name,
          'parent' => 'lingotek.manage',
        ] + $base_plugin_definition;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
