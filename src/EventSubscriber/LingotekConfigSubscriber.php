<?php
/**
 * @file
 * Contains \Drupal\lingotek\EventSubscriber\LingotekConfigSubscriber.
 */

namespace Drupal\lingotek\EventSubscriber;

use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates config Lingotek translation status when saved.
 */
class LingotekConfigSubscriber implements EventSubscriberInterface {

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
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
   * Constructs a LingotekConfigSubscriber.
   *
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   *   The Lingotek config translation service.
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $mapper_manager
   *   The configuration mapper manager.
   */
  public function __construct(LingotekConfigTranslationServiceInterface $translation_service, ConfigMapperManagerInterface $mapper_manager) {
    $this->translationService = $translation_service;
    $this->mapperManager = $mapper_manager;
    $this->mappers = $mapper_manager->getMappers();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

  /**
   * Updates the configuration translation status when a configuration is saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    if (!drupal_installation_attempted()) {
      $config = $event->getConfig();
      if (!$config instanceof ConfigEntityInterface) {
        $name = $config->getName();
        $mapper = $this->getMapperFromConfigName($name);
        if ($mapper !== NULL) {
          if ($this->translationService->getConfigDocumentId($mapper)) {
            $this->translationService->setConfigSourceStatus($mapper, Lingotek::STATUS_EDITED);
            $this->translationService->markConfigTranslationsAsDirty($mapper);
          }
        }
      }

      // If there are changes on content translation settings, we need to react to
      // them in case the entity was enabled for Lingotek translation.
      if (0 === strpos($config->getName(), 'language.content_settings.') && $event->isChanged('third_party_settings.content_translation.enabled')) {
        $id = $config->get('id');
        list($entity_type_id, $bundle) = explode('.', $id);
        if (!$config->get('third_party_settings.content_translation.enabled')) {
          /** @var LingotekConfigurationServiceInterface $lingotek_config */
          $lingotek_config = \Drupal::service('lingotek.configuration');
          if ($lingotek_config->isEnabled($entity_type_id, $bundle)) {
            $lingotek_config->setEnabled($entity_type_id, $bundle, FALSE);
            $fields = $lingotek_config->getFieldsLingotekEnabled($entity_type_id, $bundle);
            foreach ($fields as $field_name) {
              $lingotek_config->setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, FALSE);
            }
          }
        }
      }
      if (0 === strpos($config->getName(), 'field.field.') && $event->isChanged('translatable')) {
        $id = $config->get('id');
        list($entity_type_id, $bundle, $field_name) = explode('.', $id);
        if (!$config->get('translatable')) {
          /** @var LingotekConfigurationServiceInterface $lingotek_config */
          $lingotek_config = \Drupal::service('lingotek.configuration');
          $field_definition = \Drupal::entityManager()->getFieldDefinitions($entity_type_id, $bundle);
          // We need to make an exception for hosted entities. The field
          // reference may not be translatable, but we want to translate the
          // hosted entity. See https://www.drupal.org/node/2735121.
          if ($field_definition[$field_name]->getType() !== 'entity_reference_revisions' &&
              $lingotek_config->isFieldLingotekEnabled($entity_type_id, $bundle, $field_name)) {
            $lingotek_config->setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, FALSE);
          }
        }
      }
    }

    if ($event->getConfig()->getName() === 'lingotek.settings' && $event->isChanged('translate.entity')) {
      drupal_static_reset();
      \Drupal::entityManager()->clearCachedDefinitions();
      \Drupal::service('router.builder')->rebuild();

      if (\Drupal::service('entity.definition_update_manager')->needsUpdates()) {
        $entity_types = \Drupal::service('lingotek.configuration')->getEnabledEntityTypes();
        foreach ($entity_types as $entity_type_id => $entity_type) {
          $storage_definitions = \Drupal::entityManager()->getFieldStorageDefinitions($entity_type_id);
          $installed_storage_definitions = \Drupal::entityManager()->getLastInstalledFieldStorageDefinitions($entity_type_id);

          foreach (array_diff_key($storage_definitions, $installed_storage_definitions) as $storage_definition) {
            /** @var $storage_definition \Drupal\Core\Field\FieldStorageDefinitionInterface */
            if ($storage_definition->getProvider() == 'lingotek') {
              \Drupal::entityManager()->onFieldStorageDefinitionCreate($storage_definition);
            }
          }
        }
      }
    }
  }

  protected function getMapperFromConfigName($name) {
    // ToDo: This is inefficient.
    foreach ($this->mappers as $mapper) {
      $names = $mapper->getConfigNames();
      foreach ($names as $the_name) {
        if ($the_name === $name) {
          return $mapper;
        }
      }
    }
    return NULL;
  }

}
