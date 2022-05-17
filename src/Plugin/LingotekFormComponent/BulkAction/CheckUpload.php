<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionBase;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek bulk action plugin for the upload status checking operation.
 *
 * @LingotekFormComponentBulkAction(
 *   id = "check_upload",
 *   title = @Translation("Check upload progress"),
 *   weight = 20,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   },
 *   batch = {
 *     "title" = @Translation("Checking content upload status with the Lingotek service")
 *   }
 * )
 */
class CheckUpload extends LingotekFormComponentBulkActionBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * DebugExport constructor.
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
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity_type.bundle.info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $lingotek_configuration, $translation_service);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
      $container->get('entity_type.bundle.info'),
    );
  }

  public function executeSingle(ContentEntityInterface $entity, $options, LingotekFormComponentBulkActionExecutor $executor, array &$context) {
    $context['message'] = $this->t('Checking status of @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning($this->t('Cannot check upload for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning($this->t('Cannot check upload for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return FALSE;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        if (!$this->translationService->checkSourceStatus($entity)) {
          $this->messenger()->addStatus($this->t('The import for @entity_type %label is still pending.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '%label' => $entity->label(),
          ]));
        }
        return TRUE;
      }
      catch (LingotekDocumentNotFoundException $exc) {
        $this->messenger()->addError($this->t('Document @entity_type %title was not found. Please upload again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        return FALSE;
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError($this->t('The upload status check for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        return FALSE;
      }
    }
    else {
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
      return FALSE;
    }
  }

}
