<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content moderation handler managing the Lingotek integration.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekContentModerationHandler implements LingotekModerationHandlerInterface {

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
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Constructs a new LingotekContentModerationHandler object.
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
  public function shouldModerationPreventUpload(EntityInterface $entity) {
    $prevent = FALSE;
    if ($this->moduleHandler->moduleExists('content_moderation')) {
      $moderationEnabled = $this->isModerationEnabled($entity);
      if ($moderationEnabled) {
        $uploadStatus = $this->moderationConfiguration->getUploadStatus($entity->getEntityTypeId(), $entity->bundle());
        $state = $this->getModerationState($entity);
        if (!empty($state) && $state !== $uploadStatus) {
          $prevent = TRUE;
        }
      }
    }
    return $prevent;
  }

  /**
   * {@inheritdoc}
   */
  public function performModerationTransitionIfNeeded(ContentEntityInterface &$entity) {
    if ($this->moderationInfo->shouldModerateEntitiesOfBundle($entity->getEntityType(), $entity->bundle())) {
      $transition = $this->moderationConfiguration->getDownloadTransition($entity->getEntityTypeId(), $entity->bundle());
      if ($transition) {
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
        $workflow = NULL;
        if (isset($bundles[$entity->bundle()]['workflow'])) {
          /** @var \Drupal\workflows\WorkflowInterface $workflow */
          $workflow = $this->entityTypeManager->getStorage('workflow')
            ->load($bundles[$entity->bundle()]['workflow']);
          if ($workflow && $workflow->getTypePlugin()->hasTransition($transition)) {
            $theTransition = $workflow->getTypePlugin()->getTransition($transition);
            if ($theTransition !== NULL) {
              // Ensure we can execute this transition.
              $state = $this->getModerationState($entity);
              $validStates = $theTransition->from();
              $validStatesIds = array_keys($validStates);
              if (in_array($state, $validStatesIds)) {
                $this->setModerationState($entity, $theTransition->to()->id());
              }
            }
          }
          else {
            \Drupal::logger('lingotek')->warning('Cannot execute transition for @bundle @entity as the workflow @workflow transition @transition cannot be loaded', [
              '@bundle' => $entity->bundle(),
              '@entity' => (string) $entity->label(),
              '@workflow' => (string) $workflow->label(),
              '@transition' => $transition,
            ]);
          }
        }
      }
    }
  }

  /**
   * Get workflow transition helper method.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   * @param $transition_id
   *   The transition id.
   *
   * @return \Drupal\workflows\TransitionInterface
   *   A transition.
   *
   * @deprecated in lingotek:3.0.0 and is removed from lingotek:4.0.0.
   *   Use $workflow->getTypePlugin()->getTransition($transition_id) instead.
   * @see \Drupal\workflows\WorkflowTypeInterface::getTransition()
   */
  protected function getWorkflowTransition(WorkflowInterface $workflow, $transition_id) {
    return $workflow->getTypePlugin()->getTransition($transition_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationState(ContentEntityInterface $entity) {
    $state = NULL;
    if ($this->moderationInfo->isModeratedEntity($entity)) {
      $state = $entity->get('moderation_state')->getString();
    }
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function setModerationState(ContentEntityInterface $entity, $state) {
    $entity->set('moderation_state', $state);
  }

  /**
   * {@inheritdoc}
   */
  public function isModerationEnabled(EntityInterface $entity) {
    $moderationEnabled = FALSE;
    $moderationClass = $entity->getEntityType()->getHandlerClass('moderation');
    if ($moderationClass) {
      $implements = class_implements($moderationClass);
      $moderationEnabled = in_array('Drupal\content_moderation\Entity\Handler\ModerationHandlerInterface', $implements);
    }
    return $moderationEnabled;
  }

}
