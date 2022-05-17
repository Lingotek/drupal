<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionBase;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek bulk action plugin for the upload operation.
 *
 * @LingotekFormComponentBulkAction(
 *   id = "assign_job",
 *   title = @Translation("Assign Job ID"),
 *   group = @Translation("Job Management"),
 *   weight = 140,
 *   options = {
 *     "job_id",
 *   },
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   },
 *   redirect = "lingotek.assign_job_entity_multiple_form"
 * )
 */
class AssignJobId extends LingotekFormComponentBulkActionBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The user currently logged in.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * AssignJobId constructor.
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
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The lingotek.configuration service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The lingotek.content_translation service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity_type.bundle.info service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, EntityTypeBundleInfoInterface $entity_type_bundle_info, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $lingotek_configuration, $translation_service);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->languageLocaleMapper = $language_locale_mapper;
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
      $container->get('lingotek.language_locale_mapper'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.content_translation'),
      $container->get('entity_type.bundle.info'),
      $container->get('tempstore.private'),
      $container->get('current_user')
    );
  }

  public function execute(array $entities, array $options, LingotekFormComponentBulkActionExecutor $executor) {
    $entityInfo = [];
    foreach ($entities as $entity) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $language = $entity->getUntranslated()->language();
      $entityInfo[$entity->getEntityTypeId()][$entity->id()] = [$language->getId() => $language->getId()];
    }
    $this->tempStoreFactory->get('lingotek_assign_job_entity_multiple_confirm')
      ->set($this->currentUser->id(), $entityInfo);
    return TRUE;
  }

}
