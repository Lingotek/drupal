<?php

namespace Drupal\lingotek\Plugin\Action\Subscriber;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\system\Entity\Action;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LingotekActionsManagementSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Constructs a new LingotekActionsManagementSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ActionManager $action_manager, LingotekConfigurationServiceInterface $lingotek_configuration) {
    $this->entityTypeManager = $entity_type_manager;
    $this->actionManager = $action_manager;
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

  /**
   * Creates and deletes the actions associated with the enabled entities.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    if (\Drupal::isConfigSyncing()) {
      return;
    }

    $actions = [
      'entity:lingotek_upload_action',
      'entity:lingotek_check_upload_action',
      'entity:lingotek_request_translations_action',
      'entity:lingotek_check_translations_action',
      'entity:lingotek_download_translations_action',
      'entity:lingotek_cancel_action',
      'entity:lingotek_delete_translations_action',
    ];
    $lang_actions = [
      'entity:lingotek_check_translation_action',
      'entity:lingotek_download_translation_action',
      'entity:lingotek_request_translation_action',
      'entity:lingotek_delete_translation_action',
      'entity:lingotek_cancel_translation_action',
    ];

    $config = $event->getConfig();
    $configName = $config->getName();
    if ($configName === 'lingotek.settings' && $event->isChanged('translate.entity')) {
      $entity_types = $this->entityTypeManager->getDefinitions();
      $enabled_entity_types = $this->lingotekConfiguration->getEnabledEntityTypes();
      foreach ($entity_types as $entity_type_id => $entity_type) {
        foreach ($actions as $action) {
          $pluginId = $action . ':' . $entity_type_id;
          /** @var \Drupal\Component\Plugin\Definition\PluginDefinitionInterface $plugin */
          $plugin = $this->actionManager->getDefinition($pluginId);
          $action_id = $entity_type_id . '_' . str_replace('entity:', '', $action);
          $existingAction = $this->entityTypeManager->getStorage('action')
            ->load($action_id);
          if (isset($enabled_entity_types[$entity_type_id]) && !$existingAction) {
            Action::create([
              'id' => $action_id,
              'label' => $plugin['label'],
              'type' => $entity_type_id,
              'plugin' => $pluginId,
              'configuration' => [],
            ])->save();
          }
          elseif (!isset($enabled_entity_types[$entity_type_id]) && $existingAction) {
            $existingAction->delete();
          }
        }

        $languages = \Drupal::languageManager()->getLanguages();
        foreach ($lang_actions as $action) {
          foreach ($languages as $langcode => $language) {
            $pluginId = $action . ':' . $entity_type_id;
            /** @var \Drupal\Component\Plugin\Definition\PluginDefinitionInterface $plugin */
            $plugin = $this->actionManager->getDefinition($pluginId, FALSE);
            $action_id = $entity_type_id . '_' . $langcode . '_' . str_replace('entity:', '', $action);
            $existingAction = $this->entityTypeManager->getStorage('action')
              ->load($action_id);
            if ($plugin && isset($enabled_entity_types[$entity_type_id]) && !$existingAction) {
              Action::create([
                'id' => $action_id,
                'label' => new FormattableMarkup($plugin['action_label'], [
                  '@entity_label' => $entity_type->getSingularLabel(),
                  '@language' => $language->getName(),
                ]),
                'type' => $entity_type_id,
                'plugin' => $pluginId,
                'configuration' => ['language' => $langcode],
              ])->save();
            }
            elseif (!isset($enabled_entity_types[$entity_type_id]) && $existingAction) {
              $existingAction->delete();
            }
          }
        }

      }
    }

    if (0 === strpos($configName, 'language.entity.')) {
      $langcode = $config->get('id');
      $langname = $config->get('label');
      $entity_types = $this->entityTypeManager->getDefinitions();
      $enabled_entity_types = $this->lingotekConfiguration->getEnabledEntityTypes();
      foreach ($entity_types as $entity_type_id => $entity_type) {
        foreach ($lang_actions as $action) {
          $pluginId = $action . ':' . $entity_type_id;
          /** @var \Drupal\Component\Plugin\Definition\PluginDefinitionInterface $plugin */
          $plugin = $this->actionManager->getDefinition($pluginId, FALSE);
          $action_id = $entity_type_id . '_' . $langcode . '_' . str_replace('entity:', '', $action);
          $existingAction = $this->entityTypeManager->getStorage('action')
            ->load($action_id);
          if ($plugin && isset($enabled_entity_types[$entity_type_id]) && !$existingAction) {
            Action::create([
              'id' => $action_id,
              'label' => new FormattableMarkup($plugin['action_label'], [
                '@entity_label' => $entity_type->getSingularLabel(),
                '@language' => $langname,
              ]),
              'type' => $entity_type_id,
              'plugin' => $pluginId,
              'configuration' => ['language' => $langcode],
            ])->save();
          }
          elseif (!isset($enabled_entity_types[$entity_type_id]) && $existingAction) {
            $existingAction->delete();
          }
        }
      }
    }
  }

}
