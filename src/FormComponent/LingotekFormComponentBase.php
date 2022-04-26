<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Lingotek form-component plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
abstract class LingotekFormComponentBase extends PluginBase implements LingotekFormComponentInterface {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language_manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The lingotek.configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * The lingotek.content_translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The entity-type ID.
   *
   * @var bool|string|null
   */
  protected $entityTypeId = FALSE;

  /**
   * An array of entity-type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityTypes = [];

  /**
   * LingotekFormComponentBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type.manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language_manager service.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The lingotek.configuration service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The lingotek.content_translation service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    LingotekConfigurationServiceInterface $lingotek_configuration,
    LingotekContentTranslationServiceInterface $translation_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->translationService = $translation_service;
  }

  /**
   * Prevents serializing the container in children when the form is submitted.
   *
   * For some reason DependencySerializationTrait doesn't do the trick. Because
   * these classes should get serialized only when performing a batch operation,
   * and because they're not needed when the form object is unserialized, there
   * should be no need to implement __wakeup().
   *
   * @return array
   *   The properties to maintain.
   */
  public function __sleep() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.content_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeId(?string $entity_type_id) {
    if (!$this->entityTypeId) {
      $this->entityTypeId = $entity_type_id;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(string $entity_type_id) {
    if (!($this->entityTypes[$entity_type_id] ?? NULL)) {
      $this->entityTypes[$entity_type_id] = $this->entityTypeManager->getDefinition($entity_type_id);
    }

    return $this->entityTypes[$entity_type_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    if ($group = $this->pluginDefinition['group'] ?? NULL) {
      return $group;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupMachineName() {
    if ($group = $this->pluginDefinition['group'] ?? NULL) {
      if ($group instanceof TranslatableMarkup) {
        $group = $group->getUntranslatedString();
      }

      return preg_replace("/[^a-z0-9_]+/", '_', strtolower($group));
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->pluginDefinition['weight'] ?? 0;
  }

}
