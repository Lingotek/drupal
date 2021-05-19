<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @RelatedEntitiesDetector (
 *   id = "field_link_detector",
 *   title = @Translation("Get editor linked entities with html links"),
 *   description = @translation("Get editor linked entities with html links."),
 *   weight = 7,
 * )
 */
class FieldLinkDetector extends PluginBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $fieldTypes = ["link"];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * NestedEntityReferences constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfiguration
   *   The Lingotek configuration service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, LingotekConfigurationServiceInterface $lingotekConfiguration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->lingotekConfiguration = $lingotekConfiguration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('lingotek.configuration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, array &$entities, array &$related, $depth, array $visited) {
    $visited[$entity->bundle()][] = $entity->id();
    $entities[$entity->getEntityTypeId()][$entity->id()] = $entity->getUntranslated();
    if ($depth > 0) {
      --$depth;
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
      foreach ($field_definitions as $k => $definition) {
        $field_type = $field_definitions[$k]->getType();
        if (in_array($field_type, $this->fieldTypes)) {
          foreach ($entity->get($k) as $item) {
            $target = $this->getTargetEntities($item);
            if (!empty($target)) {
              [$target_entity_type_id, $target_id] = $target;
              $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
              if ($target_entity_type instanceof ContentEntityType) {
                $referencedEntity = $this->entityTypeManager->getStorage($target_entity_type_id)
                  ->load($target_id);
                if ($referencedEntity !== NULL) {
                  // We need to avoid cycles if we have several entity references
                  // referencing each other.
                  if (!isset($visited[$referencedEntity->bundle()]) || !in_array($referencedEntity->id(), $visited[$referencedEntity->bundle()])) {
                    if ($referencedEntity instanceof ContentEntityInterface && $referencedEntity->isTranslatable() && $this->lingotekConfiguration->isEnabled($referencedEntity->getEntityTypeId(), $referencedEntity->bundle())) {
                      if (!$this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $k)) {
                        $entities = $this->extract($referencedEntity, $entities, $related, $depth, $visited);
                      }
                      else {
                        $related[$referencedEntity->getEntityTypeId()][$referencedEntity->id()] = $referencedEntity->getUntranslated();
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    return $entities;
  }

  /**
   * Get the target entity of a given link.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $link
   *   The link field item.
   *
   * @return array
   *   Array of (target type, target id). Empty if no entity was linked.
   */
  public function getTargetEntities(FieldItemInterface $link) {
    /** @var \Drupal\link\LinkItemInterface $link */
    // Check if the link is referencing an entity.
    $url = $link->getUrl();
    if (!$url->isRouted() || !preg_match('/^entity\./', $url->getRouteName())) {
      return [];
    }

    // Ge the target entity type and ID.
    $route_parameters = $url->getRouteParameters();
    $target_type = array_keys($route_parameters)[0];
    $target_id = $route_parameters[$target_type];

    // Only return a valid result if the target entity exists.
    try {
      if (!$this->entityTypeManager->getStorage($target_type)->load($target_id)) {
        return [];
      }
    }
    catch (\Exception $exception) {
      return [];
    }

    return [$target_type, $target_id];
  }

}
