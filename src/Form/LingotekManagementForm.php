<?php

/**
 * Contains \Drupal\Lingotek\Form\LingotekManagementForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\LingotekTranslatableEntity;

/**
 * Form for bulk management of content.
 */
class LingotekManagementForm extends FormBase {

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
    $entity_type_id = 'node';

    /** @var EntityListBuilderInterface $list_builder */
    $query = \Drupal::entityQuery($entity_type_id);
    $ids = $query->execute();
    $entities = \Drupal::entityManager()->getStorage($entity_type_id)->loadMultiple($ids);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $rows = [];
    foreach ($entities as $entity_id => $entity) {
      $language_source = LingotekLocale::convertLingotek2Drupal($translation_service->getSourceLocale($entity));
      $translations = $this->getTranslationsStatuses($entity);
      $form['table'][$entity_id] = ['#type' => 'checkbox', '#value'=> $entity->id()];
      $rows[$entity_id] = [
        'type' => \Drupal::entityManager()->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'],
        'title' => \Drupal::linkGenerator()->generate($entity->label(), Url::fromRoute('entity.node.canonical', ['node' => $entity->id()])),
        'langcode' => \Drupal::languageManager()->getLanguage($language_source)->getName(),
        'translations' => $translations,
        'profile' => $this->getProfile($entity),
      ];
    }
    $headers = [
      'type' => 'Content type',
      'title' => 'Title',
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
    foreach (\Drupal::languageManager()->getLanguages() as $langcode => $language) {
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
    $entity_type_id = 'node';
    $entities = \Drupal::entityManager()->getStorage($entity_type_id)->loadMultiple($values);

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
    $entity_type_id = 'node';
    $entities = \Drupal::entityManager()->getStorage($entity_type_id)->loadMultiple($values);

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
    $entity_type_id = 'node';
    $entities = \Drupal::entityManager()->getStorage($entity_type_id)->loadMultiple($values);

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
    $entity_type_id = 'node';
    $entities = \Drupal::entityManager()->getStorage($entity_type_id)->loadMultiple($values);

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
    $languages = \Drupal::languageManager()->getLanguages();
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
   * @return string
   *   The profile name.
   */
  protected function getProfile(ContentEntityInterface &$entity) {
    $te = LingotekTranslatableEntity::load(\Drupal::service('lingotek'), $entity);
    $profile = $te->getProfile();
    return $profile;
  }

}