<?php

namespace Drupal\lingotek;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\config_translation\ConfigNamesMapper;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Service for managing Lingotek configuration translations.
 */
class LingotekConfigTranslationService implements LingotekConfigTranslationServiceInterface {

  /**
   * @var \Drupal\lingotek\LingotekInterface
   */
  protected $lingotek;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

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
   * A array of configuration mapper instances.
   *
   * @var \Drupal\config_translation\ConfigMapperInterface[]
   */
  protected $mappers;

  /**
   * Constructs a new LingotekConfigTranslationService object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   An lingotek object.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   An entity manager object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $mapper_manager
   *   The configuration mapper manager.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, ConfigMapperManagerInterface $mapper_manager) {
    $this->lingotek = $lingotek;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
    $this->configMapperManager = $mapper_manager;
    $this->mappers = $mapper_manager->getMappers();
  }

  /**
   * {@inheritDoc}
   */
  public function getEnabledConfigTypes() {
    $enabled_types = [];
    foreach ($this->mappers as $mapper) {
      if ($mapper instanceof ConfigEntityMapper) {
        $enabled = $this->isEnabled($mapper->getPluginId());
        if ($enabled) {
          $enabled_types[] = $mapper->getPluginId();
        }
      }
    }
    return $enabled_types;
  }

