<?php

namespace Drupal\lingotek\Moderation;

/**
 * A facade for getting the services that are part of moderation integrations.
 *
 * @package Drupal\lingotek\Moderation
 */
interface LingotekModerationFactoryInterface {

  /**
   * Called when the tag collector finds a moderation configuration service.
   *
   * @param \Drupal\lingotek\Moderation\LingotekModerationConfigurationServiceInterface $service
   *   The moderation configuration service.
   * @param string $id
   *   The id of the tagged service.
   * @param int $priority
   *   The priority for the tagged service.
   */
  public function addModerationConfiguration(LingotekModerationConfigurationServiceInterface $service, $id, $priority);

  /**
   * Called when the tag collector finds a moderation settings form service.
   *
   * @param \Drupal\lingotek\Moderation\LingotekModerationSettingsFormInterface $service
   *   The moderation configuration service.
   * @param string $id
   *   The id of the tagged service.
   * @param int $priority
   *   The priority for the tagged service.
   */
  public function addModerationForm(LingotekModerationSettingsFormInterface $service, $id, $priority);

  /**
   * Called when the tag collector finds a moderation handler service.
   *
   * @param \Drupal\lingotek\Moderation\LingotekModerationHandlerInterface $service
   *   The moderation configuration service.
   * @param string $id
   *   The id of the tagged service.
   * @param int $priority
   *   The priority for the tagged service.
   */
  public function addModerationHandler(LingotekModerationHandlerInterface $service, $id, $priority);

  /**
   * Gets the first moderation configuration service applying to a given entity.
   *
   * @return \Drupal\lingotek\Moderation\LingotekModerationConfigurationServiceInterface
   *   The moderation configuration service.
   */
  public function getModerationConfigurationService();

  /**
   * Gets the first moderation settings form service applying to a given entity.
   *
   * @return \Drupal\lingotek\Moderation\LingotekModerationSettingsFormInterface
   *   The moderation settings form service.
   */
  public function getModerationSettingsForm();

  /**
   * Gets the first moderation handler service that applies to the given entity.
   *
   * @return \Drupal\lingotek\Moderation\LingotekModerationHandlerInterface
   *   The moderation handler service.
   */
  public function getModerationHandler();

}
