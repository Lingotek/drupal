<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentBundleTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek form-field plugin for an entity's bundle.
 *
 * @LingotekFormComponentField(
 *   id = "bundle",
 *   title = @Translation("Bundle"),
 *   weight = 100,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   }
 * )
 */
class Bundle extends LingotekFormComponentFieldBase {

  use LingotekFormComponentBundleTrait;

  /**
   * LingotekFormComponentBase constructor.
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
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    $entity_type_id = isset($arguments['entity_type_id']) ? $arguments['entity_type_id'] : NULL;
    // If we are showing multiple entity types, we default to show the bundle
    // field.
    return $entity_type_id ? $this->hasBundles($entity_type_id) : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader($entity_type_id = NULL) {
    if ($entity_type_id === NULL) {
      return $this->t('Bundle');
    }
    $entity_type = $this->getEntityType($entity_type_id);
    return ['data' => $entity_type->getBundleLabel()];
  }

  /**
   * {@inheritdoc}
   */
  public function getData(EntityInterface $entity) {
    if ($bundle_info = $this->getBundleInfo($entity->getEntityTypeId()) ?? []) {
      $data = $bundle_info[$entity->bundle()]['label'] ?? '';
    }

    return $data ?? '';
  }

}
