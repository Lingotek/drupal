<?php

namespace Drupal\lingotek\Moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Workbench moderation handler managing the Lingotek integration.
 *
 * @package Drupal\lingotek\Moderation
 */
class LingotekWorkbenchModerationHandler implements LingotekModerationHandlerInterface {

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
   * Constructs a new LingotekWorkbenchModerationHandler object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\lingotek\Moderation\LingotekModerationConfigurationServiceInterface $moderation_configuration
   *   A Lingotek moderation configuration service.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container from which optional services can be requested.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, LingotekModerationConfigurationServiceInterface $moderation_configuration, ContainerInterface $container) {
    $this->setModuleHandler($module_handler);
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationConfiguration = $moderation_configuration;
    // We need a service we cannot depend on, as it may not exist if the module
    // is not present. Ignore the error.
    if ($container->has('workbench_moderation.moderation_information')) {
      $this->moderationInfo = $container->get('workbench_moderation.moderation_information');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function shouldModerationPreventUpload(EntityInterface $entity) {
    $prevent = FALSE;
    if ($this->moduleHandler->moduleExists('workbench_moderation')) {
      $moderationEnabled = $this->isModerationEnabled($entity);
      if ($moderationEnabled) {
        $uploadStatus = $this->moderationConfiguration->getUploadStatus($entity->getEntityTypeId(), $entity->bundle());
        $state = $this->getModerationState($entity);
        if ($state !== $uploadStatus) {
          $prevent = TRUE;
        }
      }
    }
    return $prevent;
  }

  /**
   * {@inheritdoc}
   */
  public  function performModerationTransitionIfNeeded(ContentEntityInterface &$entity) {
    if ($this->moderationInfo->isModeratableBundle($entity->getEntityType(), $entity->bundle())) {
      $transition = $this->moderationConfiguration->getDownloadTransition($entity->getEntityTypeId(), $entity->bundle());
      if ($transition !== NULL) {
        $transition = $this->entityTypeManager->getStorage('moderation_state_transition')->load($transition);
        if ($transition !== NULL) {
          // Ensure we can execute this transition.
          if ($this->getModerationState($entity) === $transition->getFromState()) {
            $this->setModerationState($entity, $transition->getToState());
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getModerationState(ContentEntityInterface $entity) {
    return $entity->get('moderation_state')->target_id;
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
    $entityType = $entity->getEntityType();
    $bundleEntityType = $entityType->getBundleEntityType();
    if ($bundleEntityType !== NULL) {
      $bundleType = $this->entityTypeManager->getStorage($bundleEntityType)
        ->load($entity->bundle());
      $moderationEnabled = $bundleType->getThirdPartySetting('workbench_moderation', 'enabled', FALSE);
    }
    return $moderationEnabled;
  }

}
