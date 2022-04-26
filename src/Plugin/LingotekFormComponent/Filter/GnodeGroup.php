<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek form-filter plugin for the Group Node group.
 *
 * @LingotekFormComponentFilter(
 *   id = "group",
 *   title = @Translation("Group"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 250,
 * )
 */
class GnodeGroup extends LingotekFormComponentFilterBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, Connection $connection, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info, $language_manager, $lingotek_configuration, $translation_service, $connection);
    $this->moduleHandler = $module_handler;
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
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    /**
     * @var string $form_id
     * @var string $entity_type_id
     */
    $entity_type_id = isset($arguments['entity_type_id']) ? $arguments['entity_type_id'] : NULL;
    return $this->moduleHandler->moduleExists('gnode') && $entity_type_id === 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'select',
      '#title' => $this->getTitle(),
      '#options' => ['' => $this->t('All')] + $this->getAllGroups(),
      '#default_value' => (array) $default_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    if ($value) {
      parent::filter($entity_type_id, $entities, $value, $query);
      $entity_type = $this->getEntityType($entity_type_id);
      /** @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface $groupContentEnablers */
      $group = $this->entityTypeManager->getStorage('group')->load($value);
      $groupType = $group->getGroupType();
      $groupContentEnablers = \Drupal::service('plugin.manager.group_content_enabler');
      $definitions = $groupContentEnablers->getDefinitions();
      $definitions = array_filter($definitions, function ($definition) {
        return ($definition['entity_type_id'] === 'node');
      });
      $valid_values = [];
      foreach ($definitions as $node_definition) {
        $valid_values[] = $groupType->id() . '-' . $node_definition['id'] . '-' . $node_definition['entity_bundle'];
      }
      $query->innerJoin('group_content_field_data', 'group_content', 'entity_table.' . $entity_type->getKey('id') . '= group_content.entity_id');
      $query->condition('group_content.gid', $value);
      $query->condition('group_content.type', $valid_values, 'IN');

      if ($unions = $query->getUnion()) {
        foreach ($unions as $union) {
          $union['query']->innerJoin('group_content_field_data', 'group_content', 'entity_table.' . $entity_type->getKey('id') . '= group_content.entity_id');
          $union['query']->condition('group_content.gid', $value);
          $union['query']->condition('group_content.type', $valid_values, 'IN');
        }
      }
    }
  }

  /**
   * Gets all the groups as options.
   *
   * @return array
   *   The groups as a valid options array.
   */
  protected function getAllGroups() {
    $options = [];
    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();
    foreach ($groups as $id => $group) {
      $options[$id] = $group->label();
    }
    return $options;
  }

}
