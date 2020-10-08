<?php

namespace Drupal\lingotek\EventSubscriber;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\language\Config\LanguageConfigOverrideCrudEvent;
use Drupal\language\Config\LanguageConfigOverrideEvents;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Installer\InstallerKernel;

/**
 * Updates config Lingotek translation status when saved.
 */
class LingotekConfigSubscriber implements EventSubscriberInterface {

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The mapper manager.
   *
   * @var \Drupal\config_translation\ConfigMapperManagerInterface
   */
  protected $mapperManager;

  /**
   * A array of configuration mapper instances.
   *
   * @var \Drupal\config_translation\ConfigMapperInterface[]
   */
  protected $mappers;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a LingotekConfigSubscriber.
   *
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   *   The Lingotek config translation service.
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $mapper_manager
   *   The configuration mapper manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(LingotekConfigTranslationServiceInterface $translation_service, ConfigMapperManagerInterface $mapper_manager, LingotekConfigurationServiceInterface $lingotek_configuration, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->translationService = $translation_service;
    $this->mapperManager = $mapper_manager;
    $this->mappers = $mapper_manager->getMappers();
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
      LanguageConfigOverrideEvents::DELETE_OVERRIDE => 'onConfigLanguageOverrideDelete',
    ];
  }

  /**
   * Updates the configuration translation status when a configuration is saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    if (!InstallerKernel::installationAttempted()) {
      $config = $event->getConfig();
      if (!$config instanceof ConfigEntityInterface) {
        $name = $config->getName();
        $mapper = $this->getMapperFromConfigName($name);
        if ($mapper !== NULL) {
          if ($mapper instanceof ConfigEntityMapper) {
            $entity = $mapper->getEntity();
            if ($this->translationService->getDocumentId($entity)) {
              $this->translationService->setSourceStatus($entity, Lingotek::STATUS_EDITED);
              $this->translationService->markTranslationsAsDirty($entity);
            }
          }
          else {
            if ($this->translationService->getConfigDocumentId($mapper)) {
              $this->translationService->setConfigSourceStatus($mapper, Lingotek::STATUS_EDITED);
              $this->translationService->markConfigTranslationsAsDirty($mapper);
            }
          }
          /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
          $lingotek_config = $this->lingotekConfiguration;
          $profile = $lingotek_config->getConfigProfile($mapper->getPluginId());
          if ($profile !== NULL && $profile->id() === Lingotek::PROFILE_DISABLED) {
            $this->translationService->setConfigSourceStatus($mapper, Lingotek::STATUS_DISABLED);
            $this->translationService->setConfigTargetStatuses($mapper, Lingotek::STATUS_DISABLED);
          }
        }
      }

      // If there are changes on content translation settings, we need to react to
      // them in case the entity was enabled for Lingotek translation.
      if (0 === strpos($config->getName(), 'language.content_settings.') && $event->isChanged('third_party_settings.content_translation.enabled')) {
        $id = $config->get('id');
        list($entity_type_id, $bundle) = explode('.', $id);
        if (!$config->get('third_party_settings.content_translation.enabled')) {
          if ($this->lingotekConfiguration->isEnabled($entity_type_id, $bundle)) {
            $this->lingotekConfiguration->setEnabled($entity_type_id, $bundle, FALSE);
            $fields = $this->lingotekConfiguration->getFieldsLingotekEnabled($entity_type_id, $bundle);
            foreach ($fields as $field_name) {
              $this->lingotekConfiguration->setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, FALSE);
            }
          }
        }
      }
      if (0 === strpos($config->getName(), 'field.field.') && $event->isChanged('translatable')) {
        $id = $config->get('id');
        list($entity_type_id, $bundle, $field_name) = explode('.', $id);
        if (!$config->get('translatable')) {
          /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
          $lingotek_config = $this->lingotekConfiguration;
          $field_definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
          // We need to make an exception for hosted entities. The field
          // reference may not be translatable, but we want to translate the
          // hosted entity. See https://www.drupal.org/node/2735121.
          if (isset($field_definition[$field_name]) && $field_definition[$field_name]->getType() !== 'entity_reference_revisions' &&
              $lingotek_config->isFieldLingotekEnabled($entity_type_id, $bundle, $field_name)) {
            $lingotek_config->setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, FALSE);
          }
          if (isset($field_definition[$field_name]) && $field_definition[$field_name]->getType() !== 'cohesion_entity_reference_revisions' &&
            $lingotek_config->isFieldLingotekEnabled($entity_type_id, $bundle, $field_name)) {
            $lingotek_config->setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, FALSE);
          }

        }
      }
    }

    if ($event->getConfig()->getName() === 'lingotek.settings' && $event->isChanged('translate.entity')) {
      $this->entityFieldManager->clearCachedFieldDefinitions();
      $this->entityTypeManager->clearCachedDefinitions();
      \Drupal::service('router.builder')->setRebuildNeeded();

      if (\Drupal::service('entity.definition_update_manager')->needsUpdates()) {
        $entity_types = $this->lingotekConfiguration->getEnabledEntityTypes();
        foreach ($entity_types as $entity_type_id => $entity_type) {
          $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
          $installed_storage_definitions = \Drupal::service('entity.last_installed_schema.repository')->getLastInstalledFieldStorageDefinitions($entity_type_id);

          foreach (array_diff_key($storage_definitions, $installed_storage_definitions) as $storage_definition) {
            /** @var $storage_definition \Drupal\Core\Field\FieldStorageDefinitionInterface */
            if ($storage_definition->getProvider() == 'lingotek') {
              $this->entityTypeManager->onFieldStorageDefinitionCreate($storage_definition);
            }
          }
        }
      }
    }
  }

  /**
   * Updates the Lingotek configuration when a field is deleted.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigLanguageOverrideDelete(LanguageConfigOverrideCrudEvent $event) {
    if (!InstallerKernel::installationAttempted()) {
      /** @var \Drupal\lingotek\LanguageLocaleMapperInterface $languageMapper */
      $languageMapper = \Drupal::service('lingotek.language_locale_mapper');

      $configOverride = $event->getLanguageConfigOverride();
      $config_name = $configOverride->getName();
      $mapper = $this->getMapperFromConfigName($config_name);
      if ($mapper !== NULL) {
        if ($mapper instanceof ConfigEntityMapper) {
          $entity = $mapper->getEntity();
          $document_id = $this->translationService->getDocumentId($entity);
          $translation_statuses = \Drupal::service('lingotek')
            ->getDocumentTranslationStatuses($document_id);
          foreach ($translation_statuses as $lingotek_locale => $progress) {
            $drupal_language = $languageMapper->getConfigurableLanguageForLocale($lingotek_locale);
            if ($drupal_language !== NULL && $drupal_language->getId() === $configOverride->getLangcode()) {
              if ($progress === Lingotek::PROGRESS_COMPLETE) {
                $this->translationService->setTargetStatus($entity, $configOverride->getLangcode(), Lingotek::STATUS_READY);
              }
              else {
                $this->translationService->setTargetStatus($entity, $configOverride->getLangcode(), Lingotek::STATUS_PENDING);
              }
              return;
            }
          }
        }
        else {
          $document_id = $this->translationService->getConfigDocumentId($mapper);
          $translation_statuses = \Drupal::service('lingotek')
            ->getDocumentTranslationStatuses($document_id);
          foreach ($translation_statuses as $lingotek_locale => $progress) {
            $drupal_language = $languageMapper->getConfigurableLanguageForLocale($lingotek_locale);
            if ($drupal_language->getId() === $configOverride->getLangcode()) {
              if ($progress === Lingotek::PROGRESS_COMPLETE) {
                $this->translationService->setConfigTargetStatus($mapper, $configOverride->getLangcode(), Lingotek::STATUS_READY);
              }
              else {
                $this->translationService->setConfigTargetStatus($mapper, $configOverride->getLangcode(), Lingotek::STATUS_PENDING);
              }
              return;
            }
          }
        }
      }
    }
  }

  protected function getMapperFromConfigName($name) {
    // ToDo: This is inefficient.
    $result = NULL;
    foreach ($this->mappers as $mapper) {
      $names = $mapper->getConfigNames();
      foreach ($names as $the_name) {
        if ($the_name === $name) {
          $result = $mapper;
          break;
        }
      }
    }
    if (!$result) {
      // It may not be config, but config entity.
      /** @var \Drupal\Core\Config\ConfigManagerInterface $config_manager */
      $config_manager = \Drupal::service('config.manager');
      $entity_type_id = $config_manager->getEntityTypeIdByName($name);

      if ($entity_type_id === 'field_config') {
        list($field1, $field2, $field_entity_type_id, $field_bundle, $field_id) = explode('.', $name);
        $entity_type_id = $field_entity_type_id . '_fields';
      }
      if (isset($this->mappers[$entity_type_id])) {
        $entity = $config_manager->loadConfigEntityByName($name);
        // Maybe the entity is null because we are deleting also the original
        // entity, e.g. uninstalling a module.
        if ($entity !== NULL) {
          $mapper = clone $this->mappers[$entity_type_id];
          $mapper->setEntity($entity);
          $result = $mapper;
        }
      }
    }
    return $result;
  }

}
