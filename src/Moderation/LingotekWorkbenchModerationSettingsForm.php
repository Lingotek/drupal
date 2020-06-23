<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation settings form for the Lingotek workbench_moderation integration.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekWorkbenchModerationSettingsForm implements LingotekModerationSettingsFormInterface {

  use StringTranslationTrait;

  use LingotekWorkbenchModerationCheckTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation configuration.
   *
   * @var \Drupal\lingotek\Moderation\LingotekModerationConfigurationServiceInterface
   */
  protected $moderationConfiguration;

  /**
   * The moderation information service.
   *
   * @var \Drupal\workbench_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a new LingotekWorkbenchModerationSettingsForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\lingotek\Moderation\LingotekModerationConfigurationServiceInterface $moderation_configuration
   *   A Lingotek moderation configuration service.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container from which optional services can be requested.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, LingotekModerationConfigurationServiceInterface $moderation_configuration, ContainerInterface $container, UrlGeneratorInterface $url_generator = NULL) {
    $this->setModuleHandler($module_handler);
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationConfiguration = $moderation_configuration;
    // We need a service we cannot depend on, as it may not exist if the module
    // is not present. Ignore the error.
    if ($container->has('workbench_moderation.moderation_information')) {
      $this->moderationInfo = $container->get('workbench_moderation.moderation_information');
    }
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnHeader() {
    return $this->t('Workbench Moderation');
  }

  /**
   * {@inheritdoc}
   */
  public function needsColumn($entity_type_id) {
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    return ($this->moduleHandler->moduleExists('workbench_moderation') &&
      ($this->moderationInfo !== NULL && $this->moderationInfo->isModeratableEntityType($entity_type_definition)));
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationUploadStatuses($entity_type_id, $bundle) {
    $states = $this->entityTypeManager->getStorage('moderation_state')->loadMultiple();
    $values = [];
    foreach ($states as $state_id => $state) {
      $values[$state_id] = $state->label();
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModerationUploadStatus($entity_type_id, $bundle) {
    $status = $this->moderationConfiguration->getUploadStatus($entity_type_id, $bundle);

    if (!$status) {
      $published_statuses = $this->entityTypeManager->getStorage('moderation_state')->getQuery()->condition('published', TRUE)->execute();
      if (count($published_statuses) > 0) {
        $status = reset($published_statuses);
      }
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationDownloadTransitions($entity_type_id, $bundle) {
    $transitions = $this->entityTypeManager->getStorage('moderation_state_transition')->loadMultiple();
    $values = [];
    $states = $this->getModerationUploadStatuses($entity_type_id, $bundle);
    /** @var \Drupal\workbench_moderation\ModerationStateTransitionInterface $transition */
    foreach ($transitions as $transition_id => $transition) {
      $values[$transition_id] = $this->t('@label [@from_state => @to_state]',
        ['@label' => $transition->label(), '@from_state' => $states[$transition->getFromState()], '@to_state' => $states[$transition->getToState()]]);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModerationDownloadTransition($entity_type_id, $bundle) {
    $transition = $this->moderationConfiguration->getDownloadTransition($entity_type_id, $bundle);

    if (!$transition) {
      $transitions = $this->entityTypeManager->getStorage('moderation_state_transition')->getQuery()
        ->condition('stateFrom', $this->getDefaultModerationUploadStatus($entity_type_id, $bundle))
        ->execute();
      if (count($transitions) > 0) {
        $transitions = $this->entityTypeManager->getStorage('moderation_state_transition')->loadMultiple($transitions);
        /** @var \Drupal\workbench_moderation\ModerationStateTransitionInterface $potential_transition */
        foreach ($transitions as $transition_id => $potential_transition) {
          /** @var \Drupal\workbench_moderation\ModerationStateInterface $toState */
          $toState = $this->entityTypeManager->getStorage('moderation_state')->load($potential_transition->getToState());
          if ($toState->isPublishedState()) {
            $transition = $transition_id;
            break;
          }
        }
      }
    }
    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public function form($entity_type_id, $bundle) {
    // We only add this option if the workbench moderation is enabled.
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $form = [];

    if ($this->moderationInfo->isModeratableBundle($entity_type_definition, $bundle)) {
      $statuses = $this->getModerationUploadStatuses($entity_type_id, $bundle);
      $default_status = $this->getDefaultModerationUploadStatus($entity_type_id, $bundle);

      $transitions = $this->getModerationDownloadTransitions($entity_type_id, $bundle);
      $default_transition = $this->getDefaultModerationDownloadTransition($entity_type_id, $bundle);
      $form['upload_status'] = [
        '#type' => 'select',
        '#options' => $statuses,
        '#default_value' => $default_status,
        '#title' => $this->t('In which status needs to be uploaded?'),
      ];
      $form['download_transition'] = [
        '#type' => 'select',
        '#options' => $transitions,
        '#default_value' => $default_transition,
        '#title' => $this->t('Which transition should be executed after download?'),
      ];
    }
    elseif ($this->moderationInfo->isModeratableEntityType($entity_type_definition)) {
      $bundle_type_id = $entity_type_definition->getBundleEntityType();
      $form = [
        '#markup' => $this->t('This entity bundle is not enabled for moderation with workbench_moderation. You can change its settings <a href=":moderation">here</a>.',
          [':moderation' => $this->urlGenerator->generateFromRoute("entity.$bundle_type_id.moderation", [$bundle_type_id => $bundle])]),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitHandler($entity_type_id, $bundle, array $form_values) {
    if (isset($form_values['moderation'])) {
      $upload_status = $form_values['moderation']['upload_status'];
      $download_transition = $form_values['moderation']['download_transition'];

      $this->moderationConfiguration->setUploadStatus($entity_type_id, $bundle, $upload_status);
      $this->moderationConfiguration->setDownloadTransition($entity_type_id, $bundle, $download_transition);
    }
  }

}
