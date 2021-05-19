<?php

namespace Drupal\lingotek\Plugin\RelatedEntitiesDetector;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EditorDetectorBase extends PluginBase implements RelatedEntitiesDetectorInterface, ContainerFactoryPluginInterface {

  /**
   * The EntityRepository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

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
   * The field types this detector applies to.
   *
   * @var string[]
   */
  protected $fieldTypes;

  /**
   * The XPath to find embedded content.
   *
   * @var string
   */
  protected $xpath;

  /**
   * NestedEntityReferences constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The EntityRepositoryInterface service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfiguration
   *   The Lingotek configuration service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository, EntityFieldManagerInterface $entityFieldManager, LingotekConfigurationServiceInterface $lingotekConfiguration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityRepository = $entity_repository;
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
      $container->get('entity.repository'),
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
          foreach ($entity->get($k) as $delta => $fieldData) {
            $text = $fieldData->value;
            if ($field_type === 'text_with_summary') {
              $text .= $fieldData->summary;
            }
            $fieldTextEntities = $this->extractEntitiesFromText($text);
            foreach ($fieldTextEntities as $fieldTextEntityTypeUuid => $fieldTextEntityTypeId) {
              $relatedEntity = $this->entityRepository->loadEntityByUuid($fieldTextEntityTypeId, $fieldTextEntityTypeUuid);
              if ($relatedEntity instanceof ContentEntityInterface && $relatedEntity->isTranslatable() && $this->lingotekConfiguration->isEnabled($relatedEntity->getEntityTypeId(), $relatedEntity->bundle())) {
                $entities[$relatedEntity->getEntityTypeId()][$relatedEntity->id()] = $relatedEntity->getUntranslated();
                $entities = $this->extract($relatedEntity, $entities, $related, $depth, $visited);
              }
            }
          }
        }
      }
    }
    return $entities;
  }

  /**
   * Extract entities referenced by a given text.
   *
   * @param string $text
   *   The text to extract the entities from.
   *
   * @return array
   *   Array of entities in the form of (uuid => entity_type_id).
   */
  protected function extractEntitiesFromText($text) {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $entities = [];
    foreach ($xpath->query($this->xpath) as $node) {
      $entities[$node->getAttribute('data-entity-uuid')] = $node->getAttribute('data-entity-type');
    }
    return $entities;
  }

}
