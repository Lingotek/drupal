<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Utility methods for all the 'noop' moderation services.
 *
 * @package Drupal\lingotek\Moderation
 */
trait LingotekNoModerationCheckTrait {

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
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

}