  /**
   * {@inheritDoc}
   */
  public function isEnabled($plugin_id) {
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.config.' . $plugin_id . '.enabled';
    $result = !!$config->get($key);
    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function setEnabled($plugin_id, $enabled = TRUE) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.config.' . $plugin_id . '.enabled';
    $config->set($key, $enabled)->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigTranslatableProperties(ConfigNamesMapper $mapper) {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config */
    $typed_config = \Drupal::service('config.typed');

    $properties = [];
    foreach ($mapper->getConfigNames() as $name) {
      $schema = $typed_config->get($name);
      $properties[$name] = $this->getTranslatableProperties($schema, NULL);
    }
    return $properties;
  }

  /**
   * Get the translatable properties for this schema.
   *
   * @param $schema
   *   The schema
   * @param $base_key
   *   The base name for constructing the canonical name.
   * @return array
   *   Canonical names of the translatable properties.
   */
  protected function getTranslatableProperties($schema, $base_key) {
    $properties = [];
    $definition = $schema->getDataDefinition();
    if (isset($definition['form_element_class'])) {
      foreach ($schema as $key => $element) {
        $element_key = isset($base_key) ? "$base_key.$key" : $key;
        $definition = $element->getDataDefinition();

        if ($element instanceof TraversableTypedDataInterface) {
          $properties = array_merge($properties, $this->getTranslatableProperties($element, $element_key));
        }
        else {
          if (isset($definition['form_element_class'])) {
            $properties[] = $element_key;
          }
        }
      }
    }
    return $properties;
  }

  public function getDocumentId(ConfigEntityInterface $entity) {
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    return $metadata->getDocumentId();
  }

  public function setDocumentId(ConfigEntityInterface &$entity, $document_id) {
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $metadata->setDocumentId($document_id)->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceStatus(ConfigEntityInterface &$entity) {
    $status = Lingotek::STATUS_UNTRACKED;
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $source_status = $metadata->getSourceStatus();
    if ($source_status !== NULL && isset($source_status[$entity->language()->getId()])) {
      $status = $source_status[$entity->language()->getId()];
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceStatus(ConfigEntityInterface &$entity, $status) {
    $source_language = NULL;
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $translation_source = $metadata->getSourceStatus();
    if ($translation_source) {
      $source_language = key($translation_source);
    }
    if ($source_language == LanguageInterface::LANGCODE_NOT_SPECIFIED || $source_language == NULL) {
      $source_language = $entity->language()->getId();
    }
    $status_value = [$source_language => $status];
    $metadata->setSourceStatus($status_value)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatus(ConfigEntityInterface &$entity, $langcode) {
    $status = Lingotek::STATUS_UNTRACKED;
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $translation_status = $metadata->getTargetStatus();
    if (count($translation_status) > 0 && isset($translation_status[$langcode])) {
      $status = $translation_status[$langcode];
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatuses(ConfigEntityInterface &$entity) {
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $translation_status = $metadata->getTargetStatus();
    return $translation_status;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatus(ConfigEntityInterface &$entity, $langcode, $status, $save = TRUE) {
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $translation_status = $metadata->getTargetStatus();
    $translation_status[$langcode] = $status;
    $metadata->setTargetStatus($translation_status);
    if ($save) {
      $metadata->save();
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatuses(ConfigEntityInterface &$entity, $status) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->language()->getId();

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getTargetStatus($entity, $langcode)) {
        if ($current_status === Lingotek::STATUS_PENDING && $status === Lingotek::STATUS_REQUEST) {
          // Don't allow to pass from pending to request. We have been already
          // requested this one.
          continue;
        }
        if (in_array($current_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_DISABLED, NULL]) && $status === Lingotek::STATUS_PENDING) {
          continue;
        }
        if ($current_status == $status) {
          continue;
        }
        if ($current_status != Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_CURRENT) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
        elseif ($current_status == Lingotek::STATUS_EDITED && in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_PENDING])) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasEntityChanged(ConfigEntityInterface &$entity) {
    $source_data = json_encode($this->getSourceData($entity));
    $hash = md5($source_data);

    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $old_hash = $metadata->getHash();
    if (!$old_hash || strcmp($hash, $old_hash)) {
      $metadata->setHash($hash)->save();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function markTranslationsAsDirty(ConfigEntityInterface &$entity) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $entity->language()->getId();

    // Only mark as out of date the current ones.
    $to_change = [
      Lingotek::STATUS_CURRENT,
      // Lingotek::STATUS_PENDING,
      // Lingotek::STATUS_INTERMEDIATE,
      // Lingotek::STATUS_READY,
    ];

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getTargetStatus($entity, $langcode)) {
        if (in_array($current_status, $to_change)) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_PENDING);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceData(ConfigEntityInterface $entity) {
    /** @var \Drupal\config_translation\ConfigEntityMapper $mapper */
    if ($entity->getEntityTypeId() == 'field_config') {
      $id = $entity->getTargetEntityTypeId();
      $mapper = clone $this->mappers[$id . '_fields'];
      $mapper->setEntity($entity);
    }
    else {
      $mapper = clone $this->mappers[$entity->getEntityTypeId()];
      $mapper->setEntity($entity);
    }
    $data = $this->getConfigSourceData($mapper);
    // For retro-compatibility, if there is only one config name, we plain our
    // data.
    $names = $mapper->getConfigNames();
    if (count($names) == 1) {
      $data = $data[$names[0]];
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLocale(ConfigEntityInterface &$entity) {
    $locale = NULL;
    $source_language = $entity->language()->getId();
    if (!in_array($source_language, [LanguageInterface::LANGCODE_NOT_SPECIFIED, LanguageInterface::LANGCODE_NOT_APPLICABLE])) {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($source_language);
    }
    return $locale;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument(ConfigEntityInterface $entity, $job_id = NULL) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($entity);
    }
    if (!empty($this->getDocumentId($entity))) {
      return $this->updateDocument($entity, $job_id);
    }
    $source_data = $this->getSourceData($entity);
    $extended_name = $entity->id() . ' (config): ' . $entity->label();
    $profile_preference = $profile->getAppendContentTypeToTitle();
    $global_preference = $this->lingotekConfiguration->getPreference('append_type_to_title');
    switch ($profile_preference) {
      case 'yes':
        $document_name = $extended_name;
        break;
      case 'no':
        $document_name = $entity->label();
        break;
      case 'global_setting':
        $document_name = $global_preference ? $extended_name : $entity->label();
        break;
      default:
        $document_name = $extended_name;
    }

    $url = $entity->hasLinkTemplate('edit-form') ? $entity->toUrl()->setAbsolute()->toString() : NULL;

    // Allow other modules to alter the data before is uploaded.
    \Drupal::moduleHandler()->invokeAll('lingotek_config_entity_document_upload', [&$source_data, &$entity, &$url]);
    $encoded_data = json_encode($source_data);

    $document_id = $this->lingotek->uploadDocument($document_name, $encoded_data, $this->getSourceLocale($entity), $url, $this->lingotekConfiguration->getConfigEntityProfile($entity), $job_id);
    if ($document_id) {
      $this->setDocumentId($entity, $document_id);
      $this->lingotekConfiguration->setConfigEntityProfile($entity, $profile->id());
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_REQUEST);
      $this->setJobId($entity, $job_id);
      return $document_id;
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkSourceStatus(ConfigEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $document_id = $this->getDocumentId($entity);
    if ($document_id && $this->lingotek->getDocumentStatus($document_id)) {
      $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
      return TRUE;
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument(ConfigEntityInterface &$entity, $job_id = NULL) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($entity);
    }
    $source_data = $this->getSourceData($entity);
    $document_id = $this->getDocumentId($entity);
    $extended_name = $entity->id() . ' (config): ' . $entity->label();
    $profile_preference = $profile->getAppendContentTypeToTitle();
    $global_preference = $this->lingotekConfiguration->getPreference('append_type_to_title');
    switch ($profile_preference) {
      case 'yes':
        $document_name = $extended_name;
        break;
      case 'no':
        $document_name = $entity->label();
        break;
      case 'global_setting':
        $document_name = $global_preference ? $extended_name : $entity->label();
        break;
      default:
        $document_name = $extended_name;
    }

    $url = $entity->hasLinkTemplate('edit-form') ? $entity->toUrl()->setAbsolute()->toString() : NULL;

    // Allow other modules to alter the data before is uploaded.
    \Drupal::moduleHandler()->invokeAll('lingotek_config_entity_document_upload', [&$source_data, &$entity, &$url]);
    $encoded_data = json_encode($source_data);

    if ($this->lingotek->updateDocument($document_id, $source_data, $url, $document_name, $this->lingotekConfiguration->getConfigEntityProfile($entity), $job_id)) {
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_PENDING);
      $this->setJobId($entity, $job_id);
      return $document_id;
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addTarget(ConfigEntityInterface &$entity, $locale) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    if ($locale == $this->languageLocaleMapper->getLocaleForLangcode($entity->language()->getId())) {
      // We don't want to translate from one language to itself.
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $source_status = $this->getSourceStatus($entity);
      $current_status = $this->getTargetStatus($entity, $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId());
      if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT && $current_status !== Lingotek::STATUS_READY) {
        if ($this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getConfigEntityProfile($entity))) {
          $this->setTargetStatus($entity, $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId(), Lingotek::STATUS_PENDING);
          // If the status was "Importing", and the target was added
          // successfully, we can ensure that the content is current now.
          if ($source_status == Lingotek::STATUS_IMPORTING) {
            $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          }
          return TRUE;
        }
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslations(ConfigEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $languages = [];
    if ($document_id = $this->getDocumentId($entity)) {
      $target_languages = $this->languageManager->getLanguages();
      $target_languages = array_filter($target_languages, function (LanguageInterface $language) {
        $configLanguage = ConfigurableLanguage::load($language->getId());
        return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
      });

      $entity_langcode = $entity->language()->getId();

      foreach ($target_languages as $langcode => $language) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
        if ($langcode !== $entity_langcode) {
          $source_status = $this->getSourceStatus($entity);
          $current_status = $this->getTargetStatus($entity, $langcode);
          if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT && $current_status !== Lingotek::STATUS_EDITED  && $current_status !== Lingotek::STATUS_READY) {
            if ($this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getConfigEntityProfile($entity))) {
              $languages[] = $langcode;
              $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_PENDING);
              // If the status was "Importing", and the target was added
              // successfully, we can ensure that the content is current now.
              if ($source_status == Lingotek::STATUS_IMPORTING) {
                $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
              }
            }
          }
        }
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatus(ConfigEntityInterface &$entity, $locale) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
    $current_status = $this->getTargetStatus($entity, $langcode);
    $document_id = $this->getDocumentId($entity);
    $source_status = $this->getSourceStatus($entity);
    if (($current_status == Lingotek::STATUS_PENDING ||
    $current_status == Lingotek::STATUS_EDITED) &&
    $source_status !== Lingotek::STATUS_EDITED) {
      if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) === TRUE) {
        $current_status = Lingotek::STATUS_READY;
        $this->setTargetStatus($entity, $langcode, $current_status);
      }
      // We may not be ready, but some phases must be complete. Let's try to
      // download data, and if there is anything, we can assume a phase is
      // completed.
      // ToDo: Instead of downloading would be nice if we could check phases.
      elseif ($this->lingotek->downloadDocument($document_id, $locale)) {
        // TODO: Set Status to STATUS_READY_INTERIM when that status is
        // available. See ticket https://www.drupal.org/node/2850548
      }
    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return $current_status;
  }

  /**
   * Clear the target statuses.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   */
  protected function clearTargetStatuses(ConfigEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    // Clear the target statuses. As we save the source status with the target,
    // we need to keep that one.
    $source_status = $this->getSourceStatus($entity);
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $metadata->setTargetStatus([])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatuses(ConfigEntityInterface &$entity) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $document_id = $this->getDocumentId($entity);
    $source_status = $this->getSourceStatus($entity);
    $translation_statuses = $this->lingotek->getDocumentTranslationStatuses($document_id);

    $statuses = [];
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $language) {
      $statuses[$language->getId()] = $this->getTargetStatus($entity, $language->getId());
    }

    // Let's reset all statuses, but keep the source one.
    $this->clearTargetStatuses($entity);

    foreach ($translation_statuses as $lingotek_locale => $progress) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($lingotek_locale);
      if ($drupal_language == NULL) {
        // languages existing in TMS, but not configured on Drupal
        continue;
      }
      $langcode = $drupal_language->id();
      $current_target_status = $statuses[$langcode];
      if (in_array($current_target_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_DISABLED, Lingotek::STATUS_EDITED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_NONE, Lingotek::STATUS_READY, Lingotek::STATUS_PENDING, NULL])) {
        if ($progress === Lingotek::PROGRESS_COMPLETE) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_READY);
        }
        else {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_PENDING);
        }
      }
      if ($source_status !== Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_EDITED && $langcode !== $entity->language()->getId()) {
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
      }
      if ($source_status === Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_CURRENT && $langcode !== $entity->language()->getId()) {
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
      }
    }
    if ($this->getSourceStatus($entity) === Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocument(ConfigEntityInterface $entity, $locale) {
    $profile = $this->lingotekConfiguration->getConfigEntityProfile($entity);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
      $data = [];
      try {
        if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) !== FALSE) {
          $data = $this->lingotek->downloadDocument($document_id, $locale);
        }
        else {
          return NULL;
        }
      }
      catch (LingotekApiException $exception) {
        // TODO: log issue
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_ERROR);
        return FALSE;
      }
      if ($data) {
        // Check the real status, because it may still need review or anything.
        $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);

        $this->saveTargetData($entity, $langcode, $data);
        // If the status was "Importing", and the target was added
        // successfully, we can ensure that the content is current now.
        $source_status = $this->getSourceStatus($entity);
        if ($source_status == Lingotek::STATUS_IMPORTING) {
          $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        }
        if ($source_status == Lingotek::STATUS_EDITED) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
        }
        elseif ($status === TRUE) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
        }
        else {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_INTERMEDIATE);
        }
        return TRUE;
      }

    }
    if ($this->getSourceStatus($entity) == Lingotek::STATUS_DISABLED) {
      $this->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDocument(ConfigEntityInterface &$entity) {
    return $this->lingotek->deleteDocument($this->getDocumentId($entity));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMetadata(ConfigEntityInterface &$entity) {
    if ($this->lingotekConfiguration->mustDeleteRemoteAfterDisassociation()) {
      $this->deleteDocument($entity);
    }
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    if (!$metadata->isNew()) {
      $metadata->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveTargetData(ConfigEntityInterface $entity, $langcode, $data) {
    // Allow other modules to alter the translation before it is saved.
    \Drupal::moduleHandler()->invokeAll('lingotek_config_entity_translation_presave', [&$entity, $langcode, &$data]);

    if ($entity->getEntityTypeId() == 'field_config') {
      $id = $entity->getTargetEntityTypeId();
      $mapper = clone ($this->mappers[$id . '_fields']);
      $mapper->setEntity($entity);
    }
    else {
      $mapper = clone ($this->mappers[$entity->getEntityTypeId()]);
      $mapper->setEntity($entity);
    }
    // For retro-compatibility, if there is only one config name, we expand our
    // data.
    $names = $mapper->getConfigNames();
    if (count($names) == 1) {
      $expanded[$names[0]] = $data;
    }
    else {
      $expanded = $data;
    }
    $this->saveConfigTargetData($mapper, $langcode, $expanded);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigDocumentId(ConfigNamesMapper $mapper) {
    $document_id = NULL;
    $metadata = NULL;
    foreach ($mapper->getConfigNames() as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      break;
    }
    if ($metadata) {
      $document_id = $metadata->getDocumentId();
    }
    return $document_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigDocumentId(ConfigNamesMapper $mapper, $document_id) {
    foreach ($mapper->getConfigNames() as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $metadata->setDocumentId($document_id);
      $metadata->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSourceLocale(ConfigNamesMapper $mapper) {
    $locale = NULL;
    $source_langcode = $mapper->getLangcode();
    if (!in_array($source_langcode, [LanguageInterface::LANGCODE_NOT_SPECIFIED, LanguageInterface::LANGCODE_NOT_APPLICABLE])) {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);
    }
    return $locale;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSourceStatus(ConfigNamesMapper $mapper) {
    $config_names = $mapper->getConfigNames();
    $source_language = $mapper->getLangcode();
    $status = Lingotek::STATUS_UNTRACKED;
    foreach ($config_names as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $source_status = $metadata->getSourceStatus();
      if (count($source_status) > 0 && isset($source_status[$source_language])) {
        $status = $source_status[$source_language];
      }
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigSourceStatus(ConfigNamesMapper $mapper, $status) {
    $config_names = $mapper->getConfigNames();
    $source_language = $mapper->getLangcode();
    $status_value = [$source_language => $status];
    foreach ($config_names as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $metadata->setSourceStatus($status_value);
      $metadata->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigTargetStatuses(ConfigNamesMapper $mapper) {
    $status = [];
    $config_names = $mapper->getConfigNames();
    if (!empty($config_names)) {
      $config_name = reset($config_names);
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $status = $metadata->getTargetStatus();
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigTargetStatus(ConfigNamesMapper $mapper, $langcode) {
    $status = Lingotek::STATUS_UNTRACKED;
    foreach ($mapper->getConfigNames() as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $translation_status = $metadata->getTargetStatus();
      if (count($translation_status) > 0 && isset($translation_status[$langcode])) {
        $status = $translation_status[$langcode];
      }
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigTargetStatus(ConfigNamesMapper $mapper, $langcode, $status, $save = TRUE) {
    foreach ($mapper->getConfigNames() as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $target_status = $metadata->getTargetStatus();
      $target_status[$langcode] = $status;
      $metadata->setTargetStatus($target_status);
      $metadata->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigTargetStatuses(ConfigNamesMapper $mapper, $status) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $mapper->getLangcode();

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getConfigTargetStatus($mapper, $langcode)) {
        if ($current_status === Lingotek::STATUS_PENDING && $status === Lingotek::STATUS_REQUEST) {
          // Don't allow to pass from pending to request. We have been already
          // requested this one.
          continue;
        }
        if (in_array($current_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_DISABLED, NULL]) && $status === Lingotek::STATUS_PENDING) {
          continue;
        }
        if ($current_status == $status) {
          continue;
        }
        if ($current_status != Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_CURRENT) {
          $this->setConfigTargetStatus($mapper, $langcode, $status);
        }
        elseif ($current_status == Lingotek::STATUS_EDITED && in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_PENDING])) {
          $this->setConfigTargetStatus($mapper, $langcode, $status);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSourceData(ConfigNamesMapper $mapper) {
    $properties = $this->getConfigTranslatableProperties($mapper);
    $values = [];
    foreach ($properties as $config_name => $config_properties) {
      $config = \Drupal::configFactory()->getEditable($config_name);
      foreach ($config_properties as $property) {
        $values[$config_name][$property] = $config->get($property);
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadConfig($mapper_id, $job_id = NULL) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id, FALSE);
    if ($profile !== NULL && $profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getConfigJobId($mapper);
    }
    if (!empty($this->getConfigDocumentId($mapper))) {
      return $this->updateConfig($mapper_id);
    }
    // Get the provide providing a default.
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);

    $source_data = $this->getConfigSourceData($mapper);

    // Allow other modules to alter the data before is uploaded.
    $config_names = $mapper->getConfigNames();
    $config_name = reset($config_names);
    \Drupal::moduleHandler()->invokeAll('lingotek_config_object_document_upload', [&$source_data, $config_name]);

    $source_data = json_encode($source_data);

    $extended_name = $mapper_id . ' (config): ' . $mapper->getTitle();
    $profile_preference = $profile->getAppendContentTypeToTitle();
    $global_preference = $this->lingotekConfiguration->getPreference('append_type_to_title');
    switch ($profile_preference) {
      case 'yes':
        $document_name = $extended_name;
        break;
      case 'no':
        $document_name = (string) $mapper->getTitle();
        break;
      case 'global_setting':
        $document_name = $global_preference ? $extended_name : (string) $mapper->getTitle();
        break;
      default:
        $document_name = $extended_name;
    }

    $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getConfigSourceLocale($mapper), NULL, $this->lingotekConfiguration->getConfigProfile($mapper_id), $job_id);
    if ($document_id) {
      $this->setConfigDocumentId($mapper, $document_id);
      $this->setConfigSourceStatus($mapper, Lingotek::STATUS_IMPORTING);
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_REQUEST);
      $this->setConfigJobId($mapper, $job_id);
      return $document_id;
    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigSourceStatus($mapper_id) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    $document_id = $this->getConfigDocumentId($mapper);
    if ($document_id && $this->lingotek->getDocumentStatus($document_id)) {
      $this->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
      return TRUE;
    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addConfigTarget($mapper_id, $locale) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    if ($locale == $this->languageLocaleMapper->getLocaleForLangcode($mapper->getLangcode())) {
      // We don't want to translate from one language to itself.
      return FALSE;
    }
    if ($document_id = $this->getConfigDocumentId($mapper)) {
      $source_status = $this->getConfigSourceStatus($mapper);
      $current_status = $this->getConfigTargetStatus($mapper, $locale);
      if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT  && $current_status !== Lingotek::STATUS_READY) {
        if ($this->lingotek->addTarget($document_id, $locale)) {
          $this->setConfigTargetStatus($mapper, $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId(), Lingotek::STATUS_PENDING);
          // If the status was "Importing", and the target was added
          // successfully, we can ensure that the content is current now.
          if ($source_status == Lingotek::STATUS_IMPORTING) {
            $this->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
          }
          return TRUE;
        }
      }
    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestConfigTranslations($mapper_id) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    $languages = [];
    if ($document_id = $this->getConfigDocumentId($mapper)) {
      $target_languages = $this->languageManager->getLanguages();
      $source_langcode = $mapper->getLangcode();

      foreach ($target_languages as $langcode => $language) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
        if ($langcode !== $source_langcode) {
          $source_status = $this->getConfigSourceStatus($mapper);
          $current_status = $this->getConfigTargetStatus($mapper, $langcode);
          if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT && $current_status !== Lingotek::STATUS_EDITED  && $current_status !== Lingotek::STATUS_READY) {
            if ($this->lingotek->addTarget($document_id, $locale, $this->lingotekConfiguration->getConfigProfile($mapper->getPluginId()))) {
              $languages[] = $langcode;
              $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_PENDING);
              // If the status was "Importing", and the target was added
              // successfully, we can ensure that the content is current now.
              if ($source_status == Lingotek::STATUS_IMPORTING) {
                $this->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
              }
            }
          }
        }
      }
    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigTargetStatus($mapper_id, $locale) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
    $current_status = $this->getConfigTargetStatus($mapper, $langcode);
    $source_status = $this->getConfigSourceStatus($mapper);
    $document_id = $this->getConfigDocumentId($mapper);
    if (($current_status == Lingotek::STATUS_PENDING ||
    $current_status == Lingotek::STATUS_EDITED) &&
    $source_status !== Lingotek::STATUS_EDITED) {
      if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) === TRUE) {
        $current_status = Lingotek::STATUS_READY;
        $this->setConfigTargetStatus($mapper, $langcode, $current_status);
      }
      // We may not be ready, but some phases must be complete. Let's try to
      // download data, and if there is anything, we can assume a phase is
      // completed.
      // ToDo: Instead of downloading would be nice if we could check phases.
      elseif ($this->lingotek->downloadDocument($document_id, $locale)) {
        // TODO: Set Status to STATUS_READY_INTERIM when that status is
        // available. See ticket https://www.drupal.org/node/2850548
      }
    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
    return $current_status;
  }

  /**
   * Clear the target statuses.
   * @param string $mapper_id
   */
  protected function clearConfigTargetStatuses($mapper_id) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    foreach ($mapper->getConfigNames() as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $metadata->setTargetStatus([])->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigTargetStatuses($mapper_id) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    $document_id = $this->getConfigDocumentId($mapper);
    $translation_statuses = $this->lingotek->getDocumentTranslationStatuses($document_id);
    $source_status = $this->getConfigSourceStatus($mapper);

    $statuses = [];
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $language) {
      $statuses[$language->getId()] = $this->getConfigTargetStatus($mapper, $language->getId());
    }

    // Let's reset all statuses, but keep the source one.
    $this->clearConfigTargetStatuses($mapper_id);

    foreach ($translation_statuses as $lingotek_locale => $progress) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($lingotek_locale);
      if ($drupal_language == NULL) {
        // languages existing in TMS, but not configured on Drupal
        continue;
      }
      $langcode = $drupal_language->id();
      $current_target_status = $statuses[$langcode];
      if (in_array($current_target_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_DISABLED, Lingotek::STATUS_EDITED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_NONE, Lingotek::STATUS_READY, Lingotek::STATUS_PENDING, NULL])) {
        if ($progress === Lingotek::PROGRESS_COMPLETE) {
          $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_READY);
        }
        else {
          $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_PENDING);
        }
      }
      if ($source_status !== Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_EDITED && $langcode !== $mapper->getLangcode()) {
        $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_EDITED);
      }
      if ($source_status === Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_CURRENT && $langcode !== $mapper->getLangcode()) {
        $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_CURRENT);
      }
    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadConfig($mapper_id, $locale) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);
    if ($profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    $mapper = $this->mappers[$mapper_id];
    if ($document_id = $this->getConfigDocumentId($mapper)) {
      $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
      $data = [];
      try {
        if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) === TRUE) {
          $data = $this->lingotek->downloadDocument($document_id, $locale);
        }
        else {
          return NULL;
        }
      }
      catch (LingotekApiException $exception) {
        // TODO: log issue
        $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_ERROR);
        return FALSE;
      }
      if ($data) {
        // Allow other modules to alter the data after it is downloaded.
        $config_names = $mapper->getConfigNames();
        $config_name = reset($config_names);
        \Drupal::moduleHandler()->invokeAll('lingotek_config_object_translation_presave', [&$data, $config_name]);

        // Check the real status, because it may still need review or anything.
        $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        $this->saveConfigTargetData($mapper, $langcode, $data);

        // If the status was "Importing", and the target was added
        // successfully, we can ensure that the content is current now.
        $source_status = $this->getConfigSourceStatus($mapper);
        if ($source_status == Lingotek::STATUS_IMPORTING) {
          $this->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
        }
        if ($source_status == Lingotek::STATUS_EDITED) {
          $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_EDITED);
        }
        elseif ($status === TRUE) {
          $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_CURRENT);
        }
        else {
          $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_INTERMEDIATE);
        }
        return TRUE;
      }

    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteConfigDocument($mapper_id) {
    $mapper = $this->mappers[$mapper_id];
    return $this->lingotek->deleteDocument($this->getConfigDocumentId($mapper));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteConfigMetadata($mapper_id) {
    $mapper = $this->mappers[$mapper_id];
    if ($this->lingotekConfiguration->mustDeleteRemoteAfterDisassociation()) {
      $this->deleteConfigDocument($mapper_id);
    }
    foreach ($mapper->getConfigNames() as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      if (!$metadata->isNew()) {
        $metadata->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfig($mapper_id, $job_id = NULL) {
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id, FALSE);
    if ($profile !== NULL && $profile->id() === Lingotek::PROFILE_DISABLED) {
      return FALSE;
    }
    // Get the provide providing a default.
    $profile = $this->lingotekConfiguration->getConfigProfile($mapper_id);

    $mapper = $this->mappers[$mapper_id];
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getConfigJobId($mapper);
    }
    $source_data = json_encode($this->getConfigSourceData($mapper));
    $document_id = $this->getConfigDocumentId($mapper);
    $extended_name = $mapper_id . ' (config): ' . $mapper->getTitle();
    $profile_preference = $profile->getAppendContentTypeToTitle();
    $global_preference = $this->lingotekConfiguration->getPreference('append_type_to_title');
    switch ($profile_preference) {
      case 'yes':
        $document_name = $extended_name;
        break;
      case 'no':
        $document_name = (string) $mapper->getTitle();
        break;
      case 'global_setting':
        $document_name = $global_preference ? $extended_name : (string) $mapper->getTitle();
        break;
      default:
        $document_name = $extended_name;
    }

    if ($this->lingotek->updateDocument($document_id, $source_data, NULL, $document_name, $profile, $job_id)) {
      $this->setConfigSourceStatus($mapper, Lingotek::STATUS_IMPORTING);
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_PENDING);
      $this->setConfigJobId($mapper, $job_id);
      return $document_id;
    }
    if ($this->getConfigSourceStatus($mapper) == Lingotek::STATUS_DISABLED) {
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
    }
    return FALSE;
  }

  public function saveConfigTargetData(ConfigNamesMapper $mapper, $langcode, $data) {
    $names = $mapper->getConfigNames();
    if (!empty($names)) {
      foreach ($names as $name) {
        $config_translation = $this->languageManager->getLanguageConfigOverride($langcode, $name);

        foreach ($data as $name => $properties) {
          foreach ($properties as $property => $value) {
            $config_translation->set($property, html_entity_decode($value));
          }
          $config_translation->save();
        }
      }
    }
  }

  public function loadByDocumentId($document_id) {
    // We cannot use a mapping table as in content, because config can be staged.
    $entity = NULL;
    // Check config first.
    $config_mappers = array_filter($this->mappers, function ($mapper) {
      return ($mapper instanceof ConfigNamesMapper
        && !$mapper instanceof ConfigEntityMapper
        && !$mapper instanceof ConfigFieldMapper);
    });
    foreach ($config_mappers as $mapper_id => $mapper) {
      if ($this->getConfigDocumentId($mapper) === $document_id) {
        return $mapper;
      }
    }
    // If we failed, check config entities.
    foreach ($this->mappers as $mapper_id => $mapper) {
      if (!isset($config_mappers[$mapper_id])) {
        $id = NULL;
        if (substr($mapper_id, -7) == '_fields') {
          // Hack for fields, the entity is field config.
          $mapper_id = 'field_config';
        }
        $id = \Drupal::service('entity.query')->get('lingotek_config_metadata')
          ->condition('document_id', $document_id)
          ->execute();
        if (!empty($id)) {
          list($mapper_id, $entity_id) = explode('.', reset($id), 2);
          return $this->entityManager->getStorage($mapper_id)->load($entity_id);
        }
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function markConfigTranslationsAsDirty(ConfigNamesMapper $mapper) {
    $target_languages = $this->languageManager->getLanguages();
    $source_langcode = $mapper->getLangcode();

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $source_langcode && $current_status = $this->getConfigTargetStatus($mapper, $langcode)) {
        if ($current_status == Lingotek::STATUS_CURRENT) {
          $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_PENDING);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigJobId(ConfigNamesMapper $mapper, $job_id, $update_tms = FALSE) {
    if ($update_tms && $document_id = $this->getConfigDocumentId($mapper)) {
      $this->lingotek->updateDocument($document_id, NULL, NULL, NULL, NULL, $job_id);
    }
    $config_names = $mapper->getConfigNames();
    foreach ($config_names as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $metadata->setJobId($job_id);
      $metadata->save();
    }
    return $mapper;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigJobId(ConfigNamesMapper $mapper) {
    $config_names = $mapper->getConfigNames();
    foreach ($config_names as $config_name) {
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      return $metadata->getJobId();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setJobId(ConfigEntityInterface $entity, $job_id, $update_tms = FALSE) {
    if ($update_tms && $document_id = $this->getDocumentId($entity)) {
      $this->lingotek->updateDocument($document_id, NULL, NULL, NULL, NULL, $job_id);
    }
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $metadata->setJobId($job_id);
    $metadata->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getJobId(ConfigEntityInterface $entity) {
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    return $metadata->getJobId();
  }

  protected function getPluginIdFromConfigName($name) {
    // ToDo: This is inefficient.
    foreach ($this->mappers as $plugin_id => $mapper) {
      $names = $mapper->getConfigNames();
      foreach ($names as $the_name) {
        if ($the_name === $name) {
          return $plugin_id;
        }
      }
    }
    return NULL;
  }

}
