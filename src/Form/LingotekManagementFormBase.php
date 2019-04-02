<?php

namespace Drupal\lingotek\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Helpers\LingotekManagementFormHelperTrait;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\LingotekSetupTrait;
use Drupal\user\PrivateTempStoreFactory;

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
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity query factory service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

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
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query factory.
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
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param string $entity_type_id
   *   The entity type id.
   */
  public function __construct(Connection $connection, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, QueryFactory $entity_query, LingotekInterface $lingotek, LingotekConfigurationServiceInterface $lingotek_configuration, LanguageLocaleMapperInterface $language_locale_mapper, ContentTranslationManagerInterface $content_translation_manager, LingotekContentTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, StateInterface $state, ModuleHandlerInterface $module_handler, $entity_type_id) {
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
    $this->entityTypeId = $entity_type_id;
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
        '#default_value' => $documentIdFilter,
       ];
      $form['filters']['advanced_options']['entity_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Entity ID'),
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
          Lingotek::STATUS_ERROR => $this->t('Error'),
          Lingotek::STATUS_INTERMEDIATE => $this->t('Interim'),
          Lingotek::STATUS_REQUEST => $this->t('Not Requested'),
        ],
      ];
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
    foreach ($keys as $key) {
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
      '#type' => 'textfield',
      '#size' => 50,
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
    $entity_type = $this->entityManager->getDefinition($entityTypeId);
    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';

    if ($has_bundles) {
      $bundleInfo = $this->entityManager->getBundleInfo($entityTypeId);
      $row['bundle'] = $bundleInfo[$entity->bundle()]['label'];
    }

    $row += [
      'title' => $entity->hasLinkTemplate('canonical') ? $this->getLinkGenerator()
        ->generate($entity->label(), Url::fromRoute($entity->urlInfo()
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
      case 'disassociate':
        $this->createDisassociateBatch($values);
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
      $operations[] = [[$this, $operation], [$entity, $language, $job_id]];
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
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   */
  public function batchFinished($success, $results, $operations) {
    if ($success) {
      $batch = &batch_get();
      drupal_set_message('Operations completed.');
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
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   */
  public function debugExportFinished($success, $results, $operations) {
    if ($success) {
      $links = [];
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
      drupal_set_message($this->t('Exports available at: @exports',
        ['@exports' => drupal_render($build)]));
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
   * Create and set a disassociate batch.
   *
   * @param array $values
   *   Array of ids to disassociate.
   */
  protected function createDisassociateBatch($values) {
    $this->createBatch('disassociate', $values, $this->t('Disassociating content from Lingotek service'));
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
    if (((float) \Drupal::VERSION) < 8.6) {
      drupal_set_message($this->t('Deletion of nodes is only available with Drupal > 8.6'), 'error');
      return;
    }
    $entityInfo = [];
    $entities = $this->getSelectedEntities($values);
    foreach ($entities as $entity) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $language = $entity->getUntranslated()->language();
      $entityInfo[$entity->id()] = [$language->getId() => $language->getId()];
    }
    \Drupal::getContainer()->get('tempstore.private')
      ->get('entity_delete_multiple_confirm')
      ->set($this->currentUser()->id() . ':node', $entityInfo);
    $form_state->setRedirect('entity.' . $this->entityTypeId . '.delete_multiple_form', [], ['query' => $this->getDestinationWithQueryArray()]);
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
    \Drupal::getContainer()->get('tempstore.private')
      ->get('lingotek_assign_job_entity_multiple_confirm')
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
    \Drupal::getContainer()->get('tempstore.private')
      ->get('lingotek_assign_job_entity_multiple_confirm')
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
    if (((float) \Drupal::VERSION) < 8.6) {
      drupal_set_message($this->t('Deletion of translations is only available with Drupal > 8.6'), 'error');
      return;
    }
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
      \Drupal::getContainer()->get('tempstore.private')
        ->get('entity_delete_multiple_confirm')
        ->set($this->currentUser()->id() . ':node', $entityInfo);
      $form_state->setRedirect('entity.' . $this->entityTypeId . '.delete_multiple_form', [], ['query' => $this->getDestinationWithQueryArray()]);
    }
    else {
      drupal_set_message($this->t('No valid translations for deletion.'), 'warning');
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
    if (((float) \Drupal::VERSION) < 8.6) {
      drupal_set_message($this->t('Deletion of translations is only available with Drupal > 8.6'), 'error');
      return;
    }
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
      \Drupal::getContainer()->get('tempstore.private')
        ->get('entity_delete_multiple_confirm')
        ->set($this->currentUser()->id() . ':node', $entityInfo);
      $form_state->setRedirect('entity.' . $this->entityTypeId . '.delete_multiple_form', [], ['query' => $this->getDestinationWithQueryArray()]);
    }
    else {
      drupal_set_message($this->t('No valid translations for deletion.'), 'warning');
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
        'created' => REQUEST_TIME,
        'changed' => REQUEST_TIME,
      ]);
      file_put_contents($file->getFileUri(), $source_data);
      $file->save();
      $context['results']['exported'][] = $file->id();
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity)) {
      try {
        $this->translationService->uploadDocument($entity, $job_id);
      }
      catch (LingotekApiException $exception) {
        $this->translationService->setSourceStatus($entity, Lingotek::STATUS_ERROR);
        if ($this->translationService->getDocumentId($entity)) {
          drupal_set_message(t('The update for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
        }
        else {
          drupal_set_message(t('The upload for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
        }
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->checkSourceStatus($entity);
      }
      catch (LingotekApiException $exception) {
        drupal_set_message(t('The upload status check for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $result = $this->translationService->requestTranslations($entity);
      }
      catch (LingotekApiException $exception) {
        drupal_set_message(t('The request for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->checkTargetStatuses($entity);
      }
      catch (LingotekApiException $exception) {
        drupal_set_message(t('The request for @entity_type %title translation status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->checkTargetStatus($entity, $language);
      }
      catch (LingotekApiException $exception) {
        drupal_set_message(t('The request for @entity_type %title translation status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->addTarget($entity, $locale);
      }
      catch (LingotekApiException $exception) {
        drupal_set_message(t('The request for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    $context['message'] = $this->t('Downloading translation for @type %label in language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $langcode]);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->downloadDocument($entity, $locale);
      }
      catch (LingotekApiException $exception) {
        drupal_set_message(t('The download for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }
      catch (LingotekContentEntityStorageException $storage_exception) {
        drupal_set_message(t('The download for @entity_type %title failed because of the length of one field translation value: %table.',
          ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%table' => $storage_exception->getTable()]), 'error');
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
              drupal_set_message(t('The download for @entity_type %title translation failed. Please try again.', [
                '@entity_type' => $entity->getEntityTypeId(),
                '%title' => $entity->label(),
              ]), 'error');
            }
          }
        }
      }
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
    }
  }

  /**
   * Disassociate the content from Lingotek.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function disassociate(ContentEntityInterface $entity, $language, $job_id, &$context) {
    $context['message'] = $this->t('Disassociating all translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->deleteMetadata($entity);
      }
      catch (LingotekApiException $exception) {
        drupal_set_message(t('The deletion of @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
      }

    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
      drupal_set_message(t('The Tranlsation Profile change for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
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
    $bundles = $this->entityManager->getBundleInfo($this->entityTypeId);
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
      $groups = $this->entityManager->getStorage('group')->loadMultiple();
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
    if (((float) \Drupal::VERSION) >= 8.6) {
      $operations[(string) $this->t('Delete translations')]['delete_translations'] = $this->t('Delete translations');
    }
    foreach ($this->lingotekConfiguration->getProfileOptions() as $profile_id => $profile) {
      $operations[(string) $this->t('Change Translation Profile')]['change_profile:' . $profile_id] = $this->t('Change to @profile Profile', ['@profile' => $profile]);
    }
    $operations['disassociate'] = $this->t('Disassociate content');
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $operations[(string) $this->t('Request translations')]['request_translation:' . $langcode] = $this->t('Request @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Check translation progress')]['check_translation:' . $langcode] = $this->t('Check progress of @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Download')]['download:' . $langcode] = $this->t('Download @language translation', ['@language' => $language->getName()]);
      if (((float) \Drupal::VERSION) >= 8.6) {
        $operations[(string) $this->t('Delete translations')]['delete_translation:' . $langcode] = $this->t('Delete @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      }
    }

    $operations['Jobs management'] = [
      'assign_job' => $this->t('Assign Job ID'),
      'clear_job' => $this->t('Clear Job ID'),
    ];

    // We add the delete operation in nodes and comments, as we have those
    // operations in core.
    if ($this->entityTypeId === 'node' && (float) \Drupal::VERSION < 8.5) {
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

}
