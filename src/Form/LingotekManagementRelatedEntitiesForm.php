<?php

namespace Drupal\lingotek\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk management of related content.
 */
class LingotekManagementRelatedEntitiesForm extends LingotekManagementFormBase {

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Constructs a new LingotekManagementRelatedEntitiesForm object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The current database connection.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query factory.
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The lingotek service.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The Lingotek content translation service.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(Connection $connection, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, QueryFactory $entity_query, LingotekInterface $lingotek, LingotekConfigurationServiceInterface $lingotek_configuration, LanguageLocaleMapperInterface $language_locale_mapper, ContentTranslationManagerInterface $content_translation_manager, LingotekContentTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, StateInterface $state, ModuleHandlerInterface $module_handler) {
    $this->connection = $connection;
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
    $this->entityQuery = $entity_query;
    $this->contentTranslationManager = $content_translation_manager;
    $this->lingotek = $lingotek;
    $this->translationService = $translation_service;
    $this->tempStoreFactory = $temp_store_factory;
    $this->lingotek = $lingotek;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->state = $state;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity.manager'),
      $container->get('language_manager'),
      $container->get('entity.query'),
      $container->get('lingotek'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('content_translation.manager'),
      $container->get('lingotek.content_translation'),
      $container->get('user.private_tempstore'),
      $container->get('state'),
      $container->get('module_handler')
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $node = NULL) {
    $this->node = $node;
    $form = parent::buildForm($form, $form_state);

    $depth = $this->getRecursionDepth();
    $form['depth_selection'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'form-item-depth-selection'],
      '#weight' => 60,
    ];
    $form['depth_selection']['depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Recursion depth:'),
      '#options' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
      '#default_value' => $depth,
    ];
    $form['depth_selection']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#submit' => [[$this, 'recursionDepthCallback']],
    ];

    return $form;
  }

  protected function getSelectedEntities($values) {
    $entityTypes = [];
    $entities = [];
    foreach ($values as $type_entity_id) {
      list($type, $entity_id) = explode(":", $type_entity_id);
      $entityTypes[$type][] = $entity_id;
    }

    foreach ($entityTypes as $type => $values) {
      $entities = array_merge($entities, $this->entityManager->getStorage($type)->loadMultiple($values));
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_entity_management';
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeaders() {
    $headers = [
      'label' => $this->t('Label'),
      'entity_type_id' => $this->t('Content Type'),
      'bundle' => $this->t('Bundle'),
      'source' => $this->t('Source'),
      'translations' => $this->t('Translations'),
      'profile' => $this->t('Profile'),
      'job_id' => $this->t('Job ID'),
    ];
    return $headers;
  }

  public function calculateNestedEntities(ContentEntityInterface &$entity, &$visited = [], &$entities = []) {
    $visited[$entity->bundle()][] = $entity->id();
    $entities[$entity->getEntityTypeId()][] = $entity;
    $field_definitions = $this->entityManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    foreach ($field_definitions as $k => $definition) {
      $field_type = $field_definitions[$k]->getType();
      if ($field_type === 'entity_reference' || $field_type === 'er_viewmode' || $field_type === 'entity_reference_revisions') {
        $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()
          ->getSetting('target_type');
        $target_entity_type = $this->entityManager->getDefinition($target_entity_type_id);
        if ($target_entity_type instanceof ContentEntityType) {
          foreach ($entity->{$k} as $field_item) {
            if (!isset($entities[$target_entity_type_id])) {
              $entities[$target_entity_type_id] = [];
            }
            $entities[$target_entity_type_id][] = $field_item->target_id;
          }
        }
      }
    }
    return $entities;
  }

  protected function getNestedEntities(ContentEntityInterface &$entity, &$visited = [], &$entities = [], $depth = 1) {
    $visited[$entity->bundle()][] = $entity->id();
    $entities[$entity->getEntityTypeId()][$entity->id()] = $entity;
    if ($depth > 0) {
      --$depth;
      $field_definitions = $this->entityManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
      foreach ($field_definitions as $k => $definition) {
        $field_type = $field_definitions[$k]->getType();
        if (in_array($field_type, ['entity_reference', 'entity_reference_revisions', 'er_viewmode'])) {
          $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()
            ->getSetting('target_type');
          $target_entity_type = $this->entityManager->getDefinition($target_entity_type_id);
          if ($target_entity_type instanceof ContentEntityType) {
            $child_entities = $entity->{$k}->referencedEntities();
            foreach ($child_entities as $embedded_entity) {
              if ($embedded_entity !== NULL) {
                if ($embedded_entity instanceof ContentEntityInterface && $embedded_entity->isTranslatable() && $this->lingotekConfiguration->isEnabled($embedded_entity->getEntityTypeId(), $embedded_entity->bundle())) {
                  // We need to avoid cycles if we have several entity references
                  // referencing each other.
                  if (!isset($visited[$embedded_entity->bundle()]) || !in_array($embedded_entity->id(), $visited[$embedded_entity->bundle()])) {
                    $entities = $this->getNestedEntities($embedded_entity, $visited, $entities, $depth);
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

  protected function getFilteredEntities() {
    // This implies recursion through all the related content.
    $visited = [];
    $entities = [];
    $recursion_depth = $this->getRecursionDepth();
    $entities = $this->getNestedEntities($this->node, $visited, $entities, $recursion_depth);
    return $entities;
  }

  protected function getRows($entity_list) {
    $counter = 1;
    $rows = [];
    foreach ($entity_list as $entity_type_id => $entities) {
      foreach ($entities as $entity_id => $entity) {
        $rowId = (string) $entity->getEntityTypeId() . ':' . (String) $entity->id();
        $rows[$rowId] = $this->getRow($entity);
        $counter += 1;
      }
    }
    return $rows;
  }

  protected function getRow($entity) {
    $row = parent::getRow($entity);
    $bundleInfo = $this->entityManager->getBundleInfo($entity->getEntityTypeId());

    if ($entity->hasLinkTemplate('canonical')) {
      $row['label'] = $entity->toLink();
    }
    else {
      $row['label'] = $entity->label();
    }
    $row['entity_type_id'] = $entity->getEntityType()->getLabel();
    $row['bundle'] = $bundleInfo[$entity->bundle()]['label'];
    return $row;
  }

  /**
   * Gets the key used for persisting filtering options in the temp storage.
   *
   * @return string
   *   Temp storage identifier where filters are persisted.
   */
  protected function getTempStorageFilterKey() {
    return NULL;
  }

  /**
   * Gets the filter keys so we can persist or clear filtering options.
   *
   * @return string[]
   *   Array of filter identifiers.
   */
  protected function getFilterKeys() {
    return NULL;
  }

  /**
   * Gets the filters for rendering.
   *
   * @return array
   *   A form array.
   */
  protected function getFilters() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPager() {
    return NULL;
  }

  /**
   * Gets the recursion depth saved in temp storage.
   *
   * @return int
   *   The recursion depth.
   */
  protected function getRecursionDepth() {
    $temp_store = $this->tempStoreFactory->get('lingotek.management.recursion_depth');
    $depth = $temp_store->get('depth');
    if ($depth === NULL) {
      $depth = 1;
    }
    return $depth;
  }

  /**
   * Saves the recursion depth in temp storage.
   *
   * @param int $depth
   *   The recursion depth.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function setRecursionDepth($depth) {
    $temp_store = $this->tempStoreFactory->get('lingotek.management.recursion_depth');
    $temp_store->set('depth', $depth);
  }

  /**
   * {@inheritdoc}
   */
  public function recursionDepthCallback(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('depth');
    $this->setRecursionDepth($value);
  }

}
