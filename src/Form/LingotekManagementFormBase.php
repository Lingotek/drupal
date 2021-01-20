<?php

namespace Drupal\lingotek\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\Helpers\LingotekManagementFormHelperTrait;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\LingotekSetupTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk management of content.
 */
abstract class LingotekManagementFormBase extends FormBase {

  use LingotekManagementFormHelperTrait;

  use LingotekSetupTrait;

  /**
   * The connection object on which to run queries.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The entity manager.
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
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Constructs a new LingotekManagementFormBase object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The current database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The Lingotek service.
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
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param string $entity_type_id
   *   The entity type id.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LingotekInterface $lingotek, LingotekConfigurationServiceInterface $lingotek_configuration, LanguageLocaleMapperInterface $language_locale_mapper, ContentTranslationManagerInterface $content_translation_manager, LingotekContentTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, StateInterface $state, ModuleHandlerInterface $module_handler, $entity_type_id, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, LinkGeneratorInterface $link_generator) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->contentTranslationManager = $content_translation_manager;
    $this->lingotek = $lingotek;
    $this->translationService = $translation_service;
    $this->tempStoreFactory = $temp_store_factory;
    $this->lingotek = $lingotek;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->state = $state;
    $this->moduleHandler = $module_handler;
    $this->entityTypeId = $entity_type_id;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->linkGenerator = $link_generator;
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
      \Drupal::routeMatch()->getParameter('entity_type_id'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());

    $labelFilter = $temp_store->get('label');
    $bundleFilter = $temp_store->get('bundle');
    $jobFilter = $temp_store->get('job');
    $documentIdFilter = $temp_store->get('document_id');
    $entityIdFilter = $temp_store->get('entity_id');
    $sourceLanguageFilter = $temp_store->get('source_language');
    $sourceStatusFilter = $temp_store->get('source_status');
    $targetStatusFilter = $temp_store->get('target_status');
    $contentStateFilter = $temp_store->get('content_state');
    $profileFilter = $temp_store->get('profile');

    // Add the filters if any.
    $filters = $this->getFilters();
    if (!empty($filters)) {
      $form['filters'] = [
        '#type' => 'details',
        '#title' => $this->t('Filter'),
        '#open' => TRUE,
        '#weight' => 5,
        '#tree' => TRUE,
      ];
      $form['filters']['wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['form--inline', 'clearfix']],
      ];
      foreach ($filters as $filter_id => $filter) {
        $form['filters']['wrapper'][$filter_id] = $filter;
      }
      // Advanced filters
      $form['filters']['advanced_options'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['form--inline', 'clearfix']],
      ];
      $form['filters']['advanced_options'] = [
        '#type' => 'details',
        '#title' => $this->t('Show advanced options'),
        '#title_display' => 'before',
        '#attributes' => ['class' => ['form--inline', 'clearfix']],
      ];
      $form['filters']['advanced_options']['document_id'] = [
        '#type' => 'textfield',
        '#size' => 35,
        '#title' => $this->t('Document ID'),
        '#description' => $this->t('You can indicate multiple comma-separated values.'),
        '#default_value' => $documentIdFilter,
       ];
      $form['filters']['advanced_options']['entity_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Entity ID'),
        '#description' => $this->t('You can indicate multiple comma-separated values.'),
        '#size' => 35,
        '#default_value' => $entityIdFilter,
      ];
      $form['filters']['advanced_options']['source_language'] = [
        '#type' => 'select',
        '#title' => $this->t('Source language'),
        '#options' => ['' => $this->t('All languages')] + $this->getAllLanguages(),
        '#default_value' => $sourceLanguageFilter,
      ];
      $form['filters']['advanced_options']['source_status'] = [
        '#type' => 'select',
        '#title' => $this->t('Source Status'),
        '#default_value' => $sourceStatusFilter,
        '#options' => [
          '' => $this->t('All'),
          'UPLOAD_NEEDED' => $this->t('Upload Needed'),
          Lingotek::STATUS_CURRENT => $this->t('Current'),
          Lingotek::STATUS_IMPORTING => $this->t('Importing'),
          Lingotek::STATUS_EDITED => $this->t('Edited'),
          Lingotek::STATUS_CANCELLED => $this->t('Cancelled'),
          Lingotek::STATUS_ERROR => $this->t('Error'),
        ],
      ];
      $form['filters']['advanced_options']['target_status'] = [
        '#type' => 'select',
        '#title' => $this->t('Target Status'),
        '#default_value' => $targetStatusFilter,
        '#options' => [
          '' => $this->t('All'),
          Lingotek::STATUS_CURRENT => $this->t('Current'),
          Lingotek::STATUS_EDITED => $this->t('Edited'),
          Lingotek::STATUS_PENDING => $this->t('In Progress'),
          Lingotek::STATUS_READY => $this->t('Ready'),
          Lingotek::STATUS_INTERMEDIATE => $this->t('Interim'),
          Lingotek::STATUS_REQUEST => $this->t('Not Requested'),
          Lingotek::STATUS_CANCELLED => $this->t('Cancelled'),
          Lingotek::STATUS_ERROR => $this->t('Error'),
        ],
      ];
      if ($this->moduleHandler->moduleExists('content_moderation')) {
        $workflow = $this->entityTypeManager->getStorage('workflow')->load('editorial');
        if ($workflow != NULL) {
          $states = $workflow->getTypePlugin()->getStates();
          $options = ['' => $this->t('All')];
          foreach ($states as $state_id => $state) {
            $options[$state_id] = $state->label();
          }
          $form['filters']['advanced_options']['content_state'] = [
            '#type' => 'select',
            '#title' => $this->t('Content State'),
            '#default_value' => $contentStateFilter,
            '#options' => $options,
          ];
        }
      }
      $form['filters']['advanced_options']['profile'] = [
        '#type' => 'select',
        '#title' => $this->t('Profile'),
        '#options' => ['' => $this->t('All')] + $this->lingotekConfiguration->getProfileOptions(),
        '#multiple' => TRUE,
        '#default_value' => $profileFilter,
      ];

      // Filter actions
      $form['filters']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['clearfix']],
      ];
      $form['filters']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Filter'),
        '#submit' => ['::filterForm'],
      ];
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetFilterForm'],
      ];
    }

    // Add the bulk operations if any.
    $options = $this->getBulkOptions();
    if (!empty($options)) {
      $form['options'] = [
        '#type' => 'details',
        '#title' => $this->t('Bulk document management'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['container-inline']],
        '#weight' => 10,
      ];
      foreach ($options as $id => $component) {
        $form['options'][$id] = $component;
      }
    }

    // Add the headers.
    $headers = $this->getHeaders();

    // Get all the entities that need to be displayed.
    $entities = $this->getFilteredEntities();

    // Generate the rows based on those entities.
    $rows = [];
    if (!empty($entities)) {
      $rows = $this->getRows($entities);
    }

    $pager = $this->getPager();
    if (!empty($pager)) {
      $form['pager'] = [
        '#type' => 'pager',
        '#weight' => 50,
      ];
      $form['items_per_page'] = $pager;
    }

    $form['table'] = [
      '#header' => $headers,
      '#options' => $rows,
      '#empty' => $this->t('No content available'),
      '#type' => 'tableselect',
      '#weight' => 30,
    ];

    $form['#attached']['library'][] = 'lingotek/lingotek';
    $form['#attached']['library'][] = 'lingotek/lingotek.manage';
    return $form;
  }

  /**
   * @return string[]
   */
  abstract protected function getHeaders();

