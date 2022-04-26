<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Lingotek form-filter plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
abstract class LingotekFormComponentFilterBase extends LingotekFormComponentBase implements LingotekFormComponentFilterInterface {

  use LingotekFormComponentBundleTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * FormComponentFilterBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type.manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity_type.bundle.info service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language_manager service.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The lingotek.configuration service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The lingotek.content_translation service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $lingotek_configuration, $translation_service);
    $this->connection = $connection;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
      $container->get('entity_type.bundle.info'),
      $container->get('language_manager'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.content_translation'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterKey() {
    return [
      $this->getGroupMachineName(),
      $this->pluginDefinition['id'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupMachineName() {
    return parent::getGroupMachineName() ?? 'wrapper';
  }

  /**
   * {@inheritdoc}
   */
  public function buildGroupElement() {
    if ($group = $this->pluginDefinition['group'] ?? NULL) {
      return [
        '#type' => 'details',
        '#title' => $group,
        '#open' => FALSE,
        '#attributes' => ['class' => ['form--inline', 'clearfix']],
      ];
    }
    // Default wrapper.
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
      '#weight' => -1000,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmittedValue($submitted) {
    return NestedArray::getValue($submitted, $this->getFilterKey());
  }

  public function addCondition(SelectInterface &$query = NULL, $field, $value = NULL, $operator = NULL, $langcode = NULL) {
    $query->condition($field, $value, $operator, $langcode);
    if ($unions = $query->getUnion()) {
      foreach ($unions as $union) {
        $union['query']->condition($field, $value, $operator, $langcode);
      }
    }
  }

  public function addInnerJoin(SelectInterface &$query = NULL, $table, $alias = NULL, $condition = NULL, $arguments = []) {
    $query->innerJoin($table, $alias, $condition, $arguments);
    if ($unions = $query->getUnion()) {
      foreach ($unions as $union) {
        $union['query']->innerJoin($table, $alias, $condition, $arguments);
      }
    }
  }

}
