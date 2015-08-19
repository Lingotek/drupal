<?php

/**
 * Contains \Drupal\Lingotek\Form\LingotekManagementForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\LingotekLocale;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk management of content.
 */
class LingotekManagementForm extends FormBase {

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
   * @param string $entity_type_id
   *   The entity type id.
   */
  public function __construct(EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, QueryFactory $entity_query, $entity_type_id) {
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
    $this->entityQuery = $entity_query;
    $this->entityTypeId = $entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('language_manager'),
      $container->get('entity.query'),
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
    $entity_type = $this->entityManager->getDefinition($this->entityTypeId);
    $query = $this->entityQuery->get($this->entityTypeId);
    $ids = $query->execute();
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($ids);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $rows = [];
    foreach ($entities as $entity_id => $entity) {
      $language_source = LingotekLocale::convertLingotek2Drupal($translation_service->getSourceLocale($entity));
      $translations = $this->getTranslationsStatuses($entity);
      $profile = $this->getProfile($entity);
      $form['table'][$entity_id] = ['#type' => 'checkbox', '#value'=> $entity->id()];
      $rows[$entity_id] = [
        'type' => $this->entityManager->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'],
        'title' => $this->getLinkGenerator()->generate($entity->label(), Url::fromRoute($entity->urlInfo()->getRouteName(), [$this->entityTypeId => $entity->id()])),
        'langcode' => $this->languageManager->getLanguage($language_source)->getName(),
        'translations' => $translations,
        'profile' => $profile ? $profile->label() : '',
      ];
    }
    $headers = [
      'type' => $entity_type->getBundleLabel(),
      'title' => $entity_type->getKey('label'),
      'langcode' => 'Language source',
      'translations' => 'Translations',
      'profile' => 'Profile',
    ];

    // Build an 'Update options' form.
    $form['options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Bulk document management'),
      '#open' => TRUE,
      '#attributes' => array('class' => array('container-inline')),
      '#weight' => 10,
    );

    $options['upload'] = $this->t('Upload source for translation');
    $options['check'] = $this->t('Check progress of translations');
    $options['disassociate'] = $this->t('Disassociate translations');
    $options['Download']['download'] = $this->t('Download all translations');
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $options['Download']['download:' . $langcode] = $this->t('Download @language translation', ['@language' => $language->getName()]);
    }

    $form['options']['operation'] = array(
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $options,
    );
    $form['options']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    );

    $form['table'] = [
      '#header' => $headers,
      '#options' => $rows,
      '#empty' => $this->t('No content available'),
      '#type' => 'tableselect',
      '#weight' => 30,
    ];
    $form['pager'] = [
      '#type' => '#pager',
      '#weight' => 50,
    ];

    return $form;
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
      case 'check':
        $this->createUploadCheckStatusBatch($values);
        $processed = TRUE;
        break;
      case 'download':
        $this->createDownloadBatch($values);
        $processed = TRUE;
        break;
    }
    if (!$processed) {
      if (substr($operation, 0, 9) === 'download:') {
        list($operation, $language) = explode(':', $operation);
        $this->createLanguageDownloadBatch($values, $language);
      }
    }
  }

  /**
   * Create and set an upload batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createUploadBatch($values) {
    $operations = [];
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($values);

    foreach ($entities as $entity) {
      $operations[] = [[$this, 'uploadDocument'], [$entity]];
    }
    $batch = array(
      'title' => t('Uploading content to Lingotek service'),
      'operations' => $operations,
    );
    batch_set($batch);
  }

  /**
   * Create and set an upload check status batch.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createUploadCheckStatusBatch($values) {
    $operations = [];
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($values);

    foreach ($entities as $entity) {
      $operations[] = [[$this, 'checkDocumentUploadStatus'], [$entity]];
    }
    $batch = array(
      'title' => t('Checking uploaded status with Lingotek service'),
      'operations' => $operations,
    );
    batch_set($batch);
  }

  /**
   * Create and set a request target and download batch for all languages.
   *
   * @param array $values
   *   Array of ids to upload.
   */
  protected function createDownloadBatch($values) {
    $requests = [];
    $downloads = [];
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($values);

    foreach ($entities as $entity) {
      $requests[] = [[$this, 'requestTranslations'], [$entity]];
      $downloads[] = [[$this, 'downloadTranslations'], [$entity]];
    }
    $batch = array(
      'title' => t('Requesting translations to Lingotek service'),
      'operations' => array_merge($requests, $downloads),
    );
    batch_set($batch);
  }

  /**
   * Create and set a request target and download batch for a given language.
   *
   * @param array $values
   *   Array of ids to upload.
   * @param string $language
   *   Language code to request.
   */
  protected function createLanguageDownloadBatch($values, $language) {
    $requests = [];
    $downloads = [];
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($values);

    foreach ($entities as $entity) {
      $requests[] = [[$this, 'requestTranslation'], [$entity, $language]];
      $downloads[] = [[$this, 'downloadTranslation'], [$entity, $language]];
    }
    $batch = array(
      'title' => t('Requesting translations to Lingotek service'),
      'operations' => array_merge($requests, $downloads),
    );
    batch_set($batch);
  }

  /**
   * Upload source for translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function uploadDocument(ContentEntityInterface $entity) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->uploadDocument($entity);
  }

  /**
   * Check document upload status for a given content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function checkDocumentUploadStatus(ContentEntityInterface $entity) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->checkSourceStatus($entity);
  }

  /**
   * Request all translations for a given content in a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function requestTranslations(ContentEntityInterface $entity) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->requestTranslations($entity);
  }

  /**
   * Request translations for a given content in a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $language
   *   The language to download.
   */
  public function requestTranslation(ContentEntityInterface $entity, $language) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->addTarget($entity, LingotekLocale::convertDrupal2Lingotek($language));
  }

  /**
   * Download translation for a given content in a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $language
   *   The language to download.
   */
  public function downloadTranslation(ContentEntityInterface $entity, $language) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->downloadDocument($entity, LingotekLocale::convertDrupal2Lingotek($language));
  }

  /**
   * Download translations for a given content in all enabled languages.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function downloadTranslations(ContentEntityInterface $entity) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $langcode => $language) {
      $translation_service->downloadDocument($entity, LingotekLocale::convertDrupal2Lingotek($langcode));
    }
  }

  /**
   * Gets the translation status of an entity in a format ready to display.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   */
  protected function getTranslationsStatuses(ContentEntityInterface &$entity) {
    $translations = [];
    foreach ($entity->lingotek_translation_status->getIterator() as $delta => $field_value) {
      $translations[$field_value->key] = $field_value->value;
    }
    return $this->formatTranslations($translations);
  }

  /**
   * Formats the translation statuses for display.
   *
   * @param array $translations
   *   Pairs of language - status.
   *
   * @return string
   */
  protected function formatTranslations(array $translations) {
    $value = [];
    foreach ($translations as $langcode => $status) {
      $value[] = $langcode . '>' . $status;
    }
    return join('; ', $value);
  }

  /**
   * Gets the profile name for display.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @return LingotekProfile
   *   The profile.
   */
  protected function getProfile(ContentEntityInterface &$entity) {
    $profile = NULL;
    if ($profile_id = $entity->lingotek_profile->target_id) {
      $profile = LingotekProfile::load($profile_id);
    }
    return $profile;
  }

}