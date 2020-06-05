<?php

namespace Drupal\lingotek\Moderation;

use Drupal\content_moderation\ContentModerationState;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
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
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

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
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, LingotekModerationConfigurationServiceInterface $moderation_configuration, EntityTypeBundleInfoInterface $entity_type_bundle_info, ContainerInterface $container, UrlGeneratorInterface $url_generator) {
    $this->setModuleHandler($module_handler);
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationConfiguration = $moderation_configuration;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    // We need a service we cannot depend on, as it may not exist if the module
    // is not present. Ignore the error.
    if ($container->has('content_moderation.moderation_information')) {
      $this->moderationInfo = $container->get('content_moderation.moderation_information');
    }
    $this->urlGenerator = $url_generator;
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
   *
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
      $states = $workflow->getTypePlugin()->getStates();
      foreach ($states as $state_id => $state) {
        $values[$state_id] = $state->label();
      }
    }
    return $values;
  }

  /**
   * Get workflow states helper method.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   *
   * @return \Drupal\workflows\StateInterface[]
   *   The states.
   *
   * @deprecated in lingotek:3.0.0 and is removed from lingotek:4.0.0.
   *   Use $workflow->getTypePlugin()->getStates() instead.
   * @see \Drupal\workflows\WorkflowTypeInterface::getStates()
   */
  protected  function getWorkflowStates(WorkflowInterface $workflow) {
    return $workflow->getTypePlugin()->getStates();
  }

  /**
   * Get workflow transitions helper method.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   *
   * @return \Drupal\workflows\TransitionInterface[]
   *   An array of transition objects.
   *
   * @deprecated in lingotek:3.0.0 and is removed from lingotek:4.0.0.
   *   Use $workflow->getTypePlugin()->getTransitions() instead.
   * @see \Drupal\workflows\WorkflowTypeInterface::getTransitions()
   */
  protected  function getWorkflowTransitions(WorkflowInterface $workflow) {
    return $workflow->getTypePlugin()->getTransitions();
  }

  /**
   * Get workflow transitions for a given state helper method.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   *
   * @param string $state
   *   State id.
   *
   * @return \Drupal\workflows\TransitionInterface[]
   *   An array of transition objects.
   *
   * @deprecated in lingotek:3.0.0 and is removed from lingotek:4.0.0.
   *   Use $workflow->getTypePlugin()->getTransitionsForState($state) instead.
   * @see \Drupal\workflows\WorkflowTypeInterface::getTransitionsForState()
   */
  protected  function getWorkflowTransitionsForState(WorkflowInterface $workflow, $state) {
    return $workflow->getTypePlugin()->getTransitionsForState($state);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModerationUploadStatus($entity_type_id, $bundle) {
    $status = $this->moderationConfiguration->getUploadStatus($entity_type_id, $bundle);
    if (!$status) {
      $workflow = $this->getWorkflow($entity_type_id, $bundle);
      $states = $workflow->getTypePlugin()->getStates();
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
    $transitions = $workflow->getTypePlugin()->getTransitions();
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
      $transitions = $workflow->getTypePlugin()->getTransitionsForState($this->getDefaultModerationUploadStatus($entity_type_id, $bundle));

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
      $form = [
        '#markup' => $this->t('This entity bundle is not enabled for moderation with content_moderation. You can change its settings <a href=":moderation">here</a>.', [':moderation' => $this->urlGenerator->generateFromRoute("entity.workflow.collection")]),
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
   * @param string $bundle
   *   The bundle id.
   * @param string $bundle_type_id
   *   The bundle type id.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   An url.
   *
   * @deprecated in lingotek:3.0.0 and is removed from lingotek:4.0.0.
   *   Use $this->urlGenerator->generateFromRoute("entity.workflow.collection")
   *   instead.
   * @see \Drupal\workflows\Entity\Workflow
   */
  protected function getContentModerationConfigurationLink($bundle, $bundle_type_id) {
    return $this->urlGenerator->generateFromRoute("entity.workflow.collection");
  }

}
