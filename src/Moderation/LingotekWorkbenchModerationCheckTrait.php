<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Utility methods for all the 'workbench_moderation' moderation services.
 *
 * @package Drupal\lingotek\Moderation
 */
trait LingotekWorkbenchModerationCheckTrait {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function applies() {
    return $this->moduleHandler->moduleExists('workbench_moderation');
  }

  /**
   * {@inheritdoc}
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

}
