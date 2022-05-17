<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionBase;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek bulk action plugin for the request operation for a single
 * language.
 *
 * @LingotekFormComponentBulkAction(
 *   id = "change_profile",
 *   title = @Translation("Change to %profile Profile"),
 *   group = @Translation("Change Translation Profile"),
 *   weight = 100,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   },
 *   batch = {
 *     "title" = @Translation("Updating Translation Profile.")
 *   },
 *   deriver = "Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\Derivative\ProfileLingotekBulkActionDeriver",
 * )
 */
class AssignProfile extends LingotekFormComponentBulkActionBase {

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
   * AssignProfile constructor.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $lingotek_configuration, $translation_service);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->languageLocaleMapper = $language_locale_mapper;
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
      $container->get('entity_type.bundle.info')
    );
  }

  public function executeSingle(ContentEntityInterface $entity, $options, LingotekFormComponentBulkActionExecutor $executor, array &$context) {
    $profile_id = $this->pluginDefinition['profile_id'];
    $context['message'] = $this->t('Changing Translation Profile for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning($this->t('Cannot change profile for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning($this->t('Cannot change profile for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return FALSE;
    }
    try {
      $this->lingotekConfiguration->setProfile($entity, $profile_id, TRUE);
      if ($profile_id === Lingotek::PROFILE_DISABLED) {
        $this->translationService->setSourceStatus($entity, Lingotek::STATUS_DISABLED);
        $this->translationService->setTargetStatuses($entity, Lingotek::STATUS_DISABLED);
      }
      elseif ($this->translationService->getSourceStatus($entity) === Lingotek::STATUS_DISABLED) {
        if ($this->translationService->getDocumentId($entity) !== NULL) {
          $this->translationService->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        }
        else {
          $this->translationService->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        }
        $this->translationService->checkTargetStatuses($entity);
      }
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError($this->t('The Translation Profile change for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      return FALSE;
    }
    return TRUE;
  }

}