  /**
   * Load the entities corresponding with the given identifiers.
   *
   * @param string[] $values
   *   Array of values that identify the selected entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities.
   */
  abstract protected function getSelectedEntities($values);

  /**
   * Gets the key used for persisting filtering options in the temp storage.
   *
   * @return string
   *   Temp storage identifier where filters are persisted.
   */
  abstract protected function getTempStorageFilterKey();

  /**
   * Gets the filter keys so we can persist or clear filtering options.
   *
   * @return string[]
   *   Array of filter identifiers.
   */
  abstract protected function getFilterKeys();

  /**
   * Form submission handler for resetting the filters.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetFilterForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());
    $keys = $this->getFilterKeys();
    foreach ($keys as $key) {
      // Reset the filter, no matter if it's under 'wrapper' or 'advanced_filters.'
      $temp_store->delete($key[1]);
    }
  }

  /**
   * Form submission handler for filtering.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function filterForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());
    $keys = $this->getFilterKeys();
    $trimmableKeys = ['label'];
    foreach ($keys as $key) {
      if (in_array($key[1], $trimmableKeys)) {
        $form_state->setValue(['filters', $key[0], $key[1]], trim($form_state->getValue(['filters', $key[0], $key[1]])));
      }
      // This sets and gets the values of the specific key. $key[0] can be either 'wrapper' or 'advanced_filters', and $key[1] is the specific filter itself.
      $temp_store->set($key[1], $form_state->getValue(['filters', $key[0], $key[1]]));
    }
    // If we apply any filters, we need to go to the first page again.
    $form_state->setRedirect('<current>');
  }

  /**
   * Gets the bulk options form array structure.
   *
   * @return array
   *   A form array.
   */
  protected function getBulkOptions() {
    $options = [];
    $options['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $this->generateBulkOptions(),
    ];
    $options['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
    ];
    $options['show_advanced'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show advanced options'),
      '#title_display' => 'before',
    ];
    $options['job_id'] = [
      '#type' => 'lingotek_job_id',
      '#title' => $this->t('Job ID'),
      '#description' => $this->t('Assign a job id that you can filter on later on the TMS or in this page.'),
      '#states' => [
        'visible' => [
          ':input[name="show_advanced"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $options;
  }

  /**
   * Gets the entities that needs to be displayed based on the current filters.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities
   */
  abstract protected function getFilteredEntities();

  /**
   * Gets the filters for rendering.
   *
   * @return array
   *   A form array.
   */
  abstract protected function getFilters();

  /**
   * Gets the rows for rendering based on the passed entity list.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entity_list
   *
   * @return array
   *   A render array.
   */
  abstract protected function getRows($entity_list);

  /**
   * Gets the pager.
   *
   * @return array
   *   A render array.
   */
  abstract protected function getPager();

  /**
   * Gets a rows fo rendering based on the passed entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array
   *   A render array.
   */
  protected function getRow($entity) {
    $row = [];
    $source = $this->getSourceStatus($entity);
    $entityTypeId = $entity->getEntityTypeId();
    $translations = $this->getTranslationsStatuses($entity);
    $profile = $this->lingotekConfiguration->getEntityProfile($entity, TRUE);
    $job_id = $this->translationService->getJobId($entity);
    $entity_type = $this->entityTypeManager->getDefinition($entityTypeId);
    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';

    if ($has_bundles) {
      $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
      $row['bundle'] = $bundleInfo[$entity->bundle()]['label'];
    }

    $row += [
      'title' => $entity->hasLinkTemplate('canonical') ? $this->linkGenerator
        ->generate($entity->label(), Url::fromRoute($entity->toUrl()
          ->getRouteName(), [$entityTypeId => $entity->id()])) : $entity->id(),
      'source' => $source,
      'translations' => $translations,
      'profile' => $profile ? $profile->label() : '',
      'job_id' => $job_id ?: '',
    ];
    if (!$this->lingotekConfiguration->isEnabled($entityTypeId, $entity->bundle())) {
      $row['profile'] = 'Not enabled';
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operation = $form_state->getValue('operation');
    $job_id = $form_state->getValue('job_id') ?: NULL;
    $values = array_keys(array_filter($form_state->getValue(['table'])));
    $processed = FALSE;
    switch ($operation) {
      case 'debug.export':
        $this->createDebugExportBatch($values);
        $processed = TRUE;
        break;

      case 'upload':
        $this->createUploadBatch($values, $job_id);
        $processed = TRUE;
        break;

      case 'check_upload':
        $this->createUploadCheckStatusBatch($values);
        $processed = TRUE;
        break;

      case 'request_translations':
        $this->createRequestTranslationsBatch($values);
        $processed = TRUE;
        break;

      case 'check_translations':
        $this->createTranslationCheckStatusBatch($values);
        $processed = TRUE;
        break;

      case 'download':
        $this->createDownloadBatch($values);
        $processed = TRUE;
        break;

      case 'cancel':
        $this->createCancelBatch($values);
        $processed = TRUE;
        break;

      case 'assign_job':
        $this->redirectToAssignJobIdMultipleEntitiesForm($values, $form_state);
        $processed = TRUE;
        break;

      case 'clear_job':
        $this->redirectToClearJobIdMultipleEntitiesForm($values, $form_state);
        $processed = TRUE;
        break;

      case 'delete_nodes':
        $this->redirectToDeleteMultipleNodesForm($values, $form_state);
        $processed = TRUE;
        break;

      case 'delete_translations':
        $this->redirectToDeleteMultipleTranslationsForm($values, $form_state);
        $processed = TRUE;
        break;
    }
    if (!$processed) {
      if (0 === strpos($operation, 'request_translation:')) {
        list($operation, $language) = explode(':', $operation);
        $this->createLanguageRequestTranslationBatch($values, $language);
        $processed = TRUE;
      }
      if (0 === strpos($operation, 'check_translation:')) {
        list($operation, $language) = explode(':', $operation);
        $this->createLanguageTranslationCheckStatusBatch($values, $language);
        $processed = TRUE;
      }
      if (0 === strpos($operation, 'download:')) {
        list($operation, $language) = explode(':', $operation);
        $this->createLanguageDownloadBatch($values, $language);
        $processed = TRUE;
      }
      if (0 === strpos($operation, 'cancel:')) {
        list($operation, $language) = explode(':', $operation);
        $this->createTargetCancelBatch($values, $language);
        $processed = TRUE;
      }
      if (0 === strpos($operation, 'delete_translation:')) {
        list($operation, $language) = explode(':', $operation);
        $this->redirectToDeleteTranslationForm($values, $language, $form_state);
        $processed = TRUE;
      }
      if (0 === strpos($operation, 'change_profile:')) {
        list($operation, $profile_id) = explode(':', $operation);
        $this->createChangeProfileBatch($values, $profile_id);
        $processed = TRUE;
      }
    }
  }

  /**
   * Performs an operation to several values in a batch.
   *
   * @param string $operation
   *   The method in this object we need to call.
   * @param array $values
   *   Array of ids to process.
   * @param string $title
   *   The title for the batch progress.
   * @param string $language
   *   (Optional) The language code for the request. NULL if is not applicable.
   * @param string $job_id
   *   (Optional) The job ID to be used. NULL if is not applicable.
   */
  protected function createBatch($operation, $values, $title, $language = NULL, $job_id = NULL) {
    $operations = [];
    $entities = $this->getSelectedEntities($values);

    foreach ($entities as $entity) {
      $split_download_all = $this->lingotekConfiguration->getPreference('split_download_all');
      if ($operation == 'downloadTranslations' && $split_download_all) {

        $languages = $this->languageManager->getLanguages();
        foreach ($languages as $langcode => $language) {
          if ($langcode !== $entity->language()->getId()) {
            $operations[] = [
              [$this, 'downloadTranslation'],
              [$entity, $langcode, $job_id],
            ];
          }
        }
      }
      else {
        $operations[] = [[$this, $operation], [$entity, $language, $job_id]];
      }
    }
    $batch = [
      'title' => $title,
      'operations' => $operations,
      'finished' => [$this, 'batchFinished'],
      'progressive' => TRUE,
      'batch_redirect' => $this->getRequest()->getUri(),
    ];
    batch_set($batch);
  }

  /**
   * Batch callback called when the batch finishes.
   *
   * @param $success
   * @param $results
   * @param $operations
   *
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   */
  public function batchFinished($success, $results, $operations) {
    if ($success) {
      $batch = &batch_get();
      $this->messenger()->addStatus('Operations completed.');
    }
    return new LocalRedirectResponse($batch['sets'][0]['batch_redirect']);
  }

  /**
   * Create and set an upload batch.
   *
   * @param array $values
   *   Array of ids to upload.
   * @param string $job_id
   *   (Optional) The job ID to be used. NULL if is not applicable.
   */
  protected function createUploadBatch($values, $job_id = NULL) {
    $this->createBatch('uploadDocument', $values, $this->t('Uploading content to Lingotek service'), NULL, $job_id);
  }

  /**
   * Create and set an export batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createDebugExportBatch($values) {
    $entities = $this->getSelectedEntities($values);

    foreach ($entities as $entity) {
      $operations[] = [[$this, 'debugExport'], [$entity]];
    }
    $batch = [
      'title' => $this->t('Exporting content (debugging purposes)'),
      'operations' => $operations,
      'finished' => [$this, 'debugExportFinished'],
      'progressive' => TRUE,
    ];
    batch_set($batch);
  }

  /**
   * Batch callback called when the debug export batch finishes.
   *
   * @param $success
   * @param $results
   * @param $operations
   *
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   */
  public function debugExportFinished($success, $results, $operations) {
    if ($success) {
      $links = [];
      if (isset($results['exported'])) {
        foreach ($results['exported'] as $result) {
          $links[] = [
            '#theme' => 'file_link',
            '#file' => File::load($result),
          ];
        }
        $build = [
          '#theme' => 'item_list',
          '#items' => $links,
        ];
        $this->messenger()->addStatus($this->t('Exports available at: @exports',
          ['@exports' => \Drupal::service('renderer')->render($build)]));
      }
    }
  }

  /**
   * Create and set a check upload status batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createUploadCheckStatusBatch($values) {
    $this->createBatch('checkDocumentUploadStatus', $values, $this->t('Checking content upload status with the Lingotek service'));
  }

  /**
   * Create and set a request translations batch for all languages.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createRequestTranslationsBatch($values) {
    $this->createBatch('requestTranslations', $values, $this->t('Requesting translations to Lingotek service.'));
  }

  /**
   * Create and set a request translations batch for all languages.
   *
   * @param array $values
   *   Array of ids to upload.
   * @param string $language
   *   Language code for the request.
   */
  protected function createLanguageRequestTranslationBatch($values, $language) {
    $this->createBatch('requestTranslation', $values, $this->t('Requesting translations to Lingotek service.'), $language);
  }

  /**
   * Create and set a check translation status batch for all languages.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createTranslationCheckStatusBatch($values) {
    $this->createBatch('checkTranslationStatuses', $values, $this->t('Checking translations status from the Lingotek service.'));
  }

  /**
   * Create and set a check translation status batch for a given language.
   *
   * @param array $values
   *   Array of ids to upload.
   * @param string $language
   *   Language code for the request.
   */
  protected function createLanguageTranslationCheckStatusBatch($values, $language) {
    $this->createBatch('checkTranslationStatus', $values, $this->t('Checking translations status from the Lingotek service.'), $language);
  }

  /**
   * Create and set a request target and download batch for all languages.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createDownloadBatch($values) {
    $this->createBatch('downloadTranslations', $values, $this->t('Downloading translations from the Lingotek service.'));
  }

  /**
   * Create and set a request target and download batch for a given language.
   *
   * @param array $values
   *   Array of ids to upload.
   * @param string $language
   *   Language code for the request.
   */
  protected function createLanguageDownloadBatch($values, $language) {
    $this->createBatch('downloadTranslation', $values, $this->t('Downloading translations to Lingotek service'), $language);
  }

  /**
   * Create and set a cancellation batch.
   *
   * @param array $values
   *   Array of ids to cancel.
   */
  protected function createCancelBatch($values) {
    $this->createBatch('cancel', $values, $this->t('Cancelling content from Lingotek service'));
  }

  /**
   * Create and set a target cancellation batch.
   *
   * @param array $values
   *   Array of ids to cancel.
   * @param string $language
   *   Language code for the request.
   */
  protected function createTargetCancelBatch($values, $language) {
    $this->createBatch('cancelTarget', $values, $this->t('Cancelling target content from Lingotek service'), $language);
  }

  /**
   * Create and set a profile change batch.
   *
   * @param array $values
   *   Array of ids to change the Profile.
   */
  protected function createChangeProfileBatch($values, $profile_id) {
    $this->createBatch('changeProfile', $values, $this->t('Updating Translation Profile'), $profile_id);
  }

  /**
   * Redirect to delete content form.
   *
   * @param array $values
   *   Array of ids to delete.
   */
  protected function redirectToDeleteMultipleNodesForm($values, FormStateInterface $form_state) {
    $entityInfo = [];
    $entities = $this->getSelectedEntities($values);
    foreach ($entities as $entity) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $language = $entity->getUntranslated()->language();
      $entityInfo[$entity->id()] = [$language->getId() => $language->getId()];
    }
    $this->tempStoreFactory->get('entity_delete_multiple_confirm')
      ->set($this->currentUser()->id() . ':' . $this->entityTypeId, $entityInfo);
    $form_state->setRedirectUrl(Url::fromUserInput($entity->getEntityType()->getLinkTemplate('delete-multiple-form'), ['query' => $this->getDestinationWithQueryArray()]));
  }

  /**
   * Redirect to assign Job ID form.
   *
   * @param array $values
   *   Array of ids to assign a Job ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function redirectToAssignJobIdMultipleEntitiesForm($values, FormStateInterface $form_state) {
    $entityInfo = [];
    $entities = $this->getSelectedEntities($values);
    foreach ($entities as $entity) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $language = $entity->getUntranslated()->language();
      $entityInfo[$entity->getEntityTypeId()][$entity->id()] = [$language->getId() => $language->getId()];
    }
    $this->tempStoreFactory->get('lingotek_assign_job_entity_multiple_confirm')
      ->set($this->currentUser()->id(), $entityInfo);
    $form_state->setRedirect('lingotek.assign_job_entity_multiple_form', [], ['query' => $this->getDestinationWithQueryArray()]);
  }

  /**
   * Redirect to clear Job ID form.
   *
   * @param array $values
   *   Array of ids to clear Job ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function redirectToClearJobIdMultipleEntitiesForm($values, FormStateInterface $form_state) {
    $entityInfo = [];
    $entities = $this->getSelectedEntities($values);
    foreach ($entities as $entity) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $language = $entity->getUntranslated()->language();
      $entityInfo[$entity->getEntityTypeId()][$entity->id()] = [$language->getId() => $language->getId()];
    }
    $this->tempStoreFactory->get('lingotek_assign_job_entity_multiple_confirm')
      ->set($this->currentUser()->id(), $entityInfo);
    $form_state->setRedirect('lingotek.clear_job_entity_multiple_form', [], ['query' => $this->getDestinationWithQueryArray()]);
  }

  /**
   * Redirect to delete specific translation form.
   *
   * @param array $values
   *   Array of ids to delete.
   */
  protected function redirectToDeleteTranslationForm($values, $langcode, FormStateInterface $form_state) {
    $entityInfo = [];
    $entities = $this->getSelectedEntities($values);
    foreach ($entities as $entity) {
      $source_language = $entity->getUntranslated()->language();
      if ($source_language->getId() !== $langcode && $entity->hasTranslation($langcode)) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entityInfo[$entity->id()][$langcode] = $langcode;
      }
    }
    if (!empty($entityInfo)) {
      $this->tempStoreFactory->get('entity_delete_multiple_confirm')
        ->set($this->currentUser()->id() . ':' . $this->entityTypeId, $entityInfo);
      $form_state->setRedirectUrl(Url::fromUserInput($entity->getEntityType()->getLinkTemplate('delete-multiple-form'), ['query' => $this->getDestinationWithQueryArray()]));
    }
    else {
      $this->messenger()->addWarning($this->t('No valid translations for deletion.'));
      // Ensure selection is persisted.
      $form_state->setRebuild();
    }
  }

  /**
   * Redirect to delete translations form.
   *
   * @param array $values
   *   Array of ids to delete.
   */
  protected function redirectToDeleteMultipleTranslationsForm($values, FormStateInterface $form_state) {
    $entityInfo = [];
    $entities = $this->getSelectedEntities($values);
    $languages = $this->languageManager->getLanguages();
    foreach ($entities as $entity) {
      $source_language = $entity->getUntranslated()->language();
      foreach ($languages as $langcode => $language) {
        if ($source_language->getId() !== $langcode && $entity->hasTranslation($langcode)) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entityInfo[$entity->id()][$langcode] = $langcode;
        }
      }
    }
    if (!empty($entityInfo)) {
      $this->tempStoreFactory->get('entity_delete_multiple_confirm')
        ->set($this->currentUser()->id() . ':' . $this->entityTypeId, $entityInfo);
      $form_state->setRedirectUrl(Url::fromUserInput($entity->getEntityType()->getLinkTemplate('delete-multiple-form'), ['query' => $this->getDestinationWithQueryArray()]));
    }
    else {
      $this->messenger()->addWarning($this->t('No valid translations for deletion.'));
      // Ensure selection is persisted.
      $form_state->setRebuild();
    }
  }

  /**
   * Export source for debugging purposes.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function debugExport(ContentEntityInterface $entity, &$context) {
    $context['message'] = $this->t('Exporting @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot debug export @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot debug export @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $data = $this->translationService->getSourceData($entity);
      $data['_debug'] = [
        'title' => $entity->bundle() . ' (' . $entity->getEntityTypeId() . '): ' . $entity->label(),
        'profile' => $profile ? $profile->id() : '<null>',
        'source_locale' => $this->translationService->getSourceLocale($entity),
      ];
      $source_data = json_encode($data);
      $filename = $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $entity->id() . '.json';
      $file = File::create([
        'uid' => 1,
        'filename' => $filename,
        'uri' => 'public://' . $filename,
        'filemime' => 'text/plain',
        'created' => \Drupal::time()->getRequestTime(),
        'changed' => \Drupal::time()->getRequestTime(),
      ]);
      file_put_contents($file->getFileUri(), $source_data);
      $file->save();
      $context['results']['exported'][] = $file->id();
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Upload source for translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function uploadDocument(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $context['message'] = $this->t('Uploading @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot upload @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot upload @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity)) {
      try {
        $this->translationService->uploadDocument($entity, $job_id);
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addError(t('Document @entity_type %title has been archived. Please upload again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '%title' => $entity->label(),
        ]));
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError(t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      catch (LingotekApiException $exception) {
        if ($this->translationService->getDocumentId($entity)) {
          $this->messenger()->addError(t('The update for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        }
        else {
          $this->messenger()->addError(t('The upload for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        }
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Check document upload status for a given content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function checkDocumentUploadStatus(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $context['message'] = $this->t('Checking status of @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot check upload @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot check upload @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->checkSourceStatus($entity);
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The upload status check for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Request all translations for a given content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function requestTranslations(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $result = NULL;
    $context['message'] = $this->t('Requesting translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot request translations for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot request translations for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $result = $this->translationService->requestTranslations($entity);
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addError(t('Document @entity_type %title has been archived. Please upload again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '%title' => $entity->label(),
        ]));
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError(t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The request for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
    return $result;
  }

  /**
   * Checks all translations statuses for a given content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function checkTranslationStatuses(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $context['message'] = $this->t('Checking translation status for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot check translations for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot check translations for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->checkTargetStatuses($entity);
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The request for @entity_type %title translation status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Checks translation status for a given content in a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $language
   *   The language to check.
   */
  public function checkTranslationStatus(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $context['message'] = $this->t('Checking translation status for @type %label to language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $language]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot check @type %label translation to @language. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $language]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot check @type %label translation to @language. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $language]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->checkTargetStatus($entity, $language);
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The request for @entity_type %title translation status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Request translations for a given content in a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The language to download.
   */
  public function requestTranslation(ContentEntityInterface $entity, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Requesting translation for @type %label to language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $langcode]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot request @type %label translation for @language. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot request @type %label translation for @language. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]
      ));
      return;
    }
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->addTarget($entity, $locale);
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addError(t('Document @entity_type %title has been archived. Please upload again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '%title' => $entity->label(),
        ]));
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError(t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The request for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Download translation for a given content in a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The language to download.
   */
  public function downloadTranslation(ContentEntityInterface $entity, $langcode, $job_id, &$context) {
    // We need to reload the entity, just in case we are using the split bulk upload. The metadata isn't true anymore.
    // ToDo: Look for a better way of invalidating already loaded metadata.
    $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
    $context['message'] = $this->t('Downloading translation for @type %label in language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $langcode]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot download @type %label translation for @language. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot download @type %label translation for @language. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]
      ));
      return;
    }
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->downloadDocument($entity, $locale);
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The download for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      catch (LingotekContentEntityStorageException $storage_exception) {
        \Drupal::logger('lingotek')->error('The download for @entity_type %title failed because of the length of one field translation (%locale) value: %table.',
          ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%locale' => $locale, '%table' => $storage_exception->getTable()]);
        $this->messenger()->addError(t('The download for @entity_type %title failed because of the length of one field translation (%locale) value: %table.',
          ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%locale' => $locale, '%table' => $storage_exception->getTable()]));
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Download translations for a given content in all enabled languages.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function downloadTranslations(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $context['message'] = $this->t('Downloading all translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot download translations for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot download translations for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $langcode => $language) {
        if ($langcode !== $entity->language()->getId()) {
          $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
          if ($this->translationService->checkTargetStatus($entity, $locale)) {
            try {
              $this->translationService->downloadDocument($entity, $locale);
            }
            catch (LingotekApiException $exception) {
              $this->messenger()->addError(t('The download for @entity_type %title translation failed. Please try again.', [
                '@entity_type' => $entity->getEntityTypeId(),
                '%title' => $entity->label(),
              ]));
            }
          }
        }
      }
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Cancel the content from Lingotek.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function cancel(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $context['message'] = $this->t('Cancelling all translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot cancel @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot cancel @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->cancelDocument($entity);
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The cancellation of @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }

    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Cancel the content from Lingotek.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function cancelTarget(ContentEntityInterface $entity, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Cancelling translation for @type %label to language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $langcode]);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning(t('Cannot cancel @type %label translation to @language. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot cancel @type %label translation to @language. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->cancelDocumentTarget($entity, $locale);
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError(t('The cancellation of @entity_type %title translation to @language failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@language' => $langcode]));
      }

    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  /**
   * Change Translation Profile.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function changeProfile(ContentEntityInterface $entity, $profile_id = NULL, $job_id = NULL, &$context = NULL) {
    $context['message'] = $this->t('Changing Translation Profile for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot change profile for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot change profile for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    try {
      $this->lingotekConfiguration->setProfile($entity, $profile_id, TRUE);
      if ($profile_id === Lingotek::PROFILE_DISABLED) {
        $this->translationService->setSourceStatus($entity, Lingotek::STATUS_DISABLED);
        $this->translationService->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
      }
      elseif ($this->translationService->getSourceStatus($entity) === Lingotek::STATUS_DISABLED) {
        if ($this->translationService->getDocumentId($entity) !== NULL) {
          $this->translationService->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        }
        else {
          $this->translationService->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        }
        $this->translationService->checkTargetStatuses($entity);
      }
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The Tranlsation Profile change for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
  }

  /**
   * Gets the source status of an entity in a format ready to display.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A render array.
   */
  protected function getSourceStatus(ContentEntityInterface $entity) {
    $langcode_source = LingotekLocale::convertLingotek2Drupal($this->translationService->getSourceLocale($entity));
    $language = $this->languageManager->getLanguage($langcode_source);
    $source_status = $this->translationService->getSourceStatus($entity);
    $data = [
      'data' => [
        '#type' => 'lingotek_source_status',
        '#entity' => $entity,
        '#language' => $language,
        '#status' => $source_status,
      ],
    ];
    if ($source_status == Lingotek::STATUS_EDITED && !$this->translationService->getDocumentId($entity)) {
      $data['data']['#context']['status'] = strtolower(Lingotek::STATUS_REQUEST);
    }
    return $data;
  }

  /**
   * Gets the translation status of an entity in a format ready to display.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A render array.
   */
  protected function getTranslationsStatuses(ContentEntityInterface &$entity) {
    $statuses = $this->translationService->getTargetStatuses($entity);
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    array_walk($statuses, function (&$status, $langcode) use ($entity, $profile) {
      if ($profile !== NULL && $profile->hasDisabledTarget($langcode)) {
        $status = Lingotek::STATUS_DISABLED;
      }
    });
    $languages = $this->lingotekConfiguration->getEnabledLanguages();
    foreach ($languages as $langcode => $language) {
      if ($profile !== NULL && $profile->hasDisabledTarget($langcode)) {
        $statuses[$langcode] = Lingotek::STATUS_DISABLED;
      }
    }
    return [
      'data' => [
        '#type' => 'lingotek_target_statuses',
        '#entity' => $entity,
        '#source_langcode' => $entity->language()->getId(),
        '#statuses' => $statuses,
      ],
    ];
  }

  /**
   * Gets all the bundles as options.
   *
   * @return array
   *   The bundles as a valid options array.
   */
  protected function getAllBundles() {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($this->entityTypeId);
    $options = [];
    foreach ($bundles as $id => $bundle) {
      $options[$id] = $bundle['label'];
    }
    return $options;
  }

  /**
   * Gets all the languages as options.
   *
   * @return array
   *   The languages as a valid options array.
   */
  protected function getAllLanguages() {
    $languages = $this->languageManager->getLanguages();
    $options = [];
    foreach ($languages as $id => $language) {
      $options[$id] = $language->getName();
    }
    return $options;
  }

  /**
   * Gets all the groups as options.
   *
   * @return array
   *   The groups as a valid options array.
   */
  protected function getAllGroups() {
    $options = [];
    if ($this->entityTypeId === 'node') {
      /** @var GroupInterface[] $groups */
      $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();
      foreach ($groups as $id => $group) {
        $options[$id] = $group->label();
      }
    }
    return $options;
  }

  /**
   * Get the bulk operations for the management form.
   *
   * @return array
   *   Array with the bulk operations.
   */
  public function generateBulkOptions() {
    $operations = [];
    $operations['upload'] = $this->t('Upload source for translation');
    $operations['check_upload'] = $this->t('Check upload progress');
    $operations[(string) $this->t('Request translations')]['request_translations'] = $this->t('Request all translations');
    $operations[(string) $this->t('Check translation progress')]['check_translations'] = $this->t('Check progress of all translations');
    $operations[(string) $this->t('Download')]['download'] = $this->t('Download all translations');
    if ($this->canHaveDeleteTranslationBulkOptions()) {
      $operations[(string) $this->t('Delete translations')]['delete_translations'] = $this->t('Delete translations');
    }
    foreach ($this->lingotekConfiguration->getProfileOptions() as $profile_id => $profile) {
      $operations[(string) $this->t('Change Translation Profile')]['change_profile:' . $profile_id] = $this->t('Change to @profile Profile', ['@profile' => $profile]);
    }
    $operations[(string) $this->t('Cancel document')]['cancel'] = $this->t('Cancel document');
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $operations[(string) $this->t('Cancel document')]['cancel:' . $langcode] = $this->t('Cancel @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Request translations')]['request_translation:' . $langcode] = $this->t('Request @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Check translation progress')]['check_translation:' . $langcode] = $this->t('Check progress of @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Download')]['download:' . $langcode] = $this->t('Download @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      if ($this->canHaveDeleteTranslationBulkOptions()) {
        $operations[(string) $this->t('Delete translations')]['delete_translation:' . $langcode] = $this->t('Delete @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      }
    }

    $operations['Jobs management'] = [
      'assign_job' => $this->t('Assign Job ID'),
      'clear_job' => $this->t('Clear Job ID'),
    ];

    if ($this->canHaveDeleteBulkOptions()) {
      $operations['delete_nodes'] = $this->t('Delete content');
    }

    $debug_enabled = $this->state->get('lingotek.enable_debug_utilities', FALSE);
    if ($debug_enabled) {
      $operations['debug']['debug.export'] = $this->t('Debug: Export sources as JSON');
    }

    return $operations;
  }

  protected function getDestinationWithQueryArray() {
    return ['destination' => \Drupal::request()->getRequestUri()];
  }

  /**
   * Check if we can delete translation in bulk based on the entity definition.
   *
   * @return bool
   *   TRUE if can delete translation in bulk, FALSE if cannot.
   */
  protected function canHaveDeleteTranslationBulkOptions() {
    return $this->entityTypeManager->getDefinition($this->entityTypeId)
      ->hasLinkTemplate('delete-multiple-form');
  }

  /**
   * Check if we can delete content in bulk based on the entity definition.
   *
   * @return bool
   *   TRUE if can delete translation in bulk, FALSE if cannot.
   */
  protected function canHaveDeleteBulkOptions() {
    return $this->entityTypeManager->getDefinition($this->entityTypeId)
      ->hasLinkTemplate('delete-multiple-form');
  }

}
