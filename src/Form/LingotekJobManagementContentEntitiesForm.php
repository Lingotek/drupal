<?php

namespace Drupal\lingotek\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 * Form for bulk management of job filtered content.
 */
class LingotekJobManagementContentEntitiesForm extends LingotekManagementFormBase {

  /**
   * The job ID
   *
   * @var string
   */
  protected $jobId;

  /**
   * Constructs a new LingotekJobManagementContentEntitiesForm object.
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

  public function buildForm(array $form, FormStateInterface $form_state, $job_id = NULL) {
    $this->jobId = $job_id;
    $form = parent::buildForm($form, $form_state);
    $form['filters']['wrapper']['job']['#access'] = FALSE;
    return $form;
  }

  protected function getBulkOptions() {
    $options = parent::getBulkOptions();
    $options['show_advanced']['#access'] = FALSE;
    $options['job_id']['#access'] = FALSE;
    $options['job_id']['#default_value'] = $this->jobId;
    return $options;
  }

  protected function getFilteredEntities() {
    $entity_query = $this->entityTypeManager->getStorage('lingotek_content_metadata')->getQuery();
    $entity_query->condition('job_id', $this->jobId);
    $ids = $entity_query->execute();

    $metadatas = $this->entityTypeManager->getStorage('lingotek_content_metadata')
      ->loadMultiple($ids);
    $entities = [];

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    foreach ($metadatas as $metadata) {
      $content_entity_type_id = $metadata->getContentEntityTypeId();
      $content_entity_id = $metadata->getContentEntityId();
      $entity = $this->entityTypeManager->getStorage($content_entity_type_id)
        ->load($content_entity_id);
      $entities[$content_entity_type_id][] = $entity;
    }
    return $entities;
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
    return 'lingotek_job_content_entities_management';
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
    $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());

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
