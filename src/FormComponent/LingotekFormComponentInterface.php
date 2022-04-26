<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Generic interface for Lingotek form plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
interface LingotekFormComponentInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Establishes whether the plugin is applicable to the form.
   *
   * @param array $arguments
   *   The arguments.
   *
   * @return bool
   *   If TRUE, the plugin is applicable.
   */
  public function isApplicable(array $arguments = []);

  /**
   * Sets the entity-type ID, if any.
   *
   * @param string|null $entity_type_id
   *   The entity-type ID.
   *
   * @return $this
   */
  public function setEntityTypeId(?string $entity_type_id);

  /**
   * Gets the definition of an entity type.
   *
   * @param string $entity_type_id
   *   The entity-type ID.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type.
   */
  public function getEntityType(string $entity_type_id);

  /**
   * Gets the plugin's title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function getTitle();

  /**
   * Gets the plugin's group, if any.
   *
   * @return string|null
   *   The group.
   */
  public function getGroup();

  /**
   * Creates a machine name for the plugin's group.
   *
   * @return string|null
   *   The group's machine name.
   */
  public function getGroupMachineName();

  /**
   * Gets the plugin's weight.
   *
   * @return int
   *   The weight.
   */
  public function getWeight();

}
