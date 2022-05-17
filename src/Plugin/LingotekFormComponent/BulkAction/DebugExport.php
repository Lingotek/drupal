<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\Entity\File;
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
 *   id = "debug_export",
 *   title = @Translation("Debug: Export sources as JSON"),
 *   group = @Translation("Debug"),
 *   weight = 255,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   },
 *   batch = {
 *     "title" = @Translation("Exporting content (debugging purposes)")
 *   }
 * )
 */
class DebugExport extends LingotekFormComponentBulkActionBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

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
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, EntityTypeBundleInfoInterface $entity_type_bundle_info, StateInterface $state, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $lingotek_configuration, $translation_service);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->state = $state;
    $this->time = $time;
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
      $container->get('state'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    return $this->state->get('lingotek.enable_debug_utilities', FALSE);
  }

  public function executeSingle(ContentEntityInterface $entity, $options, LingotekFormComponentBulkActionExecutor $executor, array &$context) {
    $context['message'] = $this->t('Exporting @type %label.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label()]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning(t('Cannot debug export @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot debug export @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return;
    }
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      $data = $this->translationService->getSourceData($entity);
      $data['_debug'] = [
        'title' => $entity->bundle() . ' (' . $entity->getEntityTypeId() . '): ' . $entity->label(),
        'profile' => $profile ? $profile->id() : '<null>',
        'source_locale' => $this->translationService->getSourceLocale($entity),
      ];
      $source_data = json_encode($data);
      $filename = $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $entity->id() . '.json';
      $file = File::create([
        'uid' => 1,
        'filename' => $filename,
        'uri' => 'public://' . $filename,
        'filemime' => 'text/plain',
        'created' => $this->time->getRequestTime(),
        'changed' => $this->time->getRequestTime(),
      ]);
      file_put_contents($file->getFileUri(), $source_data);
      $file->save();
      $context['results']['exported'][] = $file->id();
    }
    else {
      $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
    }
  }

  public function finished($success, $results, $operations) {
    if ($success) {
      $links = [];
      if (isset($results['exported'])) {
        $files = $this->entityTypeManager->getStorage('file')->loadMultiple($results['exported']);
        foreach ($files as $file) {
          $links[] = [
            '#theme' => 'file_link',
            '#file' => $file,
          ];
        }
        $build = [
          '#theme' => 'item_list',
          '#items' => $links,
        ];
        $this->messenger()->addStatus($this->t('Exports available at: @exports',
          ['@exports' => \Drupal::service('renderer')->render($build)]));
      }
    }
  }

}
