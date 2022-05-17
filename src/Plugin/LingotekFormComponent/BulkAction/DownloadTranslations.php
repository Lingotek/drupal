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
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek bulk action plugin for the request all translations
 * operation.
 *
 * @LingotekFormComponentBulkAction(
 *   id = "download_translations",
 *   title = @Translation("Download all translations"),
 *   group = @Translation("Download translations"),
 *   weight = 75,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   },
 *   batch = {
 *     "title" = @Translation("Downloading translations from the Lingotek service."),
 *     "function" = "createBatch"
 *   }
 * )
 */
class DownloadTranslations extends LingotekFormComponentBulkActionBase {

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
      $container->get('entity_type.bundle.info'),
    );
  }

  public function createBatch(LingotekFormComponentBulkActionExecutor $executor, array $entities, array $options) {
    $plugin_manager = \Drupal::service('plugin.manager.lingotek_form_bulk_action');
    // We can pass any valid value, as if we reach this we have a valid form id
    // already.
    $plugins = $plugin_manager->getApplicable(['form_id' => 'lingotek_management']);
    // If that preference is active, we use the action for each language
    // independently.
    $split_download_all = $this->lingotekConfiguration->getPreference('split_download_all');
    if ($split_download_all) {
      foreach ($entities as $entity) {
        $languages = $this->lingotekConfiguration->getEnabledLanguages();
        foreach ($languages as $langcode => $language) {
          if (isset($plugins['download_translation:' . $langcode])) {
            $operations[] = [
              [
                $executor,
                'doExecuteSingle',
              ],
              [$plugins['download_translation:' . $langcode], $entity, $options, $plugins['upload'] ?? NULL],
            ];
          }
        }
      }
      $batch = [
        'title' => $this->getPluginDefinition()['batch']['title'],
        'operations' => $operations,
        'finished' => [$this, 'finished'],
        'progressive' => TRUE,
      ];
      batch_set($batch);
    }
    else {
      foreach ($entities as $entity) {
        $operations[] = [[$this, 'executeSingle'], [$entity, $options, $executor]];
      }
      $batch = [
        'title' => $this->getTitle(),
        'operations' => $operations,
        'finished' => [$this, 'finished'],
        'progressive' => TRUE,
      ];
      batch_set($batch);
      return TRUE;
    }
  }

  public function executeSingle(ContentEntityInterface $entity, array $options, LingotekFormComponentBulkActionExecutor $executor, array &$context) {
    $context['message'] = $this->t('Downloading all translations for @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning($this->t('Cannot download translations for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning($this->t('Cannot download translations for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return FALSE;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $langcode => $language) {
        if ($langcode !== $entity->language()->getId()) {
          $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
          if ($this->translationService->checkTargetStatus($entity, $langcode)) {
            try {
              $this->translationService->downloadDocument($entity, $locale);
            }
            catch (LingotekDocumentNotFoundException $exc) {
              $this->messenger()->addError($this->t('Document @entity_type %title was not found. Please upload again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
              return FALSE;
            }
            catch (LingotekApiException $exception) {
              $this->messenger()->addError($this->t('The download for @entity_type %title translation failed. Please try again.', [
                '@entity_type' => $entity->getEntityTypeId(),
                '%title' => $entity->label(),
              ]));
              return FALSE;
            }
          }
        }
      }
    }
    else {
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
      return FALSE;
    }
    return TRUE;
  }

}
