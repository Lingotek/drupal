<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Common interface for all the Lingotek moderation services.
 *
 * @package Drupal\lingotek\Moderation
 */
interface LingotekModerationServiceInterface {

  /**
   * Checks if this service applies.
   *
   * @return bool
   *   TRUE if this service applies. FALSE if not.
   */
  public function applies();

  /**
   * Sets the module handler for this service.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler);

}
