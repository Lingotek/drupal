<?php

/**
 * @file
 * Contains \Drupal\lingotek\Plugin\Derivative\ContentTranslationLocalTasks.
 */

namespace Drupal\lingotek\Plugin\Derivative;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
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
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * Constructs a new ContentTranslationLocalTasks.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   */
  public function __construct($base_plugin_id, ContentTranslationManagerInterface $content_translation_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->contentTranslationManager = $content_translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('content_translation.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Create tabs for all possible entity types.
    foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      if (\Drupal::config('lingotek.settings')->get('translate.entity.' . $entity_type_id)) {

        // Find the route name for the translation overview.
        $translation_route_name = "lingotek.manage.$entity_type_id";

        $base_route_name = "lingotek.manage";
        $this->derivatives[$translation_route_name] = array(
            'entity_type_id' => $entity_type_id,
            'title' => $entity_type->getLabel(),
            'route_name' => $translation_route_name,
            'base_route' => $base_route_name,
          ) + $base_plugin_definition;
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
