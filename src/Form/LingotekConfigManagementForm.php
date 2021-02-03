<?php

namespace Drupal\lingotek\Form;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigFieldMapper;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\config_translation\ConfigNamesMapper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekSetupTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk management of content.
 */
class LingotekConfigManagementForm extends FormBase {

  use LingotekSetupTrait;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

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
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * A array of configuration mapper instances.
   *
   * @var \Drupal\config_translation\ConfigMapperInterface[]
   */
  protected $mappers;

  /**
   * The type of config to display.
   *
   * @var string
   */
  protected $filter;

  /**
   * Constructs a new LingotekManagementForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   *   The Lingotek config translation service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\config_translation\ConfigMapperInterface[] $mappers
   *   The configuration mappers.
   */
  public function __construct(LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, array $mappers) {
    $this->languageManager = $language_manager;
    $this->translationService = $translation_service;
    $this->tempStoreFactory = $temp_store_factory;
    $this->lingotek = \Drupal::service('lingotek');
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->mappers = $mappers;
    $this->filter = 'config';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('lingotek.config_translation'),
      $container->get('tempstore.private'),
      $container->get('plugin.manager.config_translation.mapper')->getMappers()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_config_management';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $showingFields = FALSE;

    $this->filter = $this->getFilter();
    $temp_store = $this->getFilterTempStore();
    $jobFilter = $temp_store->get('job');

    // Create the headers first so they can be used for sorting.
    $headers = [
      'title' => [
        'data' => $this->t('Entity'),
      ],
      'source' => $this->t('Source'),
      'translations' => $this->t('Translations'),
      'profile' => $this->t('Profile'),
      'job_id' => $this->t('Job ID'),
    ];

    // ToDo: Find a better filter?
    if ($this->filter === 'config') {
      $mappers = array_filter($this->mappers, function ($mapper) {
        return ($mapper instanceof ConfigNamesMapper
          && !$mapper instanceof ConfigEntityMapper
          && !$mapper instanceof ConfigFieldMapper);
      });
    }
    elseif (substr($this->filter, -7) == '_fields') {
      $showingFields = TRUE;
      $mapper = $this->mappers[$this->filter];
      $base_entity_type = $mapper->getPluginDefinition()['base_entity_type'];

      // If we are showing field config instances, we need to show bundles for
      // a better UX.
      $headers = [
        'bundle' => [
          'data' => $this->t('Bundle'),
          'specifier' => 'bundle',
        ],
      ] + $headers;

      // Make the table sortable by field label.
      $headers['title']['specifier'] = 'label';

      $ids = \Drupal::entityQuery('field_config')
        ->condition('id', $base_entity_type . '.', 'STARTS_WITH')
        ->tableSort($headers)
        ->execute();
      $fields = FieldConfig::loadMultiple($ids);
      $mappers = [];
      foreach ($fields as $id => $field) {
        $new_mapper = clone $mapper;
        $new_mapper->setEntity($field);
        $mappers[$field->id()] = $new_mapper;
      }
    }
    else {
      $mapper = $this->mappers[$this->filter];
      $query = \Drupal::entityQuery($this->filter);
      $label_filter = $temp_store->get('label');

      // Determine the machine name of the title for this entity type.
      $entity_storage = \Drupal::entityTypeManager()->getStorage($this->filter);
      $entity_keys = $entity_storage->getEntityType()->getKeys();
      if (isset($entity_keys['label'])) {
        $label_key = $entity_keys['label'];
        if ($label_filter) {
          $query->condition($label_key, $label_filter, 'CONTAINS');
        }

        $headers['title']['specifier'] = $label_key;
        $query->tableSort($headers);
      }

      $ids = $query->execute();
      $entities = $entity_storage->loadMultiple($ids);
      /** @var \Drupal\config_translation\ConfigEntityMapper $mapper  */
      $mappers = [];
      foreach ($entities as $entity) {
        $new_mapper = clone $mapper;
        $new_mapper->setEntity($entity);
        $mappers[$entity->id()] = $new_mapper;
      }
    }

    $rows = [];
    foreach ($mappers as $mapper_id => $mapper) {
      if (!in_array($mapper->getLangcode(), [LanguageInterface::LANGCODE_NOT_SPECIFIED, LanguageInterface::LANGCODE_NOT_APPLICABLE])) {
        $is_config_entity = $mapper instanceof ConfigEntityMapper;

        $source = $this->getSourceStatus($mapper);
        $translations = $this->getTranslationsStatuses($mapper);

        // We select those that we want if there is a filter for job ID.
        $job_id = $this->getMetadataJobId($mapper);
        if (!empty($jobFilter)) {
          $found = strpos($job_id, $jobFilter);
          if ($found === FALSE || $found < 0) {
            continue;
          }
        }

        $profile = $is_config_entity ?
          $this->lingotekConfiguration->getConfigEntityProfile($mapper->getEntity()) :
          $this->lingotekConfiguration->getConfigProfile($mapper_id, FALSE);
        $form['table'][$mapper_id] = [
          '#type' => 'checkbox',
          '#value' => $mapper_id,
        ];
        $rows[$mapper_id] = [];
        $rows[$mapper_id] += [
          'title' => trim($mapper->getTitle()),
          'source' => $source,
          'translations' => $translations,
          'profile' => $profile ? $profile->label() : '',
          'job_id' => $job_id ?: '',
        ];
        if ($is_config_entity) {
          $link = NULL;
          if ($mapper->getEntity()->hasLinkTemplate('canonical')) {
            $link = $mapper->getEntity()->toLink(trim($mapper->getTitle()));
          }
          elseif ($mapper->getEntity()->hasLinkTemplate('edit-form')) {
            $link = $mapper->getEntity()
              ->toLink(trim($mapper->getTitle()), 'edit-form');
          }
          if ($link !== NULL) {
            $rows[$mapper_id]['title'] = $link;
          }
        }

        if ($showingFields) {
          $entity_type_id = $mapper->getEntity()->get('entity_type');
          $bundle = $mapper->getEntity()->get('bundle');
          $bundle_info = \Drupal::service('entity_type.bundle.info')
            ->getBundleInfo($entity_type_id);
          if (isset($bundle_info[$bundle])) {
            $rows[$mapper_id]['bundle'] = trim($bundle_info[$bundle]['label']);
          }
          else {
            $rows[$mapper_id]['bundle'] = trim($bundle);
          }
        }
      }
    }
    // Add filters.
    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Select config bundle'),
      '#open' => TRUE,
      '#weight' => 5,
      '#tree' => TRUE,
    ];
    $form['filters']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
    ];
    $form['filters']['wrapper']['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter'),
      '#options' => ['config' => $this->t('Simple configuration')] + $this->getAllBundles(),
      '#default_value' => $this->filter,
      '#attributes' => ['class' => ['form-item']],
    ];
    if ($mapper instanceof ConfigEntityMapper) {
      $form['filters']['wrapper']['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $temp_store->get('label'),
        '#attributes' => ['class' => ['form-item']],
      ];
    }
    $form['filters']['wrapper']['job'] = [
      '#type' => 'lingotek_job_id',
      '#title' => $this->t('Job ID'),
      '#default_value' => $jobFilter,
      '#attributes' => ['class' => ['form-item']],
    ];
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

    // Build an 'Update options' form.
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Bulk document management'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['container-inline']],
      '#weight' => 10,
    ];
    $form['options']['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $this->generateBulkOptions(),
    ];
    $form['options']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
    ];
    $form['options']['job_id'] = [
      '#type' => 'lingotek_job_id',
      '#title' => $this->t('Job ID'),
      '#description' => $this->t('Assign a job id that you can filter on later on the TMS or in this page.'),
    ];
    $form['table'] = [
      '#header' => $headers,
      '#options' => $rows,
      '#empty' => $this->t('No content available'),
      '#type' => 'tableselect',
      '#weight' => 30,
    ];
    $form['pager'] = [
      '#type' => 'pager',
      '#weight' => 50,
    ];
    $form['#attached']['library'][] = 'lingotek/lingotek';
    return $form;
  }

  /**
   * Gets the filter to be applied. By default will be 'config'.
   *
   * @return string
   */
  protected function getFilter() {
    /** @var \Drupal\user\PrivateTempStore $temp_store */
    $temp_store = $this->getFilterTempStore();
    $value = $temp_store->get('bundle');
    if (!$value) {
      $value = 'config';
    }
    return $value;
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
    $value = $form_state->getValue(['filters', 'wrapper', 'bundle']);
    $job_id = $form_state->getValue(['filters', 'wrapper', 'job']) ?: NULL;
    $label = $form_state->getValue(['filters', 'wrapper', 'label']) ?: NULL;

    /** @var \Drupal\user\PrivateTempStore $temp_store */
    $temp_store = $this->getFilterTempStore();
    $temp_store->set('bundle', $value);
    $temp_store->set('job', $job_id);
    $temp_store->set('label', trim($label));
    $this->filter = $value;
    // If we apply any filters, we need to go to the first page again.
    $form_state->setRedirect('<current>');
  }

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
    $temp_store = $this->getFilterTempStore();
    $temp_store->delete('bundle');
    $temp_store->delete('job');
    $temp_store->delete('label');
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
        $this->redirectToAssignJobIdMultipleConfigForm($values, $form_state);
        $processed = TRUE;
        break;

      case 'clear_job':
        $this->redirectToClearJobIdMultipleConfigForm($values, $form_state);
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
      if (0 === strpos($operation, 'change_profile:')) {
        list($operation, $profile_id) = explode(':', $operation);
        $this->createChangeProfileBatch($values, $profile_id);
        $processed = TRUE;
      }
    }
  }

  /**
   * Redirect to assign Job ID form.
   *
   * @param array $values
   *   Array of ids to assign a Job ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function redirectToAssignJobIdMultipleConfigForm($values, FormStateInterface $form_state) {
    $entityInfo = [];
    $mappers = $this->getSelectedMappers($values);
    foreach ($mappers as $mapper_id => $mapper) {
      /** @var \Drupal\config_translation\ConfigNamesMapper $mapper */
      $langcode = $mapper->getLangcode();
      $entityInfo[$this->filter][$mapper_id] = [$langcode => $langcode];
    }
    $this->tempStoreFactory->get('lingotek_assign_job_config_multiple_confirm')
      ->set($this->currentUser()->id(), $entityInfo);
    $form_state->setRedirect('lingotek.assign_job_config_multiple_form', [], ['query' => $this->getDestinationWithQueryArray()]);
  }

  /**
   * Redirect to clear Job ID form.
   *
   * @param array $values
   *   Array of ids to clear Job ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function redirectToClearJobIdMultipleConfigForm($values, FormStateInterface $form_state) {
    $entityInfo = [];
    $mappers = $this->getSelectedMappers($values);
    foreach ($mappers as $mapper_id => $mapper) {
      /** @var \Drupal\config_translation\ConfigNamesMapper $mapper */
      $langcode = $mapper->getLangcode();
      $entityInfo[$this->filter][$mapper_id] = [$langcode => $langcode];
    }
    $this->tempStoreFactory->get('lingotek_assign_job_config_multiple_confirm')
      ->set($this->currentUser()->id(), $entityInfo);
    $form_state->setRedirect('lingotek.clear_job_config_multiple_form', [], ['query' => $this->getDestinationWithQueryArray()]);
  }

  protected function getAllBundles() {
    $mappers = array_filter($this->mappers, function ($mapper) {
      // Filter config entity mappers and config field mappers.
      return ($mapper instanceof ConfigEntityMapper);
    });
    $bundles = [];
    foreach ($mappers as $bundle => $mapper) {
      /** @var \Drupal\config_translation\ConfigEntityMapper $mapper */
      $bundles[$bundle] = $mapper->getTypeLabel();
    }
    return $bundles;
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
    $operations = $this->generateOperations($operation, $values, $language, $job_id);
    $batch = [
      'title' => $title,
      'operations' => $operations,
      'finished' => [$this, 'batchFinished'],
      'progressive' => TRUE,
    ];
    batch_set($batch);
  }

  public function batchFinished($success, $results, $operations) {
    if ($success) {
      $this->messenger()->addStatus('Operations completed.');
    }
  }

  /**
   * Create and set an upload batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createUploadBatch($values, $job_id) {
    $this->createBatch('uploadDocument', $values, $this->t('Uploading content to Lingotek service'), NULL, $job_id);
  }

  /**
   * Create and set an export batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createDebugExportBatch($values) {
    $operations = $this->generateOperations('debugExport', $values, NULL);
    $batch = [
      'title' => $this->t('Exporting config entities (debugging purposes)'),
      'operations' => $operations,
      'finished' => [$this, 'debugExportFinished'],
      'progressive' => TRUE,
    ];
    batch_set($batch);
  }

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
      $this->messenger()->addStatus($this->t('Exports available at: @exports',
        ['@exports' => \Drupal::service('renderer')->render($build)]));
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
    $this->createBatch('downloadTranslation', $values, $this->t('Downloading translation to Lingotek service'), $language);
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
   * Export source for debugging purposes.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function debugExport(ConfigMapperInterface $mapper, $language, $job_id, &$context) {
    $context['message'] = $this->t('Exporting %label.', ['%label' => $mapper->getTitle()]);
    $profile = ($mapper instanceof ConfigEntityMapper) ?
      $this->lingotekConfiguration->getConfigEntityProfile($mapper->getEntity()) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId());

    $data = $this->translationService->getConfigSourceData($mapper);
    $data['_debug'] = [
      'title' => trim($mapper->getPluginId() . ' (config): ' . $mapper->getTitle()),
      'profile' => $profile ? $profile->id() : '<null>',
      'source_locale' => $this->translationService->getConfigSourceLocale($mapper),
    ];
    $filename = 'config.' . $mapper->getPluginId() . '.json';
    $plugin_definition = $mapper->getPluginDefinition();
    if (isset($plugin_definition['entity_type']) && 'field_config' === $plugin_definition['entity_type']) {
      $entity = $mapper->getEntity();
      $data['_debug']['title'] = $entity->id() . ' (config): ' . $entity->label();
      $filename = 'config.' . $entity->id() . '.json';
    }
    $source_data = json_encode($data);

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

  /**
   * Upload source for translation.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function uploadDocument(ConfigMapperInterface $mapper, $language, $job_id, &$context) {
    $context['message'] = $this->t('Uploading %label.', ['%label' => $mapper->getTitle()]);

    /** @var \Drupal\Core\Config\ConfigEntityInterface $entity */
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);
    $document_id = $mapper instanceof ConfigEntityMapper ?
      $this->translationService->getDocumentId($entity) :
      $this->translationService->getConfigDocumentId($mapper);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
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
        catch (LingotekApiException $e) {
          if ($document_id) {
            $this->messenger()->addError($this->t('%label update failed. Please try again.',
              ['%label' => $entity->label()]));
          }
          else {
            $this->messenger()->addError($this->t('%label upload failed. Please try again.',
              ['%label' => $entity->label()]));
          }
        }
      }
      else {
        try {
          $this->translationService->uploadConfig($mapper->getPluginId(), $job_id);
        }
        catch (LingotekPaymentRequiredException $exception) {
          $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
        }
        catch (LingotekDocumentArchivedException $exception) {
          $this->messenger()->addError(t('Document %label has been archived. Please upload again.',
            ['%label' => $mapper->getTitle()]));
        }
        catch (LingotekDocumentLockedException $exception) {
          $this->messenger()->addError(t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
        catch (LingotekApiException $e) {
          if ($document_id) {
            $this->messenger()->addError($this->t('%label update failed. Please try again.',
              ['%label' => $mapper->getTitle()]));
          }
          else {
            $this->messenger()->addError($this->t('%label upload failed. Please try again.',
              ['%label' => $mapper->getTitle()]));
          }
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Check document upload status for a given content.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function checkDocumentUploadStatus(ConfigMapperInterface $mapper, $language, $job_id, &$context) {
    $context['message'] = $this->t('Checking status of %label.', ['%label' => $mapper->getTitle()]);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
        try {
          $this->translationService->checkSourceStatus($entity);
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label upload failed. Please try again.',
            ['%label' => $entity->label()]));
        }
      }
      else {
        try {
          $this->translationService->checkConfigSourceStatus($mapper->getPluginId());
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label upload failed. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Request all translations for a given content.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function requestTranslations(ConfigMapperInterface $mapper, $language, $job_id, &$context) {
    $result = NULL;
    $context['message'] = $this->t('Requesting translations for %label.', ['%label' => $mapper->getTitle()]);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
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
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('Document @entity_type %title translations request failed. Please try again.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '%title' => $entity->label(),
          ]));
        }
      }
      else {
        try {
          $result = $this->translationService->requestConfigTranslations($mapper->getPluginId());
        }
        catch (LingotekPaymentRequiredException $exception) {
          $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
        }
        catch (LingotekDocumentArchivedException $exception) {
          $this->messenger()->addError(t('Document %label has been archived. Please upload again.',
            ['%label' => $mapper->getTitle()]));

        }
        catch (LingotekDocumentLockedException $exception) {
          $this->messenger()->addError(t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label translations request failed. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
    return $result;
  }

  /**
   * Checks all translations statuses for a given content.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function checkTranslationStatuses(ConfigMapperInterface $mapper, $language, $job_id, &$context) {
    $context['message'] = $this->t('Checking translation status for %label.', ['%label' => $mapper->getTitle()]);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
        try {
          $this->translationService->checkTargetStatuses($entity);
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label translation statuses check failed. Please try again.',
            ['%label' => $entity->label()]));
        }
      }
      else {
        try {
          $this->translationService->checkConfigTargetStatuses($mapper->getPluginId());
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label translation statuses check failed. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Checks translation status for a given content in a given language.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   * @param string $langcode
   *   The language to check.
   */
  public function checkTranslationStatus(ConfigMapperInterface $mapper, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Checking translation status for %label to language @language.', ['%label' => $mapper->getTitle(), '@language' => $langcode]);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
        try {
          $this->translationService->checkTargetStatus($entity, $locale);
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label @locale translation status check failed. Please try again.',
            ['%label' => $entity->label(), '@locale' => $locale]));
        }
      }
      else {
        try {
          $this->translationService->checkConfigTargetStatus($mapper->getPluginId(), $locale);
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label @locale translation status check failed. Please try again.',
            ['%label' => $mapper->getTitle(), '@locale' => $locale]));
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Request translations for a given content in a given language.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   * @param string $langcode
   *   The language to download.
   */
  public function requestTranslation(ConfigMapperInterface $mapper, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Requesting translation for %label to language @language.', ['%label' => $mapper->getTitle(), '@language' => $langcode]);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
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
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('Document @entity_type %title @locale translation request failed. Please try again.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '%title' => $entity->label(),
            '@locale' => $locale,
          ]));
        }
      }
      else {
        try {
          $this->translationService->addConfigTarget($mapper->getPluginId(), $locale);
        }
        catch (LingotekPaymentRequiredException $exception) {
          $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
        }
        catch (LingotekDocumentArchivedException $exception) {
          $this->messenger()->addError(t('Document %label has been archived. Please upload again.',
            ['%label' => $mapper->getTitle()]));
        }
        catch (LingotekDocumentLockedException $exception) {
          $this->messenger()->addError(t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
        catch (LingotekApiException $e) {
          $this->messenger()
            ->addError($this->t('Document %label @locale translation request failed. Please try again.', [
              '%label' => $mapper->getTitle(),
              '@locale' => $locale,
            ]));
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Download translation for a given content in a given language.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   * @param string $langcode
   *   The language to download.
   */
  public function downloadTranslation(ConfigMapperInterface $mapper, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Downloading translation for %label in language @language.', ['%label' => $mapper->getTitle(), '@language' => $langcode]);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      $this->performTranslationDownload($mapper, $entity, $locale);
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Download translations for a given content in all enabled languages.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function downloadTranslations(ConfigMapperInterface $mapper, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Downloading all translations for %label.', ['%label' => $mapper->getTitle()]);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $langcode => $language) {
        if ($langcode !== $mapper->getLangcode()) {
          $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
          $this->performTranslationDownload($mapper, $entity, $locale);
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Cancel the content from Lingotek.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function cancel(ConfigMapperInterface $mapper, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Cancelling all translations for %label.', ['%label' => $mapper->getTitle()]);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
        try {
          $this->translationService->cancelDocument($entity);
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label cancellation failed. Please try again.',
            ['%label' => $entity->label()]));
        }
      }
      else {
        try {
          $this->translationService->cancelConfigDocument($mapper->getPluginId());
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('%label cancellation failed. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
      }
    }
    elseif ($profile = $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE) or TRUE) {
      try {
        $this->translationService->cancelConfigDocument($mapper->getPluginId());
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t('%label cancellation failed. Please try again.',
          ['%label' => $mapper->getTitle()]));
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Cancel the content from Lingotek.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function cancelTarget(ConfigMapperInterface $mapper, $langcode, $job_id, &$context) {
    $context['message'] = $this->t('Cancelling translation for %label to language @language.', ['%label' => $mapper->getTitle(), '@language' => $langcode]);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper) {
        try {
          $this->translationService->cancelDocumentTarget($entity, $locale);
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('Cancelling translation for %label to language @language failed. Please try again.',
            ['%label' => $entity->label(), '@language' => $langcode]));
        }
      }
      else {
        try {
          $this->translationService->cancelConfigDocumentTarget($mapper->getPluginId(), $locale);
        }
        catch (LingotekApiException $e) {
          $this->messenger()->addError($this->t('Cancelling translation for %label to language @language failed. Please try again.',
            ['%label' => $mapper->getTitle(), '@language' => $langcode]));
        }
      }
    }
    elseif ($profile = $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE) or TRUE) {
      try {
        $this->translationService->cancelConfigDocumentTarget($mapper->getPluginId());
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t('Cancelling translation for %label to language @language. Please try again.',
          ['%label' => $mapper->getTitle(), '@language' => $langcode]));
      }
    }
    else {
      $this->messenger()->addWarning($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]));
    }
  }

  /**
   * Change Translation Profile.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function changeProfile(ConfigMapperInterface $mapper, $profile_id = NULL, $job_id = NULL, &$context = NULL) {
    $context['message'] = $this->t('Changing Translation Profile for @type %label.', [
      '@type' => $mapper->getTypeLabel(),
      '%label' => $mapper->getTitle(),
    ]);
    try {
      /** @var \Drupal\Core\Config\ConfigEntityInterface $entity */
      $entity = ($mapper instanceof ConfigEntityMapper) ? $mapper->getEntity() : NULL;
      if ($mapper instanceof ConfigEntityMapper) {
        $this->lingotekConfiguration->setConfigEntityProfile($entity, $profile_id);
      }
      else {
        $this->lingotekConfiguration->setConfigProfile($mapper->getPluginId(), $profile_id);
      }
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The Translation Profile change for %title failed. Please try again.', ['%title' => $mapper->getTitle()]));
    }
    if ($profile_id === Lingotek::PROFILE_DISABLED) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
      $entity = ($mapper instanceof ConfigEntityMapper) ? $mapper->getEntity() : NULL;
      if ($mapper instanceof ConfigEntityMapper) {
        $this->translationService->setSourceStatus($entity, Lingotek::STATUS_DISABLED);
        $this->translationService->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
      }
      else {
        $this->translationService->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
        $this->translationService->setConfigSourceStatus($mapper, Lingotek::STATUS_DISABLED);
      }
    }
    else {
      if ($mapper instanceof ConfigEntityMapper) {
        $entity = $mapper->getEntity();
        if ($this->translationService->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
          if ($this->translationService->getDocumentId($entity) !== NULL) {
            $this->translationService->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          }
          else {
            $this->translationService->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          }
          if ($this->translationService->getDocumentId($entity)) {
            $this->translationService->checkTargetStatuses($entity);
          }
        }
      }
      else {
        if ($this->translationService->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
          if ($this->translationService->getConfigDocumentId($mapper) !== NULL) {
            $this->translationService->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
          }
          else {
            $this->translationService->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
          }
          if ($this->translationService->getConfigDocumentId($mapper)) {
            $this->translationService->checkConfigTargetStatuses($mapper->getPluginId());
          }
        }
      }
    }
  }

  /**
   * Gets the source status of an config in a format ready to display.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   *
   * @return array
   *   A render array.
   */
  protected function getSourceStatus(ConfigMapperInterface $mapper) {
    $is_config_entity = $mapper instanceof ConfigEntityMapper;
    $entity = $is_config_entity ? $mapper->getEntity() : NULL;

    $language_source = $this->languageLocaleMapper->getConfigurableLanguageForLocale(
      $is_config_entity ?
        $this->translationService->getSourceLocale($entity) :
        $this->translationService->getConfigSourceLocale($mapper)
    );

    $source_status = $is_config_entity ?
      $this->translationService->getSourceStatus($entity) :
      $this->translationService->getConfigSourceStatus($mapper);

    $data = [
      'data' => [
        '#type' => 'inline_template',
        '#template' => '<span class="language-icon source-{{status}}" title="{{status_title}}">{% if url %}<a href="{{url}}">{%endif%}{{language}}{%if url %}</a>{%endif%}</span>',
        '#context' => [
          'language' => empty($language_source) ? 'N/A' : strtoupper($language_source->id()),
          'status' => strtolower($source_status),
          'status_title' => $this->getSourceStatusText($mapper, $source_status),
          'url' => $this->getSourceActionUrl($mapper, $source_status),
        ],
      ],
    ];
    return $data;
  }

  protected function getSourceStatusText(ConfigMapperInterface $mapper, $status) {
    $is_config_entity = $mapper instanceof ConfigEntityMapper;
    $entity = $is_config_entity ? $mapper->getEntity() : NULL;

    switch ($status) {
      case Lingotek::STATUS_UNTRACKED:
      case Lingotek::STATUS_REQUEST:
        return $this->t('Upload');

      case Lingotek::STATUS_DISABLED:
        return $this->t('Disabled, cannot request translation');

      case Lingotek::STATUS_EDITED:
        return ($is_config_entity ? $this->translationService->getDocumentId($entity) : $this->translationService->getConfigDocumentId($mapper)) ?
          $this->t('Re-upload (content has changed since last upload)') : $this->t('Upload');

      case Lingotek::STATUS_IMPORTING:
        return $this->t('Source importing');

      case Lingotek::STATUS_CURRENT:
        return $this->t('Source uploaded');

      case Lingotek::STATUS_ERROR:
        return $this->t('Error');

      case Lingotek::STATUS_CANCELLED:
        return $this->t('Cancelled by user');

      default:
        return ucfirst(strtolower($status));
    }
  }

  protected function getTargetStatusText(ConfigMapperInterface $mapper, $status, $langcode) {
    $language = ConfigurableLanguage::load($langcode);
    if ($language) {
      switch ($status) {
        case Lingotek::STATUS_UNTRACKED:
          return $language->label() . ' - ' . $this->t('Translation exists, but it is not being tracked by Lingotek');

        case Lingotek::STATUS_REQUEST:
          return $language->label() . ' - ' . $this->t('Request translation');

        case Lingotek::STATUS_PENDING:
          return $language->label() . ' - ' . $this->t('In-progress');

        case Lingotek::STATUS_READY:
          return $language->label() . ' - ' . $this->t('Ready for Download');

        case Lingotek::STATUS_CURRENT:
          return $language->label() . ' - ' . $this->t('Current');

        case Lingotek::STATUS_EDITED:
          return $language->label() . ' - ' . $this->t('Not current');

        case Lingotek::STATUS_INTERMEDIATE:
          return $language->label() . ' - ' . $this->t('In-progress (interim translation downloaded)');

        case Lingotek::STATUS_ERROR:
          return $language->label() . ' - ' . $this->t('Error');

        case Lingotek::STATUS_CANCELLED:
          return $language->label() . ' - ' . $this->t('Cancelled by user');

        default:
          return $language->label() . ' - ' . ucfirst(strtolower($status));
      }
    }
  }

  /**
   * Gets the translation status of an entity in a format ready to display.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   *
   * @return array
   *   A render array.
   */
  protected function getTranslationsStatuses(ConfigMapperInterface &$mapper) {
    $is_config_entity = $mapper instanceof ConfigEntityMapper;
    $translations = [];
    $languages = $this->languageManager->getLanguages();
    $languages = array_filter($languages, function (LanguageInterface $language) {
      $configLanguage = ConfigurableLanguage::load($language->getId());
      return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
    });

    $document_id = $is_config_entity ?
      $this->translationService->getDocumentId($mapper->getEntity()) :
      $this->translationService->getConfigDocumentId($mapper);
    $entity = $is_config_entity ? $mapper->getEntity() : NULL;

    $translations_statuses = $mapper instanceof ConfigEntityMapper ?
      $this->translationService->getTargetStatuses($entity) :
      $this->translationService->getConfigTargetStatuses($mapper);

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, TRUE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId());

    array_walk($translations_statuses, function (&$status, $langcode) use ($entity, $profile) {
      if ($profile !== NULL && $profile->hasDisabledTarget($langcode)) {
        $status = Lingotek::STATUS_DISABLED;
      }
    });

    foreach ($languages as $langcode => $language) {
      // Show the untracked translations in the bulk management form, unless it's the
      // source one.
      if ($mapper->hasTranslation($language) && $mapper->getLangcode() !== $langcode) {
        $translations[$langcode] = [
          'status' => Lingotek::STATUS_UNTRACKED,
          'url' => NULL,
          'new_window' => FALSE,
        ];
      }
    }

    foreach ($translations_statuses as $langcode => $status) {
      if (isset($languages[$langcode]) && $langcode !== $mapper->getLangcode() && array_key_exists($langcode, $languages)) {
        if ($mapper->hasTranslation($languages[$langcode]) && $status == Lingotek::STATUS_REQUEST) {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_UNTRACKED,
            'url' => $this->getTargetActionUrl($mapper, Lingotek::STATUS_UNTRACKED, $langcode),
            'new_window' => $status == Lingotek::STATUS_CURRENT,
          ];
        }
        else {
          $translations[$langcode] = [
            'status' => $status,
            'url' => $this->getTargetActionUrl($mapper, $status, $langcode),
            'new_window' => $status == Lingotek::STATUS_CURRENT,
          ];
        }
      }
    }
    array_walk($languages, function ($language, $langcode) use ($document_id, $mapper, &$translations, $profile) {
      if ($document_id && !isset($translations[$langcode]) && $langcode !== $mapper->getLangcode()) {
        if ($profile !== NULL && $profile->hasDisabledTarget($langcode)) {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_DISABLED,
            'url' => NULL,
            'new_window' => FALSE,
          ];
        }
        else {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_REQUEST,
            'url' => $this->getTargetActionUrl($mapper, Lingotek::STATUS_REQUEST, $langcode),
            'new_window' => FALSE,
          ];
        }
      }
    });
    ksort($translations);
    return $this->formatTranslations($mapper, $translations);
  }

  /**
   * Formats the translation statuses for display.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   * @param array $translations
   *   Pairs of language - status.
   *
   * @return array
   *   A render array.
   */
  protected function formatTranslations(ConfigMapperInterface $mapper, array $translations) {
    $languages = [];
    foreach ($translations as $langcode => $data) {
      if ($this->languageManager->getLanguage($langcode)) {
        $languages[] = [
          'language' => $langcode,
          'status' => $data['status'],
          'status_text' => $this->getTargetStatusText($mapper, $data['status'], $langcode),
          'url' => $data['url'],
          'new_window' => $data['new_window'],
        ];
      }
    }
    return [
      'data' => [
        '#type' => 'lingotek_target_statuses',
        '#mapper' => $mapper,
        '#source_langcode' => $mapper->getLangcode(),
        '#statuses' => $languages,
      ],
    ];
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
    $operations[(string) $this->t('Cancel document')]['cancel'] = $this->t('Cancel document');
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $operations[(string) $this->t('Cancel document')]['cancel:' . $langcode] = $this->t('Cancel @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Request translations')]['request_translation:' . $langcode] = $this->t('Request @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Check translation progress')]['check_translation:' . $langcode] = $this->t('Check progress of @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
      $operations[(string) $this->t('Download')]['download:' . $langcode] = $this->t('Download @language translation', ['@language' => $language->getName() . ' (' . $language->getId() . ')']);
    }
    foreach ($this->lingotekConfiguration->getProfileOptions() as $profile_id => $profile) {
      $operations[(string) $this->t('Change Translation Profile')]['change_profile:' . $profile_id] = $this->t('Change to @profile Profile', ['@profile' => $profile]);
    }
    $operations['Jobs management'] = [
      'assign_job' => $this->t('Assign Job ID'),
      'clear_job' => $this->t('Clear Job ID'),
    ];
    $debug_enabled = \Drupal::state()->get('lingotek.enable_debug_utilities', FALSE);
    if ($debug_enabled) {
      $operations['debug']['debug.export'] = $this->t('Debug: Export sources as JSON');
    }

    return $operations;
  }

  protected function getActionUrlArguments(ConfigMapperInterface &$mapper) {
    $args = [
      'entity_type' => $mapper->getPluginId(),
      'entity_id' => $mapper->getPluginId(),
    ];
    if ($mapper instanceof ConfigEntityMapper && !$mapper instanceof ConfigFieldMapper) {
      $args['entity_id'] = $mapper->getEntity()->id();
    }
    elseif ($mapper instanceof ConfigFieldMapper) {
      $args['entity_type'] = $mapper->getType();
      $args['entity_id'] = $mapper->getEntity()->id();
    }
    return $args;
  }

  /**
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   * @param string $source_status
   *
   * @return \Drupal\Core\Url
   */
  protected function getSourceActionUrl(ConfigMapperInterface &$mapper, $source_status) {
    $url = NULL;
    $args = $this->getActionUrlArguments($mapper);
    $document_id = $mapper instanceof ConfigEntityMapper ?
      $this->translationService->getDocumentId($mapper->getEntity()) :
      $this->translationService->getConfigDocumentId($mapper);

    if ($source_status == Lingotek::STATUS_IMPORTING) {
      $url = Url::fromRoute('lingotek.config.check_upload',
        $args,
        ['query' => $this->getDestinationArray()]);
    }
    if ($source_status == Lingotek::STATUS_EDITED || $source_status == Lingotek::STATUS_UNTRACKED || $source_status == Lingotek::STATUS_ERROR || $source_status == Lingotek::STATUS_CANCELLED) {
      if ($document_id) {
        $url = Url::fromRoute('lingotek.config.update',
          $args,
          ['query' => $this->getDestinationArray()]);
      }
      else {
        $url = Url::fromRoute('lingotek.config.upload',
          $args,
          ['query' => $this->getDestinationArray()]);
      }
    }
    return $url;
  }

  protected function getTargetActionUrl(ConfigMapperInterface &$mapper, $target_status, $langcode) {
    $url = NULL;
    $args = $this->getActionUrlArguments($mapper);

    $document_id = $mapper instanceof ConfigEntityMapper ?
      $this->translationService->getDocumentId($mapper->getEntity()) :
      $this->translationService->getConfigDocumentId($mapper);

    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    if ($locale) {
      if ($target_status == Lingotek::STATUS_REQUEST || $target_status == Lingotek::STATUS_UNTRACKED) {
        $url = Url::fromRoute('lingotek.config.request',
          $args + ['locale' => $locale],
          ['query' => $this->getDestinationArray()]);
      }
      if ($target_status == Lingotek::STATUS_PENDING) {
        $url = Url::fromRoute('lingotek.config.check_download',
          $args + ['locale' => $locale],
          ['query' => $this->getDestinationArray()]);
      }
      if ($target_status == Lingotek::STATUS_READY || $target_status == Lingotek::STATUS_ERROR) {
        $url = Url::fromRoute('lingotek.config.download',
          $args + ['locale' => $locale],
          ['query' => $this->getDestinationArray()]);
      }
      if ($target_status == Lingotek::STATUS_CURRENT ||
          $target_status == Lingotek::STATUS_INTERMEDIATE ||
          $target_status == Lingotek::STATUS_EDITED) {
        $url = Url::fromRoute('lingotek.workbench', [
          'doc_id' => $document_id,
          'locale' => $locale,
        ]);
      }
    }
    return $url;
  }

  /**
   * Generates an array of operations to be performed in a batch.
   *
   * @param string $operation
   *   The operation (method of this object) to be executed.
   * @param array $values
   *   The mappers this operation will be applied to.
   * @param $language
   *   The language to be passed to that operation.
   * @param $job_id
   *   The job ID to be passed to that operation.
   *
   * @return array
   *   An array of operations suitable for a batch.
   */
  protected function generateOperations($operation, $values, $language, $job_id = NULL) {
    $operations = [];

    $mappers = $this->getSelectedMappers($values);

    foreach ($mappers as $mapper) {
      $operations[] = [[$this, $operation], [$mapper, $language, $job_id]];
    }
    return $operations;
  }

  /**
   * Actually performs the translation download.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper to be used.
   * @param $entity
   *   The entity (in case it is a config entity mapper).
   * @param $locale
   *   The locale to be downloaded.
   */
  protected function performTranslationDownload(ConfigMapperInterface $mapper, $entity, $locale) {
    if ($mapper instanceof ConfigEntityMapper) {
      try {
        if ($this->translationService->checkTargetStatus($entity, $locale)) {
          $success = $this->translationService->downloadDocument($entity, $locale);
          if ($success === FALSE) {
            $this->messenger()->addError($this->t('%label @locale translation download failed. Please try again.',
              ['%label' => $entity->label(), '@locale' => $locale]));
          }
        }
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t('%label @locale translation download failed. Please try again.',
          ['%label' => $entity->label(), '@locale' => $locale]));
      }
    }
    else {
      try {
        if ($this->translationService->checkConfigTargetStatus($mapper->getPluginId(), $locale)) {
          $success = $this->translationService->downloadConfig($mapper->getPluginId(), $locale);
          if ($success === FALSE) {
            $this->messenger()->addError($this->t('%label @locale translation download failed. Please try again.',
              ['%label' => $mapper->getTitle(), '@locale' => $locale]));
          }
        }
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t('%label @locale translation download failed. Please try again.',
          ['%label' => $mapper->getTitle(), '@locale' => $locale]));
      }
    }
  }

  protected function getMetadataJobId($mapper) {
    $job_id = NULL;
    $metadata = NULL;
    if ($mapper instanceof ConfigEntityMapper) {
      $entity = $mapper->getEntity();
      $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    }
    elseif ($mapper instanceof ConfigMapperInterface) {
      $config_names = $mapper->getConfigNames();
      if ($config_names) {
        $config_name = reset($config_names);
        $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      }
    }
    if ($metadata !== NULL) {
      $job_id = $metadata->getJobId();
    }
    return $job_id;
  }

  /**
   * Gets the select mappers from their IDs.
   *
   * @param $values
   *   Array of ids.
   *
   * @return \Drupal\config_translation\ConfigNamesMapper[]
   *   The mappers.
   */
  protected function getSelectedMappers($values) {
    $mappers = [];
    if ($this->filter === 'config') {
      foreach ($values as $value) {
        $mappers[$value] = $this->mappers[$value];
      }
    }
    elseif (substr($this->filter, -7) == '_fields') {
      $mapper = $this->mappers[$this->filter];
      $ids = \Drupal::entityQuery('field_config')
        ->condition('id', $values)
        ->execute();
      $fields = FieldConfig::loadMultiple($ids);
      $mappers = [];
      foreach ($fields as $id => $field) {
        $new_mapper = clone $mapper;
        $new_mapper->setEntity($field);
        $mappers[$field->id()] = $new_mapper;
      }
    }
    else {
      $entities = \Drupal::entityTypeManager()
        ->getStorage($this->filter)
        ->loadMultiple($values);
      foreach ($entities as $entity) {
        $mapper = clone $this->mappers[$this->filter];
        $mapper->setEntity($entity);
        $mappers[$entity->id()] = $mapper;
      }
    }
    return $mappers;
  }

  protected function getDestinationWithQueryArray() {
    return ['destination' => \Drupal::request()->getRequestUri()];
  }

  protected function getFilterTempStore() {
    return $this->tempStoreFactory->get('lingotek.config_management.filter');
  }

}
