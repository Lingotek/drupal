<?php

/**
 * @file
 * Contains \Drupal\Lingotek\Form\LingotekManagementForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\LingotekSetupTrait;
use Drupal\user\PrivateTempStore;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk management of content.
 */
class LingotekManagementForm extends FormBase {

  use LingotekSetupTrait;

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
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   */
  protected $translationService;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Constructs a new LingotekManagementForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query factory.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The Lingotek content translation service.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param string $entity_type_id
   *   The entity type id.
   */
  public function __construct(EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, QueryFactory $entity_query, LingotekConfigurationServiceInterface $lingotek_configuration, ContentTranslationManagerInterface $content_translation_manager, LingotekContentTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, $entity_type_id) {
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
    $this->entityQuery = $entity_query;
    $this->contentTranslationManager = $content_translation_manager;
    $this->translationService = $translation_service;
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityTypeId = $entity_type_id;
    $this->lingotek = \Drupal::service('lingotek');
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('language_manager'),
      $container->get('entity.query'),
      $container->get('lingotek.configuration'),
      $container->get('content_translation.manager'),
      $container->get('lingotek.content_translation'),
      $container->get('user.private_tempstore'),
      \Drupal::routeMatch()->getParameter('entity_type_id')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_management';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    $entity_type = $this->entityManager->getDefinition($this->entityTypeId);
    $properties = $this->entityManager->getBaseFieldDefinitions($this->entityTypeId);
    $query = $this->entityQuery->get($this->entityTypeId)->pager(10);
    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';

    // Filter results.
    /** @var PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get('lingotek.management.filter.' . $this->entityTypeId);
    $labelFilter = $temp_store->get('label');
    $bundleFilter = $temp_store->get('bundle');
    $profileFilter = $temp_store->get('profile');
    $sourceLanguageFilter = $temp_store->get('source_language');
    if ($has_bundles && $bundleFilter) {
      $query->condition($entity_type->getKey('bundle'), $bundleFilter);
    }
    if ($labelFilter) {
      if ($has_bundles) {
        $query->condition($entity_type->getKey('label'), '%' . $labelFilter . '%', 'LIKE');
      }
      else {
        $query->condition('name', '%' . $labelFilter . '%', 'LIKE');
      }
    }
    if ($profileFilter) {
      $query->condition('lingotek_profile', $profileFilter);
    }
    if ($sourceLanguageFilter) {
      $query->condition($entity_type->getKey('langcode'), $sourceLanguageFilter);
      $query->condition('default_langcode', 1);

    }

    $ids = $query->execute();
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($ids);

    $rows = [];
    foreach ($entities as $entity_id => $entity) {
      $source = $this->getSourceStatus($entity);
      $translations = $this->getTranslationsStatuses($entity);
      $profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE);
      $form['table'][$entity_id] = ['#type' => 'checkbox', '#value'=> $entity->id()];
      $rows[$entity_id] = [];
      if ($has_bundles) {
        $rows[$entity_id]['bundle'] = $this->entityManager->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'];
      }
      $rows[$entity_id] += [
        'title' => $this->getLinkGenerator()->generate($entity->label(), Url::fromRoute($entity->urlInfo()->getRouteName(), [$this->entityTypeId => $entity->id()])),
        'source' => $source,
        'translations' => $translations,
        'profile' => $profile ? $profile->label() : '',
      ];
    }
    $headers = [];
    if ($has_bundles) {
      $headers['bundle'] = $entity_type->getBundleLabel();
    }
    $headers += [
      'title' => $has_bundles ? $properties[$entity_type->getKey('label')]->getLabel() : $entity_type->getLabel(),
      'source' => $this->t('Language source'),
      'translations' => $this->t('Translations'),
      'profile' => $this->t('Profile'),
    ];

    // Add filters.
    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Filter'),
      '#open' => TRUE,
      '#weight' => 5,
      '#tree' => TRUE,
    );
    $form['filters']['wrapper'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('form--inline', 'clearfix')),
    );
    $form['filters']['wrapper']['label'] = array(
      '#type' => 'textfield',
      '#title' => $has_bundles ? $properties[$entity_type->getKey('label')]->getLabel() : $entity_type->getLabel(),
      '#placeholder' => $this->t('Filter by @title', ['@title' => $entity_type->getBundleLabel()]),
      '#default_value' => $labelFilter,
      '#attributes' => array('class' => array('form-item')),
    );
    if ($has_bundles) {
      $form['filters']['wrapper']['bundle'] = array(
        '#type' => 'select',
        '#title' => $entity_type->getBundleLabel(),
        '#options' => ['' => $this->t('All')] + $this->getAllBundles(),
        '#default_value' => $bundleFilter,
        '#attributes' => array('class' => array('form-item')),
      );
    }
    $form['filters']['wrapper']['source_language'] = array(
      '#type' => 'select',
      '#title' => $this->t('Source language'),
      '#options' => ['' => $this->t('All languages')] + $this->getAllLanguages(),
      '#default_value' => $sourceLanguageFilter,
      '#attributes' => array('class' => array('form-item')),
    );
    $form['filters']['wrapper']['profile'] = array(
      '#type' => 'select',
      '#title' => $this->t('Profile'),
      '#options' => ['' => $this->t('All')] + $this->lingotekConfiguration->getProfileOptions(),
      '#default_value' => $profileFilter,
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
    $form['filters']['actions']['reset'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => array('::resetFilterForm'),
    );

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
   * Form submission handler for resetting the filters.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetFilterForm(array &$form, FormStateInterface $form_state) {
    /** @var PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get('lingotek.management.filter.' . $this->entityTypeId);
    $temp_store->delete('label');
    $temp_store->delete('profile');
    $temp_store->delete('source_language');
    $temp_store->delete('bundle');
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
    /** @var PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get('lingotek.management.filter.' . $this->entityTypeId);
    $temp_store->set('label', $form_state->getValue(['filters', 'wrapper', 'label']));
    $temp_store->set('profile', $form_state->getValue(['filters', 'wrapper', 'profile']));
    $temp_store->set('source_language', $form_state->getValue(['filters', 'wrapper', 'source_language']));
    $temp_store->set('bundle', $form_state->getValue(['filters', 'wrapper', 'bundle']));
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
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($values);

    foreach ($entities as $entity) {
      $operations[] = [[$this, $operation], [$entity, $language]];
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
   * Upload source for translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function uploadDocument(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Uploading @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->uploadDocument($entity);
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
  public function checkDocumentUploadStatus(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Checking status of @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->checkSourceStatus($entity);
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
  public function requestTranslations(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Requesting translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->requestTranslations($entity);
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
    }
  }

  /**
   * Checks all translations statuses for a given content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function checkTranslationStatuses(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Checking translation status for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->checkTargetStatuses($entity);
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
  public function checkTranslationStatus(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Checking translation status for @type %label to language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $language]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->checkTargetStatus($entity, $language);
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
   * @param string $language
   *   The language to download.
   */
  public function requestTranslation(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Requesting translation for @type %label to language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $language]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->addTarget($entity, LingotekLocale::convertDrupal2Lingotek($language));
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
   * @param string $language
   *   The language to download.
   */
  public function downloadTranslation(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Downloading translation for @type %label in language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $language]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->downloadDocument($entity, LingotekLocale::convertDrupal2Lingotek($language));
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
  public function downloadTranslations(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Downloading all translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $langcode => $language) {
        if ($langcode !== $entity->language()->getId()) {
          $this->translationService->downloadDocument($entity, LingotekLocale::convertDrupal2Lingotek($langcode));
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
  public function disassociate(ContentEntityInterface $entity, $language, &$context) {
    $context['message'] = $this->t('Disassociating all translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $this->translationService->deleteMetadata($entity);
    }
    else {
      $bundleInfos = $this->entityManager->getBundleInfo($entity->getEntityTypeId());
      drupal_set_message($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]), 'warning');
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
    $language_source = LingotekLocale::convertLingotek2Drupal($this->translationService->getSourceLocale($entity));

    $source_status = $this->translationService->getSourceStatus($entity);
    return array('data' => array(
      '#type' => 'inline_template',
      '#template' => '<span class="language-icon source-{{status}}" title="{{status}}">{{language}}</span>',
      '#context' => array(
        'language' => $this->languageManager->getLanguage($language_source)->getName(),
        'status' => strtolower($source_status),
      ),
    ));
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
    $translations = [];
    if ($entity->lingotek_translation_status && $entity->lingotek_document_id) {
      foreach ($entity->lingotek_translation_status->getIterator() as $delta => $field_value) {
        if ($field_value->language !== $entity->language()->getId()) {
          $translations[$field_value->language] = [
            'status' => $field_value->value,
            'url' => Url::fromRoute('lingotek.workbench', ['doc_id' => $entity->lingotek_document_id->value, 'locale' => LingotekLocale::convertDrupal2Lingotek($field_value->language)]),
          ];
        }
      }
    }
    return $this->formatTranslations($translations);
  }

  /**
   * Formats the translation statuses for display.
   *
   * @param array $translations
   *   Pairs of language - status.
   *
   * @return array
   *   A render array.
   */
  protected function formatTranslations(array $translations) {
    $languages = [];
    foreach ($translations as $langcode => $data) {
      $languages[] = [
        'language' => strtoupper($langcode) ,
        'status' => strtolower($data['status']),
        'url' => $data['url'],
        'render_link' => ($data['status'] !== Lingotek::STATUS_REQUEST) && ($data['status'] !== Lingotek::STATUS_UNTRACKED)
      ];
    }
    return array('data' => array(
      '#type' => 'inline_template',
      '#template' => '{% for language in languages %}{% if language.render_link %} <a href="{{ language.url }}" {%else%} <span {%endif%} class="language-icon target-{{language.status}}" title="{{language.status}}">{{language.language}}{%if language.render_link%}</a>{%else%}</span>{%endif%} {% endfor %}',
      '#context' => array(
        'languages' => $languages,
      ),
    ));

  }

  /**
   * Gets alls the bundles as options.
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
   * Get the bulk operations for the management form.
   *
   * @return array
   *   Array with the bulk operations.
   */
  public function generateOperations() {
    $operations = [];
    $operations['upload'] = $this->t('Upload source for translation');
    $operations['check_upload'] = $this->t('Check upload progress');
    $operations[$this->t('Request translations')]['request_translations'] = $this->t('Request all translations');
    $operations[$this->t('Check translation progress')]['check_translations'] = $this->t('Check progress of all translations');
    $operations[$this->t('Download')]['download'] = $this->t('Download all translations');
    $operations['disassociate'] = $this->t('Disassociate translations');
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $operations[$this->t('Request translations')]['request_translation:' . $langcode] = $this->t('Request @language translation', ['@language' => $language->getName()]);
      $operations[$this->t('Check translation progress')]['check_translation:' . $langcode] = $this->t('Check progress of @language translation', ['@language' => $language->getName()]);
      $operations[$this->t('Download')]['download:' . $langcode] = $this->t('Download @language translation', ['@language' => $language->getName()]);
    }
    return $operations;
  }

}