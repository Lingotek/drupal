<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigTranslationService.
 */

namespace Drupal\lingotek;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\config_translation\ConfigNamesMapper;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\lingotek\Entity\LingotekConfigMetadata;

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
   * @var \Drupal\Core\Language\LanguageManagerInterface $language_manager
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
   *  The language-locale mapper.
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
    /** @var TypedConfigManagerInterface $typed_config */
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
        if ($current_status != Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_CURRENT) {
          $this->setTargetStatus($entity, $langcode, $status);
        }
        elseif ($current_status == Lingotek::STATUS_EDITED && $status == Lingotek::STATUS_CURRENT) {
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

    // These statuses indicate that content has been uploaded to the API, so
    // we need to flag them as out of date.
    $to_change = [
      Lingotek::STATUS_CURRENT,
      Lingotek::STATUS_PENDING,
      Lingotek::STATUS_INTERMEDIATE,
      Lingotek::STATUS_READY,
    ];

    foreach($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $current_status = $this->getTargetStatus($entity, $langcode)) {
        if (in_array($current_status, $to_change)) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_EDITED);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceData(ConfigEntityInterface $entity) {
    /** @var ConfigEntityMapper $mapper */
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
    $source_language = $entity->language()->getId();
    return $this->languageLocaleMapper->getLocaleForLangcode($source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument(ConfigEntityInterface $entity) {
    if (!empty($this->getDocumentId($entity))) {
      return $this->updateDocument($entity);
    }
    $source_data = json_encode($this->getSourceData($entity));
    $document_name = $entity->id() . ' (config): ' . $entity->label();
    $url = $entity->hasLinkTemplate('edit-form') ? $entity->toUrl()->setAbsolute()->toString() : NULL;
    $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getSourceLocale($entity), $url, $this->lingotekConfiguration->getConfigEntityProfile($entity));
    if ($document_id) {
      $this->setDocumentId($entity, $document_id);
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_REQUEST);
      return $document_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkSourceStatus(ConfigEntityInterface &$entity) {
    $document_id = $this->getDocumentId($entity);
    if ($document_id && $this->lingotek->getDocumentStatus($document_id)) {
      $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument(ConfigEntityInterface &$entity) {
    $source_data = json_encode($this->getSourceData($entity));
    $document_id = $this->getDocumentId($entity);
    $document_name = $entity->id() . ' (config): ' . $entity->label();

    $url = $entity->hasLinkTemplate('edit-form') ? $entity->toUrl()->setAbsolute()->toString() : NULL;
    if ($this->lingotek->updateDocument($document_id, $source_data, $url, $document_name)) {
      $this->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($entity, Lingotek::STATUS_REQUEST);
      return $document_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addTarget(ConfigEntityInterface &$entity, $locale) {
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
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslations(ConfigEntityInterface &$entity) {
    $languages = [];
    if ($document_id = $this->getDocumentId($entity)) {
      $target_languages = $this->languageManager->getLanguages();
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
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatus(ConfigEntityInterface &$entity, $locale) {
    $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
    $current_status = $this->getTargetStatus($entity, $langcode);

    if (($current_status == Lingotek::STATUS_PENDING || $current_status == Lingotek::STATUS_EDITED) && $this->lingotek->getDocumentTranslationStatus($this->getDocumentId($entity), $locale)) {
      $current_status = Lingotek::STATUS_READY;
      $this->setTargetStatus($entity, $langcode, $current_status);
    }
    return $current_status;
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatuses(ConfigEntityInterface &$entity) {
    $metadata = LingotekConfigMetadata::loadByConfigName($entity->getEntityTypeId() . '.' . $entity->id());
    $translation_status = $metadata->getTargetStatus();
    foreach ($translation_status as $language => $current_status) {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($language);
      $document_id = $this->getDocumentId($entity);
      if ($current_status == Lingotek::STATUS_PENDING || $current_status == Lingotek::STATUS_EDITED) {
        if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) ||
            // We may not be ready, but some phases must be complete. Let's try to
            // download data, and if there is anything, we can assume a phase is
            // completed.
          // ToDo: Instead of downloading would be nice if we could check phases.
            $this->lingotek->downloadDocument($entity, $locale)) {
          $current_status = Lingotek::STATUS_READY;
          $this->setTargetStatus($entity, $language, $current_status);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocument(ConfigEntityInterface $entity, $locale) {
    if ($document_id = $this->getDocumentId($entity)) {
      try {
        $data = $this->lingotek->downloadDocument($document_id, $locale);
      }
      catch (LingotekApiException $exception) {
        // TODO: log issue
        return FALSE;
      }
      if ($data) {
        // Check the real status, because it may still need review or anything.
        $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);

        $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
        $this->saveTargetData($entity, $langcode, $data);
        // If the status was "Importing", and the target was added
        // successfully, we can ensure that the content is current now.
        $source_status = $this->getSourceStatus($entity);
        if ($source_status == Lingotek::STATUS_IMPORTING || $source_status == Lingotek::STATUS_EDITED) {
          $this->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        }
        if ($status) {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
        }
        else {
          $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_INTERMEDIATE);
        }
        return TRUE;
      }

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
    $source_langcode = $mapper->getLangcode();
    return $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);
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
        if ($current_status != Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_CURRENT) {
          $this->setConfigTargetStatus($mapper, $langcode, $status);
        }
        elseif ($current_status == Lingotek::STATUS_EDITED && $status == Lingotek::STATUS_CURRENT) {
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
  public function uploadConfig($mapper_id) {
    $mapper = $this->mappers[$mapper_id];
    if (!empty($this->getConfigDocumentId($mapper))) {
      return $this->updateConfig($mapper_id);
    }
    $source_data = json_encode($this->getConfigSourceData($mapper));
    $document_name = $mapper_id . ' (config): ' . $mapper->getTitle();
    $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getConfigSourceLocale($mapper), NULL, $this->lingotekConfiguration->getConfigProfile($mapper_id));
    if ($document_id) {
      $this->setConfigDocumentId($mapper, $document_id);
      $this->setConfigSourceStatus($mapper, Lingotek::STATUS_IMPORTING);
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_REQUEST);
      return $document_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigSourceStatus($mapper_id) {
    $mapper = $this->mappers[$mapper_id];
    $document_id = $this->getConfigDocumentId($mapper);
    if ($document_id && $this->lingotek->getDocumentStatus($document_id)) {
      $this->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addConfigTarget($mapper_id, $locale) {
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
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestConfigTranslations($mapper_id) {
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
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigTargetStatus($mapper_id, $locale) {
    $mapper = $this->mappers[$mapper_id];
    $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
    $current_status = $this->getConfigTargetStatus($mapper, $langcode);
    if (($current_status == Lingotek::STATUS_PENDING || $current_status == Lingotek::STATUS_EDITED) && $this->lingotek->getDocumentTranslationStatus($this->getConfigDocumentId($mapper), $locale)) {
      $current_status = Lingotek::STATUS_READY;
      $this->setConfigTargetStatus($mapper, $langcode, $current_status);
    }
    return $current_status;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigTargetStatuses($mapper_id) {
    $mapper = $this->mappers[$mapper_id];
    $config_names = $mapper->getConfigNames();
    if (!empty($config_names)) {
      $config_name = reset($config_names);
      $metadata = LingotekConfigMetadata::loadByConfigName($config_name);
      $translation_status = $metadata->getTargetStatus();
      foreach ($translation_status as $language => $current_status) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($language);
        $document_id = $this->getConfigDocumentId($mapper);
        if ($current_status == Lingotek::STATUS_PENDING || $current_status == Lingotek::STATUS_EDITED) {
          if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) ||
              // We may not be ready, but some phases must be complete. Let's try to
              // download data, and if there is anything, we can assume a phase is
              // completed.
              // ToDo: Instead of downloading would be nice if we could check phases.
              $this->lingotek->downloadDocument($document_id, $locale)) {
            $current_status = Lingotek::STATUS_READY;
            $this->setConfigTargetStatus($mapper, $language, $current_status);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadConfig($mapper_id, $locale) {
    $mapper = $this->mappers[$mapper_id];
    if ($document_id = $this->getConfigDocumentId($mapper)) {
      try {
        $data = $this->lingotek->downloadDocument($document_id, $locale);
      }
      catch (LingotekApiException $exception) {
        // TODO: log issue
        return FALSE;
      }
      if ($data) {
        $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
        $this->saveConfigTargetData($mapper, $langcode, $data);
        // If the status was "Importing", and the target was added
        // successfully, we can ensure that the content is current now.
        $source_status = $this->getConfigSourceStatus($mapper);
        if ($source_status == Lingotek::STATUS_IMPORTING || $source_status == Lingotek::STATUS_EDITED) {
          $this->setConfigSourceStatus($mapper, Lingotek::STATUS_CURRENT);
        }
        $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_CURRENT);
        return TRUE;
      }

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
  public function updateConfig($mapper_id) {
    $mapper = $this->mappers[$mapper_id];
    $source_data = json_encode($this->getConfigSourceData($mapper));
    $document_id = $this->getConfigDocumentId($mapper);
    $document_name = $mapper_id . ' (config): ' . $mapper->getTitle();

    if ($this->lingotek->updateDocument($document_id, $source_data, NULL, $document_name)) {
      $this->setConfigSourceStatus($mapper, Lingotek::STATUS_IMPORTING);
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_REQUEST);
      return $document_id;
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
            $config_translation->set($property, $value);
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
    $config_mappers = array_filter($this->mappers, function($mapper) {
      return ($mapper instanceof ConfigNamesMapper
        && ! $mapper instanceof ConfigEntityMapper
        && ! $mapper instanceof ConfigFieldMapper)
        ;
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

    foreach($target_languages as $langcode => $language) {
      if ($langcode != $source_langcode && $current_status = $this->getConfigTargetStatus($mapper, $langcode)) {
        if ($current_status == Lingotek::STATUS_CURRENT) {
          $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_EDITED);
        }
      }
    }
  }

}
