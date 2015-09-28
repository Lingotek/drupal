<?php

/**
 * @file
 * Contains \Drupal\lingotek\LingotekConfigTranslationService.
 */

namespace Drupal\lingotek;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\config_translation\ConfigNamesMapper;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\lingotek\Entity\LingotekProfile;

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
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   An entity manager object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $mapper_manager
   *   The configuration mapper manager.
   */
  public function __construct(LingotekInterface $lingotek, LingotekConfigurationServiceInterface $lingotek_configuration, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, ConfigMapperManagerInterface $mapper_manager) {
    $this->lingotek = $lingotek;
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
  public function getDefaultProfile($plugin_id) {
    $config = \Drupal::config('lingotek.settings');
    $key = 'translate.config.' . $plugin_id . '.profile';
    $profile_id = $config->get($key);
    return $profile_id ? LingotekProfile::load($profile_id) : LingotekProfile::load(Lingotek::PROFILE_AUTOMATIC);
  }

  /**
   * {@inheritDoc}
   */
  public function setDefaultProfile($plugin_id, $profile_id) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $key = 'translate.config.' . $plugin_id . '.profile';
    $config->set($key, $profile_id)->save();
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
    return $entity->getThirdPartySetting('lingotek', 'lingotek_document_id');
  }

  public function setDocumentId(ConfigEntityInterface &$entity, $document_id) {
    $entity->setThirdPartySetting('lingotek', 'lingotek_document_id', $document_id);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceStatus(ConfigEntityInterface &$entity) {
    $source_status = $entity->getThirdPartySetting('lingotek', 'lingotek_translation_source');
    return $source_status[$entity->language()->getId()];
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceStatus(ConfigEntityInterface &$entity, $status) {
    $source_language = $entity->getThirdPartySetting('lingotek', 'lingotek_translation_source');
    if ($source_language == LanguageInterface::LANGCODE_NOT_SPECIFIED || $source_language == NULL) {
      $source_language = $entity->language()->getId();
    }
    $status_value = [$source_language => $status];
    $entity->setThirdPartySetting('lingotek', 'lingotek_translation_source', $status_value)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatus(ConfigEntityInterface &$entity, $langcode) {
    $status = Lingotek::STATUS_UNTRACKED;
    $translation_status = $entity->getThirdPartySetting('lingotek', 'lingotek_translation_status');
    if (count($translation_status) > 0) {
      $status = $translation_status[$langcode];
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatus(ConfigEntityInterface &$entity, $langcode, $status, $save = TRUE) {
    $translation_status = $entity->getThirdPartySetting('lingotek', 'lingotek_translation_status');
    $translation_status[$langcode] = $status;
    $entity->setThirdPartySetting('lingotek', 'lingotek_translation_status', $translation_status);
    if ($save) {
      $entity->save();
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
      if ($langcode != $entity_langcode && $this->getTargetStatus($entity, $langcode)) {
        $this->setTargetStatus($entity, $langcode, $status);
      }
    }
  }

  public function getSourceData(ConfigEntityInterface $entity) {
    /** @var ConfigEntityMapper $mapper */
    $mapper = $this->configMapperManager->createInstance($entity->getEntityTypeId());
    $mapper->setEntity($entity);
    $properties = $this->getConfigTranslatableProperties($mapper);
    $values = [];
    foreach ($mapper->getConfigNames() as $config_name) {
      foreach ($properties[$config_name] as $property) {
        $keys = explode('.', $property);
        $value = $entity;
        foreach ($keys as $key) {
          if (is_array($value)) {
            $value = $value[$key];
          }
          else {
            $value = $value->get($key);
          }
        }
        $values[$property] = $value;
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLocale(ConfigEntityInterface &$entity) {
    $source_language = $entity->language()->getId();
    return LingotekLocale::convertDrupal2Lingotek($source_language);
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
    $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getSourceLocale($entity));
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
    if ($document_id && $this->lingotek->documentImported($document_id)) {
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
    if ($this->lingotek->updateDocument($document_id, $source_data)) {
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
    if ($locale == LingotekLocale::convertDrupal2Lingotek($entity->language()->getId())) {
      // We don't want to translate from one language to itself.
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($entity)) {
      $current_status = $this->getTargetStatus($entity, LingotekLocale::convertLingotek2Drupal($locale));
      if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT && $current_status !== Lingotek::STATUS_EDITED) {
        if ($this->lingotek->addTarget($document_id, $locale)) {
          $this->setTargetStatus($entity, LingotekLocale::convertLingotek2Drupal($locale), Lingotek::STATUS_PENDING);
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatus(ConfigEntityInterface &$entity, $locale) {
    $langcode = LingotekLocale::convertLingotek2Drupal($locale);
    $current_status = $this->getTargetStatus($entity, $langcode);
    if (($current_status == Lingotek::STATUS_PENDING) && $this->lingotek->getDocumentStatus($this->getDocumentId($entity))) {
      $current_status = Lingotek::STATUS_READY;
      $this->setTargetStatus($entity, $langcode, $current_status);
    }
    return $current_status;
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
        $langcode = LingotekLocale::convertLingotek2Drupal($locale);
        $this->saveTargetData($entity, $langcode, $data);
        $this->setTargetStatus($entity, $langcode, Lingotek::STATUS_CURRENT);
        return TRUE;
      }

    }
    return FALSE;
  }

  public function saveTargetData(ConfigEntityInterface $entity, $langcode, array $data) {
    /** @var ConfigEntityInterface $translated */
    $translated = $this->entityManager->getStorage($entity->getEntityTypeId())
      ->load($entity->id());

    $type = $translated->getEntityType();
    $prefix = $type->get('config_prefix')
      ? $type->getProvider() . '.' . $type->get('config_prefix') . '.'
      : $type->getProvider() . '.' . $type->get('id') . '.';

    /** @var Config $config */
    $name = $prefix . $entity->id();
    // $config = \Drupal::configFactory()->getEditable($name);
    $config_translation = $this->languageManager->getLanguageConfigOverride($langcode, $name);

    foreach ($data as $property => $value) {
      $config_translation->set($property, $value);
    }
    $config_translation->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigDocumentId(ConfigNamesMapper $mapper) {
    $document_id = NULL;
    foreach ($mapper->getConfigNames() as $config_name) {
      $config = \Drupal::configFactory()->getEditable($config_name);
      break;
    }
    $metadata = $config->get('lingotek_metadata') ?: [];
    $document_id = isset($metadata['document_id']) ? $metadata['document_id'] : NULL;
    return $document_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigDocumentId(ConfigNamesMapper $mapper, $document_id) {
    foreach ($mapper->getConfigNames() as $config_name) {
      $config = \Drupal::configFactory()->getEditable($config_name);
      $metadata = $config->get('lingotek_metadata') ?: [];
      $metadata['document_id'] = $document_id;
      $config->set('lingotek_metadata', $metadata)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSourceLocale(ConfigNamesMapper $mapper) {
    $source_language = $mapper->getLangcode();
    return LingotekLocale::convertDrupal2Lingotek($source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigSourceStatus(ConfigNamesMapper $mapper, $status) {
    $config_names = $mapper->getConfigNames();
    $source_language = $this->getConfigSourceLocale($mapper);
    $status_value = [$source_language => $status];
    foreach ($config_names as $config_name) {
      $config = \Drupal::configFactory()->getEditable($config_name);
      $metadata = $config->get('lingotek_metadata') ?: [];
      $metadata['source_status'] = $status_value;
      $config->set('lingotek_metadata', $metadata)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigTargetStatus(ConfigNamesMapper $mapper, $langcode) {
    $status = Lingotek::STATUS_UNTRACKED;
    foreach ($mapper->getConfigNames() as $config_name) {
      $config = \Drupal::configFactory()->getEditable($config_name);
      $metadata = $config->get('lingotek_metadata') ?: [];
      $translation_status = $metadata['target_status'];
      if (count($translation_status) > 0) {
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
      $config = \Drupal::configFactory()->getEditable($config_name);
      $metadata = $config->get('lingotek_metadata') ?: [];
      $metadata['target_status'][$langcode] = $status;
      $config->set('lingotek_metadata', $metadata)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigTargetStatuses(ConfigNamesMapper $mapper, $status) {
    $target_languages = $this->languageManager->getLanguages();
    $entity_langcode = $mapper->getLangcode();

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $entity_langcode && $this->getConfigTargetStatus($mapper, $langcode)) {
        $this->setConfigTargetStatus($mapper, $langcode, $status);
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
      return $this->updateConfig($mapper);
    }
    $source_data = json_encode($this->getConfigSourceData($mapper));
    $document_name = $mapper_id . ' (config): ' . $mapper->getTitle();
    $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getConfigSourceLocale($mapper));
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
    if ($document_id && $this->lingotek->documentImported($document_id)) {
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
    if ($locale == LingotekLocale::convertDrupal2Lingotek($mapper->getLangcode())) {
      // We don't want to translate from one language to itself.
      return FALSE;
    }
    if ($document_id = $this->getConfigDocumentId($mapper)) {
      $current_status = $this->getConfigTargetStatus($mapper, LingotekLocale::convertLingotek2Drupal($locale));
      if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT) {
        if ($this->lingotek->addTarget($document_id, $locale)) {
          $this->setConfigTargetStatus($mapper, LingotekLocale::convertLingotek2Drupal($locale), Lingotek::STATUS_PENDING);
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigTargetStatus($mapper_id, $locale) {
    $mapper = $this->mappers[$mapper_id];
    $langcode = LingotekLocale::convertLingotek2Drupal($locale);
    $current_status = $this->getConfigTargetStatus($mapper, $langcode);
    if (($current_status == Lingotek::STATUS_PENDING) && $this->lingotek->getDocumentStatus($this->getConfigDocumentId($mapper))) {
      $current_status = Lingotek::STATUS_READY;
      $this->setConfigTargetStatus($mapper, $langcode, $current_status);
    }
    return $current_status;
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
        $langcode = LingotekLocale::convertLingotek2Drupal($locale);
        $this->saveConfigTargetData($mapper, $langcode, $data);
        $this->setConfigTargetStatus($mapper, $langcode, Lingotek::STATUS_CURRENT);
        return TRUE;
      }

    }
    return FALSE;
  }


  /**
   * {@inheritdoc}
   */
  public function updateConfig(ConfigNamesMapper $mapper) {
    $source_data = json_encode($this->getConfigSourceData($mapper));
    $document_id = $this->getConfigDocumentId($mapper);
    if ($this->lingotek->updateDocument($document_id, $source_data)) {
      $this->setConfigSourceStatus($mapper, Lingotek::STATUS_IMPORTING);
      $this->setConfigTargetStatuses($mapper, Lingotek::STATUS_REQUEST);
      return $document_id;
    }
    return FALSE;
  }


  public function saveConfigTargetData(ConfigNamesMapper $mapper, $langcode, array $data) {
    $names = $mapper->getConfigNames();
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