<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @RelatedEntitiesDetector (
 *   id = "nested_layout_builder_entities_detector",
 *   title = @Translation("Get related nested layout builder content"),
 *   description = @translation("The default retrieval of nested layout builder content"),
 *   weight = 5,
 * )
 */
class NestedLayoutBuilderEntitiesDetector extends PluginBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Nested layoutBuilderDetector constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin-id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfiguration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, LingotekConfigurationServiceInterface $lingotekConfiguration, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->lingotekConfiguration = $lingotekConfiguration;
    $this->moduleHandler = $moduleHandler;
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
      $container->get('lingotek.configuration'),
      $container->get('module_handler')
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
      $layoutBuilderAT = $this->moduleHandler->moduleExists('layout_builder_at');
      $layoutBuilderST = $this->moduleHandler->moduleExists('layout_builder_st');
      foreach ($field_definitions as $k => $definition) {
        $field_type = $field_definitions[$k]->getType();
        if ($field_type === 'layout_translation' &&  $layoutBuilderST || $field_type === 'layout_section' && $layoutBuilderAT) {
          $blockContentRevisionIds = [];
          $block_manager = \Drupal::service('plugin.manager.block');
          $layoutField = $entity->get(OverridesSectionStorage::FIELD_NAME);
          $layout = $layoutField->getValue();
          /** @var \Drupal\layout_builder\Section $sectionObject */
          foreach ($layout as $section) {
            $sectionObject = $section['section'];
            $components = $sectionObject->getComponents();
            /** @var \Drupal\layout_builder\SectionComponent $component */
            foreach ($components as $component) {
              $blockDefinition = $block_manager->getDefinition($component->getPluginId());
              $configuration = $component->toArray()['configuration'];
              if ($blockDefinition['id'] === 'inline_block') {
                $blockContentRevisionIds[] = $configuration['block_revision_id'];
              }
            }
          }
          $target_entity_ids = empty($blockContentRevisionIds) ? $blockContentRevisionIds : $this->prepareBlockContentIds($blockContentRevisionIds);
          $target_entity_type_id = 'block_content';

          foreach ($target_entity_ids as $target_content_entity_id) {
            $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
            if ($target_entity_type instanceof ContentEntityType) {
              $relatedEntity = $this->entityTypeManager->getStorage($target_entity_type_id)->load($target_content_entity_id);
              if ($relatedEntity !== NULL) {
                // Avoid entities that were are already visited
                if (!isset($visited[$relatedEntity->bundle()]) || !in_array($relatedEntity->id(), $visited[$relatedEntity->bundle()])) {
                  if ($relatedEntity instanceof ContentEntityInterface && $relatedEntity->isTranslatable() && $this->lingotekConfiguration->isEnabled($relatedEntity->getEntityTypeId(), $relatedEntity->bundle())) {
                    if (!$this->lingotekConfiguration->isFieldLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $k)) {
                      $entities = $this->extract($relatedEntity, $entities, $related, $depth, $visited);
                    }
                    else {
                      $related[$relatedEntity->getEntityTypeId()][$relatedEntity->id()] = $relatedEntity->getUntranslated();
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
   * Get block content ids from the revision ids stored in the field definition of parent entity
   *
   * @param array $blockContentRevisionIds
   *   An array of block revision IDs.
   *
   * @return array
   *   An array of block IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function prepareBlockContentIds(array $blockContentRevisionIds) {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $blockContentStorage */
    $blockContentStorage = $this->entityTypeManager->getStorage('block_content');
    $ids = $blockContentStorage->getQuery()->condition($blockContentStorage->getEntityType()->getKey('revision'), $blockContentRevisionIds, 'IN')->execute();
    return $ids;
  }

}
