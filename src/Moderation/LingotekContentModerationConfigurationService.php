<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service for managing content moderation settings in the Lingotek integration.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekContentModerationConfigurationService implements LingotekModerationConfigurationServiceInterface {

  use LingotekContentModerationCheckTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new LingotekContentModerationConfigurationService object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    $this->setModuleHandler($module_handler);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getUploadStatus($entity_type_id, $bundle) {
    $config = $this->configFactory->get('lingotek.settings');
    $upload_status = $config->get('translate.entity.' . $entity_type_id . '.' . $bundle . '.content_moderation.upload_status');
    return $upload_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadTransition($entity_type_id, $bundle) {
    $config = $this->configFactory->get('lingotek.settings');
    $download_transition = $config->get('translate.entity.' . $entity_type_id . '.' . $bundle . '.content_moderation.download_transition');
    return $download_transition;
  }

  /**
   * {@inheritdoc}
   */
  public function setUploadStatus($entity_type_id, $bundle, $status) {
    $config = $this->configFactory->getEditable('lingotek.settings');
    $config->set('translate.entity.' . $entity_type_id . '.' . $bundle . '.content_moderation.upload_status', $status);
    $config->save();
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setDownloadTransition($entity_type_id, $bundle, $transition) {
    $config = $this->configFactory->getEditable('lingotek.settings');
    $config->set('translate.entity.' . $entity_type_id . '.' . $bundle . '.content_moderation.download_transition', $transition);
    $config->save();
    return $transition;
  }

}
