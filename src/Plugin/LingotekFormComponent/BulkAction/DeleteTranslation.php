<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionBase;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek bulk action plugin for the delete all translations
 * operation.
 *
 * @LingotekFormComponentBulkAction(
 *   id = "delete_translation",
 *   title = @Translation("Delete %language (%langcode) translation"),
 *   group = @Translation("Delete translations"),
 *   weight = 92,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   },
 *   redirect = "entity:delete-multiple-form",
 *   deriver = "Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\Derivative\LanguageLingotekBulkActionDeriver",
 * )
 */
class DeleteTranslation extends LingotekFormComponentBulkActionBase {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The user currently logged in.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * DeleteTranslation constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type.manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language_manager service.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The lingotek.configuration service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The lingotek.content_translation service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $lingotek_configuration, $translation_service);
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
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
      $container->get('language_manager'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.content_translation'),
      $container->get('tempstore.private'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    $entity_type_id = $arguments['entity_type_id'] ?? NULL;
    if ($entity_type_id != NULL) {
      return $this->entityTypeManager->getDefinition($entity_type_id)
        ->hasLinkTemplate('delete-multiple-form');
    }
    return FALSE;
  }

  public function execute(array $entities, array $options, LingotekFormComponentBulkActionExecutor $executor) {
    $langcode = $this->pluginDefinition['langcode'];
    $entityInfo = [];
    foreach ($entities as $entity) {
      $source_language = $entity->getUntranslated()->language();
      if ($source_language->getId() !== $langcode && $entity->hasTranslation($langcode)) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entityInfo[$entity->id()][$langcode] = $langcode;
      }
    }
    if (!empty($entityInfo)) {
      $this->tempStoreFactory->get('entity_delete_multiple_confirm')
        ->set($this->currentUser->id() . ':' . $this->entityTypeId, $entityInfo);
    }
    else {
      $this->messenger()->addWarning($this->t('No valid translations for deletion.'));
      // Ensure selection is persisted.
      return FALSE;
    }
    return TRUE;
  }

}
