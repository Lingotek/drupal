<?php

namespace Drupal\lingotek\Moderation;

use Drupal\content_moderation\ContentModerationState;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation settings form for the Lingotek content_moderation integration.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekContentModerationSettingsForm implements LingotekModerationSettingsFormInterface {

  use StringTranslationTrait;

  use LingotekContentModerationCheckTrait;

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
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new LingotekContentModerationSettingsForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\lingotek\Moderation\LingotekModerationConfigurationServiceInterface $moderation_configuration
   *   A Lingotek moderation configuration service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container from which optional services can be requested.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, LingotekModerationConfigurationServiceInterface $moderation_configuration, EntityTypeBundleInfoInterface $entity_type_bundle_info, ContainerInterface $container) {
    $this->setModuleHandler($module_handler);
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationConfiguration = $moderation_configuration;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    // We need a service we cannot depend on, as it may not exist if the module
    // is not present. Ignore the error.
    if ($container->has('content_moderation.moderation_information')) {
      $this->moderationInfo = $container->get('content_moderation.moderation_information');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnHeader() {
    return $this->t('Content moderation');
  }

  /**
   * {@inheritdoc}
   */
  public function needsColumn($entity_type_id) {
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    return ($this->moduleHandler->moduleExists('content_moderation') &&
      ($this->moderationInfo !== NULL && $this->moderationInfo->canModerateEntitiesOfEntityType($entity_type_definition)));
  }

  /**
   * Gets the workflow for the given entity type id and bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   * @return \Drupal\workflows\WorkflowInterface|null
   */
  protected function getWorkflow($entity_type_id, $bundle) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $workflow = NULL;
    if (isset($bundles[$bundle]['workflow'])) {
      $workflow = $this->entityTypeManager->getStorage('workflow')->load($bundles[$bundle]['workflow']);
    }
    return $workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationUploadStatuses($entity_type_id, $bundle) {
    $workflow = $this->getWorkflow($entity_type_id, $bundle);
    $values = [];
    if ($workflow) {
      $states = $this->getWorkflowStates($workflow);
      foreach ($states as $state_id => $state) {
        $values[$state_id] = $state->label();
      }
    }
    return $values;
  }

  /**
   * Get workflow states helper method.
   *
   * Needed because of differences in 8.3.x and 8.4.x.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   *
   * @return \Drupal\workflows\StateInterface[]
   *   The states.
   */
  protected  function getWorkflowStates(WorkflowInterface $workflow) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      return $workflow->getTypePlugin()->getStates();
    }
    else {
      return $workflow->getStates();
    }
  }

  /**
   * Get workflow transitions helper method.
   *
   * Needed because of differences in 8.3.x and 8.4.x.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   *
   * @return \Drupal\workflows\TransitionInterface[]
   *   An array of transition objects.
   */
  protected  function getWorkflowTransitions(WorkflowInterface $workflow) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      return $workflow->getTypePlugin()->getTransitions();
    }
    else {
      return $workflow->getTransitions();
    }
  }

  /**
   * Get workflow transitions for a given state helper method.
   *
   * Needed because of differences in 8.3.x and 8.4.x.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   *
   * @param string $state
   *   State id.
   *
   * @return \Drupal\workflows\TransitionInterface[]
   *   An array of transition objects.
   */
  protected  function getWorkflowTransitionsForState(WorkflowInterface $workflow, $state) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      return $workflow->getTypePlugin()->getTransitionsForState($state);
    }
    else {
      return $workflow->getTransitionsForState($state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModerationUploadStatus($entity_type_id, $bundle) {
    $status = $this->moderationConfiguration->getUploadStatus($entity_type_id, $bundle);
    if (!$status) {
      $workflow = $this->getWorkflow($entity_type_id, $bundle);
      $states = $this->getWorkflowStates($workflow);
      $published_statuses = array_filter($states, function (ContentModerationState $state) {
        return $state->isPublishedState();
      });
      if (count($published_statuses) > 0) {
        $status = reset($published_statuses)->id();
      }
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationDownloadTransitions($entity_type_id, $bundle) {
    $workflow = $this->getWorkflow($entity_type_id, $bundle);
    $transitions = $this->getWorkflowTransitions($workflow);
    $values = [];
    foreach ($transitions as $transition_id => $transition) {
      $values[$transition_id] = $transition->label();
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModerationDownloadTransition($entity_type_id, $bundle) {
    $transition = $this->moderationConfiguration->getDownloadTransition($entity_type_id, $bundle);

    if (!$transition) {
      $workflow = $this->getWorkflow($entity_type_id, $bundle);
      $transitions = $this->getWorkflowTransitionsForState($workflow, $this->getDefaultModerationUploadStatus($entity_type_id, $bundle));

      if (count($transitions) > 0) {
        /** @var \Drupal\workflows\TransitionInterface $potential_transition */
        foreach ($transitions as $transition_id => $potential_transition) {
          $toState = $potential_transition->to();
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

    if ($this->moderationInfo->shouldModerateEntitiesOfBundle($entity_type_definition, $bundle)) {
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
    elseif ($this->moderationInfo->canModerateEntitiesOfEntityType($entity_type_definition)) {
      $bundle_type_id = $entity_type_definition->getBundleEntityType();
      $form = [
        '#markup' => $this->t('This entity bundle is not enabled for moderation with content_moderation. You can change its settings <a href=":moderation">here</a>.', [':moderation' => $this->getContentModerationConfigurationLink($bundle, $bundle_type_id)]),
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

  /**
   * Get configure link for content moderation.
   *
   * Needed because of differences in 8.3.x and 8.4.x.
   *
   * @param string $bundle
   *   The bundle id.
   * @param string $bundle_type_id
   *   The bundle type id.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   An url.
   */
  protected function getContentModerationConfigurationLink($bundle, $bundle_type_id) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      return \Drupal::url("entity.workflow.collection");
    }
    else {
      return \Drupal::url("entity.$bundle_type_id.moderation", [$bundle_type_id => $bundle]);
    }
  }

}
