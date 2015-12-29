<?php

/**
 * @file
 * Contains \Drupal\Lingotek\Form\LingotekConfigManagementForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigFieldMapper;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\config_translation\ConfigNamesMapper;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\LingotekSetupTrait;
use Drupal\user\PrivateTempStore;
use Drupal\user\PrivateTempStoreFactory;
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
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   */
  protected $translationService;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
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
   *  The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   *   The Lingotek config translation service.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param string $entity_type_id
   *   The entity type id.
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
      $container->get('user.private_tempstore'),
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

    $this->filter = $this->getFilter();

    // ToDo: Find a better filter?
    if ($this->filter === 'config') {
      $mappers = array_filter($this->mappers, function($mapper) {
        return ($mapper instanceof ConfigNamesMapper
          && ! $mapper instanceof ConfigEntityMapper
          && ! $mapper instanceof ConfigFieldMapper)
          ;
      });
    }
    else {
      $mapper = $this->mappers[$this->filter];
      $ids = \Drupal::entityQuery($this->filter)->execute();
      $entities = \Drupal::entityManager()->getStorage($this->filter)->loadMultiple($ids);
      /** @var ConfigEntityMapper $mapper  */
      $mappers = [];
      foreach ($entities as $entity) {
        $new_mapper = clone $mapper;
        $new_mapper->setEntity($entity);
        $mappers[$entity->id()] = $new_mapper;
      }
    }

    $rows = [];
    foreach ($mappers as $mapper_id => $mapper) {
      $source = $this->getSourceStatus($mapper);
      $translations = $this->getTranslationsStatuses($mapper);
      $profile = $mapper instanceof ConfigEntityMapper ?
        $this->lingotekConfiguration->getConfigEntityProfile($mapper->getEntity(), FALSE) :
        $this->lingotekConfiguration->getConfigProfile($mapper_id, FALSE);
      $form['table'][$mapper_id] = ['#type' => 'checkbox', '#value'=> $mapper_id];
      $rows[$mapper_id] = [];
      $rows[$mapper_id] += [
        'title' => $mapper->getTitle(),
        'source' => $source,
        'translations' => $translations,
        'profile' => $profile ? $profile->label() : '',
      ];
    }
    // Add filters.
    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Select config bundle'),
      '#open' => TRUE,
      '#weight' => 5,
      '#tree' => TRUE,
    );
    $form['filters']['wrapper'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('form--inline', 'clearfix')),
    );
    $form['filters']['wrapper']['bundle'] = array(
      '#type' => 'select',
      '#title' => $this->t('Filter'),
      '#options' => ['config' => $this->t('Config')] + $this->getAllBundles(),
      '#default_value' => $this->filter,
      '#attributes' => array('class' => array('form-item')),
    );
    $form['filters']['actions'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('clearfix'),),
    );
    $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#submit' => array('::filterForm'),
    );

    $headers = [
      'title' => $this->t('Entity'),
      'source' => $this->t('Language source'),
      'translations' => $this->t('Translations'),
      'profile' => $this->t('Profile'),
    ];

    // Build an 'Update options' form.
    $form['options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Bulk document management'),
      '#open' => TRUE,
      '#attributes' => array('class' => array('container-inline')),
      '#weight' => 10,
    );
    $form['options']['operation'] = array(
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $this->generateOperations(),
    );
    $form['options']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
    );

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
    /** @var PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get('lingotek.config_management.filter');
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
    /** @var PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get('lingotek.config_management.filter');
    $temp_store->set('bundle', $value);
    $this->filter = $value;
    // If we apply any filters, we need to go to the first page again.
    $form_state->setRedirect('<current>');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operation = $form_state->getValue('operation');
    $values = array_keys(array_filter($form_state->getValue(['table'], function($key, $value){ return $value; })));
    $processed = FALSE;
    switch ($operation) {
      case 'debug.export':
        $this->createDebugExportBatch($values);
        $processed = TRUE;
        break;
      case 'upload':
        $this->createUploadBatch($values);
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
      case 'delete_nodes':
        $this->redirectToDeleteMultipleNodesForm($values, $form_state);
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
    }
  }

  protected function getAllBundles() {
    $mappers = array_filter($this->mappers, function($mapper) {
      return ($mapper instanceof ConfigEntityMapper
        && ! $mapper instanceof ConfigFieldMapper)
        ;
    });
    $bundles = [];
    foreach ($mappers as $bundle => $mapper) {
      /** @var ConfigEntityMapper $mapper */
      $definition = $mapper->getPluginDefinition();
      $bundles[$bundle] = ucwords($definition['title']);
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
   *   The language code for the request. NULL if is not applicable.
   */
  protected function createBatch($operation, $values, $title, $language = NULL) {
    $operations = [];

    $mappers = [];
    if ($this->filter === 'config') {
      foreach ($values as $value) {
        $mappers[$value] = $this->mappers[$value];
      }
    } else {
      $entities = \Drupal::entityManager()->getStorage($this->filter)->loadMultiple($values);
      foreach ($entities as $entity) {
        $mapper = clone $this->mappers[$this->filter];
        $mapper->setEntity($entity);
        $mappers[$entity->id()] = $mapper;
      }
    }

    foreach ($mappers as $mapper) {
      $operations[] = [[$this, $operation], [$mapper, $language]];
    }
    $batch = array(
      'title' => $title,
      'operations' => $operations,
      'finished' => [$this, 'batchFinished'],
      'progressive' => TRUE,
    );
    batch_set($batch);
  }

  public function batchFinished($success, $results, $operations) {
    if ($success) {
      drupal_set_message('Operations completed.');
    }
  }

  /**
   * Create and set an upload batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createUploadBatch($values) {
    $this->createBatch('uploadDocument', $values, $this->t('Uploading content to Lingotek service'));
  }

  /**
   * Create and set an export batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createDebugExportBatch($values) {
    $mappers = [];
    if ($this->filter === 'config') {
      foreach ($values as $value) {
        $mappers[$value] = $this->mappers[$value];
      }
    } else {
      $entities = \Drupal::entityManager()->getStorage($this->filter)->loadMultiple($values);
      foreach ($entities as $entity) {
        $mapper = clone $this->mappers[$this->filter];
        $mapper->setEntity($entity);
        $mappers[$entity->id()] = $mapper;
      }
    }

    foreach ($mappers as $mapper) {
      $operations[] = [[$this, 'debugExport'], [$mapper]];
    }

    $batch = array(
      'title' => $this->t('Exporting config entities (debugging purposes)'),
      'operations' => $operations,
      'finished' => [$this, 'debugExportFinished'],
      'progressive' => TRUE,
    );
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
    $this->createBatch('downloadTranslation', $values, $this->t('Requesting translations to Lingotek service'), $language);
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
   * Export source for debugging purposes.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function debugExport(ConfigMapperInterface $mapper, &$context) {
    $context['message'] = $this->t('Exporting %label.', ['%label' => $mapper->getTitle()]);
    if ($profile = $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE) or TRUE) {
      $data = $this->translationService->getConfigSourceData($mapper);
      $data['_debug'] = [
        'title' => $mapper->getPluginId() . ' (config): ' . $mapper->getTitle(),
        'profile' => $profile ? $profile->id() : '<null>',
        'source_locale' => $this->translationService->getConfigSourceLocale($mapper),
      ];
      $source_data = json_encode($data);
      $filename = 'config.' . $mapper->getPluginId() . '.json';
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
      drupal_set_message($this->t('The %label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
    }
  }

  /**
   * Upload source for translation.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function uploadDocument(ConfigMapperInterface $mapper, $language, &$context) {
    $context['message'] = $this->t('Uploading %label.', ['%label' => $mapper->getTitle()]);

    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $this->translationService->uploadDocument($entity);
      }
      else {
        $this->translationService->uploadConfig($mapper->getPluginId());
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
    }
  }

  /**
   * Check document upload status for a given content.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function checkDocumentUploadStatus(ConfigMapperInterface $mapper, $language, &$context) {
    $context['message'] = $this->t('Checking status of %label.', ['%label' => $mapper->getTitle()]);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $this->translationService->checkSourceStatus($entity);
      }
      else {
        $this->translationService->checkConfigSourceStatus($mapper->getPluginId());
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
    }
  }

  /**
   * Request all translations for a given content.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function requestTranslations(ConfigMapperInterface $mapper, $language, &$context) {
    $result = NULL;
    $context['message'] = $this->t('Requesting translations for %label.', ['%label' => $mapper->getTitle()]);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $result = $this->translationService->requestTranslations($entity);
      }
      else {
        $result = $this->translationService->requestConfigTranslations($mapper->getPluginId());
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
    }
    return $result;
  }

  /**
   * Checks all translations statuses for a given content.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function checkTranslationStatuses(ConfigMapperInterface $mapper, $language, &$context) {
    $context['message'] = $this->t('Checking translation status for %label.', ['%label' => $mapper->getTitle()]);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $this->translationService->checkTargetStatuses($entity);
      }
      else {
        $this->translationService->checkConfigTargetStatuses($mapper->getPluginId());
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
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
  public function checkTranslationStatus(ConfigMapperInterface $mapper, $langcode, &$context) {
    $context['message'] = $this->t('Checking translation status for %label to language @language.', ['%label' => $mapper->getTitle(), '@language' => $langcode]);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $this->translationService->checkTargetStatus($entity, $langcode);
      }
      else {
        $this->translationService->checkConfigTargetStatus($mapper->getPluginId(), $langcode);
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
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
  public function requestTranslation(ConfigMapperInterface $mapper, $langcode, &$context) {
    $context['message'] = $this->t('Requesting translation for %label to language @language.', ['%label' => $mapper->getTitle(), '@language' => $langcode]);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $this->translationService->addTarget($entity, $locale);
      }
      else {
        $this->translationService->addConfigTarget($mapper->getPluginId(), $locale);
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
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
  public function downloadTranslation(ConfigMapperInterface $mapper, $langcode, &$context) {
    $context['message'] = $this->t('Downloading translation for %label in language @language.', ['%label' => $mapper->getTitle(), '@language' => $langcode]);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $this->translationService->downloadDocument($entity, $locale);
      }
      else {
        $this->translationService->downloadConfig($mapper->getPluginId(), $locale);
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
    }
  }

  /**
   * Download translations for a given content in all enabled languages.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function downloadTranslations(ConfigMapperInterface $mapper, $langcode, &$context) {
    $context['message'] = $this->t('Downloading all translations for %label.', ['%label' => $mapper->getTitle()]);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
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
          if ($mapper instanceof ConfigEntityMapper){
            $this->translationService->downloadDocument($entity, $locale);
          }
          else {
            $this->translationService->downloadConfig($mapper->getPluginId(), $locale);
          }
        }
      }
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
    }
  }

  /**
   * Disassociate the content from Lingotek.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   */
  public function disassociate(ConfigMapperInterface $mapper, $langcode, &$context) {
    $context['message'] = $this->t('Disassociating all translations for %label.', ['%label' => $mapper->getTitle()]);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $profile = $mapper instanceof ConfigEntityMapper ?
      $this->lingotekConfiguration->getConfigEntityProfile($entity, FALSE) :
      $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE);

    // If there is no entity, it's a config object and we don't abort based on
    // the profile.
    if ($entity === NULL || $profile !== NULL) {
      if ($mapper instanceof ConfigEntityMapper){
        $this->translationService->deleteMetadata($entity);
      }
      else {
        $this->translationService->deleteConfigMetadata($mapper->getPluginId());
      }
    }

    if ($profile = $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId(), FALSE) or TRUE) {
      $this->translationService->deleteConfigMetadata($mapper->getPluginId());
    }
    else {
      drupal_set_message($this->t('%label has no profile assigned so it was not processed.',
        ['%label' => $mapper->getTitle()]), 'warning');
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
    $entity = $mapper instanceof ConfigEntityMapper ?
      $mapper->getEntity() : NULL;

    $language_source = $this->languageLocaleMapper->getConfigurableLanguageForLocale(
      $mapper instanceof ConfigEntityMapper ?
        $this->translationService->getSourceLocale($entity) :
        $this->translationService->getConfigSourceLocale($mapper)
    );

    $source_status = $mapper instanceof ConfigEntityMapper ?
      $this->translationService->getSourceStatus($entity) :
      $this->translationService->getConfigSourceStatus($mapper);

    $data = array(
      'data' => array(
        '#type' => 'inline_template',
        '#template' => '<span class="language-icon source-{{status}}" title="{{status_title}}">{% if url %}<a href="{{url}}">{%endif%}{{language}}{%if url %}</a>{%endif%}</span>',
        '#context' => array(
          'language' => $language_source->getName(),
          'status' => strtolower($source_status),
          'status_title' => $this->getSourceStatusText($mapper, $source_status),
          'url' => $this->getSourceActionUrl($mapper, $source_status),
        ),
      )
    );
    if ($source_status == Lingotek::STATUS_EDITED && !$this->translationService->getConfigDocumentId($mapper)) {
      $data['data']['#context']['status'] = strtolower(Lingotek::STATUS_REQUEST);
    }
    return $data;
  }

  protected function getSourceStatusText(ConfigMapperInterface $mapper, $status) {
    switch ($status) {
      case Lingotek::STATUS_CURRENT:
        return $this->t('Source uploaded');
      case Lingotek::STATUS_UNTRACKED:
        return $this->t('Never uploaded');
      case Lingotek::STATUS_EDITED:
        return ($this->translationService->getConfigDocumentId($mapper)) ?
         $this->t('Upload') : $this->t('Never uploaded');
      case Lingotek::STATUS_IMPORTING:
        return $this->t('Source importing');
      default:
        return ucfirst(strtolower($status));
    }
  }

  protected function getTargetStatusText(ConfigMapperInterface $mapper, $status, $langcode) {
    $language = ConfigurableLanguage::load($langcode);
    switch ($status) {
      case Lingotek::STATUS_UNTRACKED:
        return $language->label() . ' - ' . $this->t('No translation');
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
      default:
        return $language->label() . ' - ' . ucfirst(strtolower($status));
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
    $translations = [];
    $document_id = $mapper instanceof ConfigEntityMapper ?
      $this->translationService->getDocumentId($mapper->getEntity()) :
      $this->translationService->getConfigDocumentId($mapper);
    $entity =  $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;

    if ($document_id) {
      $translations_statuses = $mapper instanceof ConfigEntityMapper ?
        $this->translationService->getTargetStatuses($entity) :
        $this->translationService->getConfigTargetStatuses($mapper);

      foreach ($translations_statuses as $langcode => $status) {
        if ($langcode !== $mapper->getLangcode()) {
          $translations[$langcode] = [
            'status' => $status,
            'url' => $this->getTargetActionUrl($mapper, $status, $langcode),
            'new_window' => $status == Lingotek::STATUS_CURRENT,
          ];
        }
      }
      $languages = $this->languageManager->getLanguages();
      array_walk($languages, function($language, $langcode) use ($mapper, &$translations) {
        if (!isset($translations[$langcode]) && $langcode !== $mapper->getLangcode()) {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_REQUEST,
            'url' => $this->getTargetActionUrl($mapper, Lingotek::STATUS_REQUEST, $langcode),
            'new_window' => false,
          ];
        }
      });
    }
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
      $languages[] = [
        'language' => strtoupper($langcode),
        'status' => strtolower($data['status']),
        'status_text' => $this->getTargetStatusText($mapper, $data['status'], $langcode),
        'url' => $data['url'],
        'new_window' => $data['new_window']
      ];
    }
    return array(
      'data' => array(
        '#type' => 'inline_template',
        '#template' => '{% for language in languages %}{% if language.url %} <a href="{{ language.url }}" {%if language.new_window%}target="_blank"{%endif%}{%else%} <span {%endif%} class="language-icon target-{{language.status}}" title="{{language.status_text}}">{{language.language}}{%if language.url%}</a>{%else%}</span>{%endif%} {% endfor %}',
        '#context' => array(
          'languages' => $languages,
        ),
      )
    );

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
  public function generateOperations() {
    $operations = [];
    $operations['upload'] = $this->t('Upload source for translation');
    $operations['check_upload'] = $this->t('Check upload progress');
    $operations[(string)$this->t('Request translations')]['request_translations'] = $this->t('Request all translations');
    $operations[(string)$this->t('Check translation progress')]['check_translations'] = $this->t('Check progress of all translations');
    $operations[(string)$this->t('Download')]['download'] = $this->t('Download all translations');
    $operations['disassociate'] = $this->t('Disassociate translations');
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $operations[(string)$this->t('Request translations')]['request_translation:' . $langcode] = $this->t('Request @language translation', ['@language' => $language->getName() . ' (' . $language->getId() .')']);
      $operations[(string)$this->t('Check translation progress')]['check_translation:' . $langcode] = $this->t('Check progress of @language translation', ['@language' => $language->getName() . ' (' . $language->getId() .')']);
      $operations[(string)$this->t('Download')]['download:' . $langcode] = $this->t('Download @language translation', ['@language' => $language->getName()]);
    }

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
    if ($mapper instanceof ConfigEntityMapper) {
      $args['entity_id'] = $mapper->getEntity()->id();
    }
    return $args;
  }


    /**
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   * @param String $source_status
   *
   * @return \Drupal\Core\Url
   */
  protected function getSourceActionUrl(ConfigMapperInterface &$mapper, $source_status) {
    $url = NULL;
    $args = $this->getActionUrlArguments($mapper);
    if ($source_status == Lingotek::STATUS_IMPORTING) {
      $url = Url::fromRoute('lingotek.config.check_upload',
        $args,
        ['query' => $this->getDestinationArray()]);
    }
    if ($source_status == Lingotek::STATUS_EDITED || $source_status == Lingotek::STATUS_UNTRACKED) {
      if ($doc_id = $this->translationService->getConfigDocumentId($mapper)) {
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
    if ($target_status == Lingotek::STATUS_REQUEST) {
        $url = Url::fromRoute('lingotek.config.request',
          $args + ['locale' => $locale],
          ['query' => $this->getDestinationArray()]);
    }
    if ($target_status == Lingotek::STATUS_PENDING ||
        $target_status == Lingotek::STATUS_EDITED) {
      $url = Url::fromRoute('lingotek.config.check_download',
        $args + ['locale' => $locale],
        ['query' => $this->getDestinationArray()]);
    }
    if ($target_status == Lingotek::STATUS_READY) {
      $url = Url::fromRoute('lingotek.config.download',
        $args + ['locale' => $locale],
        ['query' => $this->getDestinationArray()]);
    }
    if ($target_status == Lingotek::STATUS_CURRENT) {
      $url = Url::fromRoute('lingotek.workbench', [
        'doc_id' => $document_id,
        'locale' => $locale
      ]);
    }
    return $url;
  }

}
