<?php

namespace Drupal\lingotek\Views;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\views\EntityViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the lingotek views integration.
 *
 * @internal
 */
class LingotekContentMetadataViewsData extends EntityViewsData {

  /**
   * The lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfigService;

  /**
   * Constructs an EntityViewsData object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to provide views integration for.
   * @param \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage_controller
   *   The storage handler used for this entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The lingotek configuration service.
   */
  public function __construct(EntityTypeInterface $entity_type, SqlEntityStorageInterface $storage_controller, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, TranslationInterface $translation_manager, LingotekConfigurationServiceInterface $lingotek_configuration, EntityFieldManagerInterface $entity_field_manager = NULL) {
    parent::__construct($entity_type, $storage_controller, $entity_type_manager, $module_handler, $translation_manager, $entity_field_manager);
    $this->lingotekConfigService = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('lingotek.configuration'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Returns the views data.
   *
   * @return array
   *   The views data.
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['lingotek_metadata']['translation_source'] = [
      'title' => $this->t('Lingotek translation source'),
      'description' => $this->t('The source language from which this translation was created.'),
      'field' => [
        'id' => 'field',
        'default_formatter' => 'lingotek_translation_status',
        'field_name' => 'translation_source',
      ],
    ];

    $enabled_entity_types = array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $type) {
      return $this->lingotekConfigService->isEnabled($type->id());
    });

    // Provides a relationship from the entity to its lingotek status metadata
    // entity.
    $lingotek_state_entity_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
    $lingotek_state_entity_base_table = $lingotek_state_entity_type->getDataTable() ?: $lingotek_state_entity_type->getBaseTable();

    foreach ($enabled_entity_types as $entity_type_id => $entity_type) {
      $table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();

      $data[$table]['lingotek_translation_status'] = [
        'title' => t('Lingotek translation status'),
        'relationship' => [
          'id' => 'standard',
          'label' => $this->t('@label Lingotek translation status', ['@label' => $entity_type->getLabel()]),
          'base' => $lingotek_state_entity_base_table,
          'base field' => 'content_entity_id',
          'relationship field' => $entity_type->getKey('id'),
          'extra' => [
            [
              'field' => 'content_entity_type_id',
              'value' => $entity_type_id,
            ],
          ],
        ],
        'field' => [
          'id' => 'field',
          'default_formatter' => 'lingotek_translation_status',
          'field_name' => 'lingotek_translation_status',
        ],
        'filter' => [
          'id' => 'lingotek_translation_status',
          'allow empty' => TRUE,
        ],
      ];
    }

    return $data;
  }

}
