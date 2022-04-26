<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek form-filter plugin for the moderation state.
 *
 * @LingotekFormComponentFilter(
 *   id = "moderation_state",
 *   title = @Translation("Moderation state"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 900,
 *   group = @Translation("Advanced options"),
 * )
 */
class ModerationState extends LingotekFormComponentFilterBase {

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The content_moderation.moderation_information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface|null
   */
  protected $moderationInformation;

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module_handler service.
   * @param \Drupal\content_moderation\ModerationInformationInterface|null $moderation_information
   *   The content_moderation.moderation_information service if available.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, Connection $connection, ModuleHandlerInterface $module_handler, $moderation_information) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info, $language_manager, $lingotek_configuration, $translation_service, $connection);
    $this->moduleHandler = $module_handler;
    if ($moderation_information) {
      $this->moderationInformation = $moderation_information;
    }
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
      $container->get('module_handler'),
      $container->has('content_moderation.moderation_information') ? $container->get('content_moderation.moderation_information') : NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    $entity_type_id = isset($arguments['entity_type_id']) ? $arguments['entity_type_id'] : NULL;
    if (!$this->moduleHandler->moduleExists('content_moderation') || $this->moderationInformation == NULL) {
      return FALSE;
    }
    $entity_type = $this->getEntityType($entity_type_id);
    return $this->moderationInformation->isModeratedEntityType($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    /** @var \Drupal\workflows\Entity\Workflow $workflow */
    if ($workflow = $this->entityTypeManager->getStorage('workflow')->load('editorial')) {
      $states = $workflow->getTypePlugin()->getStates();
      $options = ['' => $this->t('All')];

      foreach ($states as $state_id => $state) {
        $options[$state_id] = $state->label();
      }

      return [
        '#type' => 'select',
        '#title' => $this->getTitle(),
        '#default_value' => $default_value ?? '',
        '#options' => $options,
      ];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    parent::filter($entity_type_id, $entities, $value, $query);
    $entity_type = $this->getEntityType($entity_type_id);
    $content_moderation_type = $this->entityTypeManager->getDefinition('content_moderation_state');

    $query->innerJoin($content_moderation_type->getDataTable(), 'content_moderation_data', 'entity_table.' . $entity_type->getKey('id') . '= content_moderation_data.content_entity_id');
    $query->condition('content_moderation_data.moderation_state', $value);

    if ($unions = $query->getUnion()) {
      foreach ($unions as $union) {
        $union['query']->innerJoin($content_moderation_type->getDataTable(), 'content_moderation_data', 'entity_table.' . $entity_type->getKey('id') . '= content_moderation_data.content_entity_id');
        $union['query']->condition('content_moderation_data.moderation_state', $value);
      }
    }
  }

}
