<?php

namespace Drupal\lingotek\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekInterface;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
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
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LingotekInterface $lingotek, LingotekConfigurationServiceInterface $lingotek_configuration, LanguageLocaleMapperInterface $language_locale_mapper, ContentTranslationManagerInterface $content_translation_manager, LingotekContentTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, StateInterface $state, ModuleHandlerInterface $module_handler, EntityFieldManagerInterface $entity_field_manager = NULL, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, LinkGeneratorInterface $link_generator = NULL) {
    parent::__construct($connection, $entity_type_manager, $language_manager, $lingotek, $lingotek_configuration, $language_locale_mapper, $content_translation_manager, $translation_service, $temp_store_factory, $state, $module_handler, NULL, $entity_field_manager, $entity_type_bundle_info, $link_generator);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('lingotek'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('content_translation.manager'),
      $container->get('lingotek.content_translation'),
      $container->get('tempstore.private'),
      $container->get('state'),
      $container->get('module_handler'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('link_generator')
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $node = NULL) {
    $this->node = $node;
    $form = parent::buildForm($form, $form_state);
    $related = $this->related;

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

    $relatedRows = [];
    if (!empty($related)) {
      // Generate the rows based on those entities.
      $relatedRows = $this->getRows($related);
    }
    $form['related'] = [
      '#type' => 'details',
      '#title' => $this->t('Embedded content references'),
      '#description' => $this->t('These entities are included in the parent document, but listed here for reference. It is not recommended to operate on this data, as its contents will be already translated with the embedding document.'),
      '#weight' => 100,
    ];
    $form['related']['table'] = [
      '#header' => $this->getHeaders(),
      '#rows' => $relatedRows,
      '#empty' => $this->t('No embedded content available'),
      '#type' => 'table',
      '#weight' => 100,
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
      $entities = array_merge($entities, $this->entityTypeManager->getStorage($type)->loadMultiple($values));
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
      'title' => $this->t('Label'),
      'entity_type_id' => $this->t('Content Type'),
      'bundle' => $this->t('Bundle'),
      'source' => $this->t('Source'),
      'translations' => $this->t('Translations'),
      'profile' => $this->t('Profile'),
      'job_id' => $this->t('Job ID'),
    ];
    return $headers;
  }

  /**
   * @deprecated in lingotek:3.1.0 and is removed from lingotek:4.0.0.
   *
   * @see \Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface
   */
  public function calculateNestedEntities(ContentEntityInterface &$entity, &$visited = [], &$entities = []) {
    $visited[$entity->bundle()][] = $entity->id();
    $entities[$entity->getEntityTypeId()][] = $entity;
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    foreach ($field_definitions as $k => $definition) {
      $field_type = $field_definitions[$k]->getType();
      if ($field_type === 'entity_reference' || $field_type === 'er_viewmode' || $field_type === 'entity_reference_revisions' || $field_type === 'cohesion_entity_reference_revisions') {
        $target_entity_type_id = $field_definitions[$k]->getFieldStorageDefinition()
          ->getSetting('target_type');
        $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
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

  protected function getFilteredEntities() {
    $entities = [];
    $related = [];
    $visited = [];
    $recursion_depth = $this->getRecursionDepth();
    $type = \Drupal::service('plugin.manager.related_entities_detector');
    $plugin_definitions = $type->getDefinitions();
    uasort($plugin_definitions, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    foreach ($plugin_definitions as $plugin_definition_id => $plugin_definition) {
      /** @var \Drupal\lingotek\RelatedEntities\RelatedEntitiesDetectorInterface $plugin */
      $plugin = $type->createInstance($plugin_definition_id, []);
      $entities = $plugin->extract($this->node, $entities, $related, $recursion_depth, $visited);
    }
    $this->related = $related;
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
    // For this method to be able to be reused for the table component, order
    // matters, so we rebuild the order of the row. For tableselect the headers
    // defines the order, for the table, the order we add to the array matters.
    $parentRow = parent::getRow($entity);
    $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());

    $row['title'] = $parentRow['title'];
    $row['entity_type_id'] = $entity->getEntityType()->getLabel();
    $row['bundle'] = $bundleInfo[$entity->bundle()]['label'];
    $row['title'] = $parentRow['title'];
    $row['source'] = $parentRow['source'];
    $row['translations'] = $parentRow['translations'];
    $row['profile'] = $parentRow['profile'];
    $row['job_id'] = $parentRow['job_id'];

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

  /**
   * {@inheritdoc}
   */
  protected function canHaveDeleteTranslationBulkOptions() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function canHaveDeleteBulkOptions() {
    return FALSE;
  }

}
