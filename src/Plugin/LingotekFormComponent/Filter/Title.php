<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek form-filter plugin for the entity label.
 *
 * @LingotekFormComponentFilter(
 *   id = "label",
 *   title = @Translation("Title"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 100,
 * )
 */
class Title extends LingotekFormComponentFilterBase {

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * ModerationState constructor.
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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, Connection $connection, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info, $language_manager, $lingotek_configuration, $translation_service, $connection);
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    $entity_type_id = isset($arguments['entity_type_id']) ? $arguments['entity_type_id'] : NULL;
    if ($entity_type_id === NULL) {
      return FALSE;
    }
    $entity_type = $this->getEntityType($entity_type_id);
    return $entity_type->hasKey('label');
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    $entity_type = $this->getEntityType($this->entityTypeId);
    $properties = $this->entityFieldManager->getBaseFieldDefinitions($this->entityTypeId);

    return [
      '#type' => 'textfield',
      '#title' => $properties[$entity_type->getKey('label')]->getLabel(),
      '#size' => 35,
      '#default_value' => $default_value !== NULL ? trim($default_value) : '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {

    if ($value = trim($value)) {
      parent::filter($entity_type_id, $entities, $value, $query);
      $entity_type = $this->getEntityType($entity_type_id);
      $id_key = $entity_type->getKey('id');
      $label_key = $entity_type->getKey('label');
      $query->innerJoin($entity_type->getDataTable(), 'entity_data', 'entity_table.' . $id_key . '= entity_data.' . $id_key);
      $query->condition('entity_data.' . $label_key, '%' . $value . '%', 'LIKE');
      if ($unions = $query->getUnion()) {
        foreach ($unions as $union) {
          $union['query']->innerJoin($entity_type->getDataTable(), 'entity_data', 'entity_table.' . $id_key . '= entity_data.' . $id_key);
          $union['query']->condition('entity_data.' . $label_key, '%' . $value . '%', 'LIKE');
        }
      }
    }
  }

}
