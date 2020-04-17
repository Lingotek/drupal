<?php

namespace Drupal\lingotek\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Installer\InstallerKernel;

/**
 * Updates Lingotek config if a field is deleted.
 */
class LingotekFieldDeletedConfigSubscriber implements EventSubscriberInterface {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Constructs a LingotekFieldDeletedConfigSubscriber.
   *
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct(LingotekConfigurationServiceInterface $lingotek_configuration) {
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::DELETE => 'onConfigDelete',
    ];
  }

  /**
   * Updates the Lingotek configuration when a field is deleted.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigDelete(ConfigCrudEvent $event) {
    if (!InstallerKernel::installationAttempted()) {
      $config = $event->getConfig();
      if ($config instanceof FieldConfigInterface) {
        $field_name = $config->getName();
        $entity_type_id = $config->getTargetEntityTypeId();
        $bundle = $config->getTargetBundle();

        $this->lingotekConfiguration->setFieldLingotekEnabled($entity_type_id, $bundle, $field_name, FALSE);
      }
    }

  }

}
