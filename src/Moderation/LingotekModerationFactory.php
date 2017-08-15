<?php

namespace Drupal\lingotek\Moderation;

/**
 * A facade for getting the services that are part of moderation integrations.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekModerationFactory implements LingotekModerationFactoryInterface {

  /**
   * The collected moderation config services.
   *
   * @var LingotekModerationConfigurationServiceInterface[]
   */
  protected $config;

  /**
   * The collected moderation form settings services.
   *
   * @var LingotekModerationSettingsFormInterface[]
   */
  protected $forms;

  /**
   * The collected moderation handler services.
   *
   * @var LingotekModerationHandlerInterface[]
   */
  protected $handlers;

  /**
   * Constructs a \Drupal\lingotek\Moderation\LingotekModerationFactory object.
   */
  public function __construct() {
    $this->config = [];
    $this->forms = [];
    $this->handlers = [];
  }

  /**
   * {@inheritdoc}
   */
  public function addModerationConfiguration(LingotekModerationConfigurationServiceInterface $service, $id, $priority) {
    $this->config[$priority] = $service;
    krsort($this->config);
  }

  /**
   * {@inheritdoc}
   */
  public function addModerationForm(LingotekModerationSettingsFormInterface $service, $id, $priority) {
    $this->forms[$priority] = $service;
    krsort($this->forms);
  }

  /**
   * {@inheritdoc}
   */
  public function addModerationHandler(LingotekModerationHandlerInterface $service, $id, $priority) {
    $this->handlers[$priority] = $service;
    krsort($this->handlers);
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationConfigurationService() {
    foreach ($this->config as $service) {
      if ($service->applies()) {
        return $service;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationSettingsForm() {
    foreach ($this->forms as $service) {
      if ($service->applies()) {
        return $service;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationHandler() {
    foreach ($this->handlers as $service) {
      if ($service->applies()) {
        return $service;
      }
    }
  }

}
