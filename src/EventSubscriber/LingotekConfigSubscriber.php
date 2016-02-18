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
