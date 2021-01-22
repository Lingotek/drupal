<?php

namespace Drupal\lingotek\Cli;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;

class LingotekCliService {

  const COMMAND_SUCCEDED = 0;
  const COMMAND_ERROR_ENTITY_TYPE_ID = 1;
  const COMMAND_ERROR_ENTITY_NOT_FOUND = 2;

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface|\Drupal\lingotek\Cli\Commands\Drush8\Drush8IoWrapper
   */
  protected $output;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface|\Drupal\lingotek\Cli\Commands\Drush8\Drush8IoWrapper
   */
  protected $logger;

  /**
   * LingotekCliService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The Lingotek content translation service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LingotekContentTranslationServiceInterface $translation_service, LanguageLocaleMapperInterface $language_locale_mapper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->translationService = $translation_service;
    $this->languageLocaleMapper = $language_locale_mapper;
  }

  public function setupOutput($output) {
    $this->output = $output;
  }

  public function setLogger($logger) {
    $this->logger = $logger;
  }

  public function upload($entity_type_id, $entity_id, $job_id = NULL) {
    $entity = $this->getEntity($entity_type_id, $entity_id);
    if ($entity instanceof EntityInterface) {
      $document_id = $this->translationService->uploadDocument($entity, $job_id);
      $this->output->writeln($document_id);
      return self::COMMAND_SUCCEDED;
    }
    // Contains an error message.
    return $entity;
  }

  public function checkUpload($entity_type_id, $entity_id) {
    $entity = $this->getEntity($entity_type_id, $entity_id);
    if ($entity instanceof EntityInterface) {
      $this->translationService->checkSourceStatus($entity);
      $status = $this->translationService->getSourceStatus($entity);
      $this->output->writeln($status);

      return self::COMMAND_SUCCEDED;
    }
    // Contains an error message.
    return $entity;
  }

  public function requestTranslations($entity_type_id, $entity_id, $langcodes = ['all']) {
    $entity = $this->getEntity($entity_type_id, $entity_id);
    if ($entity instanceof EntityInterface) {
      $result = [];
      $languages = [];
      if (in_array('all', $langcodes)) {
        $languages = $this->translationService->requestTranslations($entity);
      }
      else {
        foreach ($langcodes as $langcode) {
          $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
          if ($locale) {
            $targetAdded = $this->translationService->addTarget($entity, $locale);
            if ($targetAdded) {
              $languages[] = $langcode;
            }
            else {
              $this->logger->error('Language %langcode could not be requested.', ['%langcode' => $langcode]);
            }
          }
          else {
            $this->logger->error('Language %langcode is not valid.', ['%langcode' => $langcode]);
          }
        }
      }
      foreach ($languages as $langcode) {
        $result[$langcode] = [
          'langcode' => $langcode,
        ];
      }
      return $result;
    }
    // Contains an error message.
    return $entity;
  }

  public function checkTranslationsStatuses($entity_type_id, $entity_id, $langcodes = ['all']) {
    $entity = $this->getEntity($entity_type_id, $entity_id);
    if ($entity instanceof EntityInterface) {
      $this->translationService->checkTargetStatuses($entity);
      $languages = $this->translationService->getTargetStatuses($entity);
      unset($languages[$entity->getUntranslated()->language()->getId()]);
      $result = [];
      foreach ($languages as $langcode => $status) {
        if (!in_array('all', $langcodes)) {
          if (!in_array($langcode, $langcodes)) {
            continue;
          }
        }
        $result[$langcode] = [
          'langcode' => $langcode,
          'status' => $status,
        ];
      }
      return $result;
    }
    // Contains an error message.
    return $entity;
  }

  public function downloadTranslations($entity_type_id, $entity_id, $langcodes = ['all']) {
    $entity = $this->getEntity($entity_type_id, $entity_id);
    if ($entity instanceof EntityInterface) {
      if (in_array('all', $langcodes)) {
        $this->translationService->downloadDocuments($entity);
      }
      else {
        foreach ($langcodes as $langcode) {
          $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
          if ($locale) {
            $this->translationService->downloadDocument($entity, $locale);
          }
          else {
            $this->logger->error('Language %langcode is not valid.', ['%langcode' => $langcode]);
          }
        }
      }
      return self::COMMAND_SUCCEDED;
    }
    // Contains an error message.
    return $entity;
  }

  public function getEntity($entity_type_id, $entity_id) {
    $entity_storage = NULL;
    if (!$this->entityTypeManager->hasDefinition($entity_type_id) || !$entity_storage = $this->entityTypeManager->getStorage($entity_type_id)) {
      $this->logger->error('Invalid entity type id: @entity_type_id', ['@entity_type_id' => $entity_type_id]);
      return self::COMMAND_ERROR_ENTITY_TYPE_ID;
    }
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $entity_storage->load($entity_id);
    if (!$entity) {
      $this->logger->error('Entity of type @entity_type_id with id @entity_id not found.', ['@entity_type_id' => $entity_type_id, '@entity_id' => $entity_id]);
      return self::COMMAND_ERROR_ENTITY_NOT_FOUND;
    }
    return $entity;
  }

}
