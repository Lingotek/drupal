<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityReferenceDetectorBase extends PluginBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

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
          $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()
            ->getSetting('target_type');
          $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
          if ($target_entity_type instanceof ContentEntityType) {
            $child_entities = $entity->get($k)->referencedEntities();
            foreach ($child_entities as $embedded_entity) {
              if ($embedded_entity !== NULL) {
                // We need to avoid cycles if we have several entity references
                // referencing each other.
                if (!isset($visited[$embedded_entity->bundle()]) || !in_array($embedded_entity->id(), $visited[$embedded_entity->bundle()])) {
                  if ($embedded_entity instanceof ContentEntityInterface && $embedded_entity->isTranslatable() && $this->lingotekConfiguration->isEnabled($embedded_entity->getEntityTypeId(), $embedded_entity->bundle())) {
                    if (!$this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $k)) {
                      $entities = $this->extract($embedded_entity, $entities, $related, $depth, $visited);
                    }
                    else {
                      $related[$embedded_entity->getEntityTypeId()][$embedded_entity->id()] = $embedded_entity->getUntranslated();
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

}
