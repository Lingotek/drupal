<?php

namespace Drupal\lingotek\Form;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabConfigurationForm extends LingotekConfigFormBase {

  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = ['entityQuery' => 'entity.query'];

  /**
   * Alows to access deprecated/removed properties.
   *
   * This method must be public.
   */
  public function __get($name) {
    if (isset($this->deprecatedProperties[$name])) {
      $service_name = $this->deprecatedProperties[$name];
      $class_name = static::class;
      @trigger_error("The property $name ($service_name service) is deprecated in $class_name and will be removed before Lingotek 9.x-1.0", E_USER_DEPRECATED);
      return NULL;
    }
  }

  protected $profile_options;
  protected $profiles;
  protected $bundles;
  protected $translatable_bundles;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfig;

  /**
   * The Lingotek config translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface
   */
  protected $translationService;

  /**
   * A array of configuration mapper instances.
   *
   * @var \Drupal\config_translation\ConfigMapperInterface[]
   */
  protected $mappers;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LingotekManagementForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param $entity_query
   *   (deprecated) The entity query factory.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config
   *   The Lingotek config service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   *   The Lingotek config translation service.
   * @param \Drupal\config_translation\ConfigMapperInterface[] $mappers
   *   The configuration mapper manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(LanguageManagerInterface $language_manager, $entity_query, LingotekConfigurationServiceInterface $lingotek_config, LingotekConfigTranslationServiceInterface $translation_service, array $mappers, EntityTypeManagerInterface $entity_type_manager = NULL) {
    $this->languageManager = $language_manager;
    if (get_class($entity_query) === '\Drupal\Core\Entity\Query\QueryFactory') {
      @trigger_error('The entity.query service is deprecated. Pass the entity_type.manager service to LingotekSettingsTabConfigurationForm::__construct instead. It is required before Lingotek 9.x-1.0. See https://www.drupal.org/node/2549139.', E_USER_DEPRECATED);
    }
    $this->lingotekConfig = $lingotek_config;
    $this->translationService = $translation_service;
    $this->mappers = $mappers;
    if (!$entity_type_manager) {
      @trigger_error('The entity_type.manager service must be passed to LingotekSettingsTabConfigurationForm::__construct, it is required before Lingotek 9.x-1.0. See https://www.drupal.org/node/2549139.', E_USER_DEPRECATED);
      $entity_type_manager = \Drupal::service('entity_type.manager');
    }
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.config_translation'),
      $container->get('plugin.manager.config_translation.mapper')->getMappers(),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $profile_options = $this->lingotekConfig->getProfileOptions();

    $header = [
      'enabled' => $this->t('Enable'),
      'type' => $this->t('Configuration Type'),
      'profile' => $this->t('Translation Profile'),
    ];

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No Entries'),
    ];

    foreach ($this->mappers as $mapper) {
      // We don't want to show config objects, where we only have one instance.
      // Just show config entities.
      if ($mapper instanceof ConfigEntityMapper) {
        $enabled = $this->translationService->isEnabled($mapper->getPluginId());
        $row = [];
        $row['enabled'] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
        ];
        $row['type'] = [
          '#markup' => $mapper->getTypeLabel(),
        ];
        $row['profile'] = [
          '#type' => 'select',
          '#options' => $this->lingotekConfig->getProfileOptions(),
          '#default_value' => $this->lingotekConfig->getConfigEntityDefaultProfileId($mapper->getPluginId()),
        ];
        $table[$mapper->getPluginId()] = $row;
      }
    }
    ksort($table);

    $form['config'] = [
      '#type' => 'details',
      '#title' => 'Translate Configuration Types',
    ];

    $form['config']['table'] = $table;

    $form['config']['actions']['#type'] = 'actions';
    $form['config']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue(['table']);
    foreach ($values as $plugin_id => $data) {
      // We only save the enabled status if it changed.
      if ($data['enabled'] && !$this->translationService->isEnabled($plugin_id)) {
        $this->translationService->setEnabled($plugin_id, TRUE);
      }
      if (!$data['enabled'] && $this->translationService->isEnabled($plugin_id)) {
        $this->translationService->setEnabled($plugin_id, FALSE);
      }
      // If we enable it, we save the profile.
      if ($data['enabled'] && $data['profile'] !== $this->lingotekConfig->getConfigEntityDefaultProfileId($plugin_id, FALSE)) {
        $this->lingotekConfig->setConfigEntityDefaultProfileId($plugin_id, $data['profile']);
      }
    }
  }

}
