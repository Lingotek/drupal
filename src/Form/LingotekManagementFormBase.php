<?php

namespace Drupal\lingotek\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionManager;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionOptionManager;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldManager;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterManager;
use Drupal\lingotek\Helpers\LingotekManagementFormHelperTrait;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekSetupTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk management of content.
 */
abstract class LingotekManagementFormBase extends FormBase {

  use LingotekManagementFormHelperTrait;

  use LingotekSetupTrait;

  /**
   * The connection object on which to run queries.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

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
  protected $lingotekConfiguration;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Available form-field plugins.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentFieldInterface[]
   */
  protected $formFields = [];

  /**
   * The table's headers.
   *
   * @var array|null
   */
  protected $headers = NULL;

  /**
   * Available form-filter plugins.
   *
   * @var \Drupal\lingotek\FormComponent\FormComponentFilterInterface[]
   */
  protected $formFilters = [];

  /**
   * Available form-bulk-actions plugins.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionInterface[]
   */
  protected $formBulkActions = [];

  /**
   * Available form-bulk-actions options plugins.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionOptionInterface[]
   */
  protected $formBulkActionOptions = [];

  /**
   * Available form-bulk-actions plugin manager.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionManager
   */
  protected $formBulkActionManager;

  /**
   * Available form-bulk-actions options plugin manager.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionOptionManager
   */
  protected $formBulkActionOptionsManager;

  /**
   * Available form-bulk-actions executor.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor
   */
  protected $formBulkActionExecutor;

  /**
   * Constructs a new LingotekManagementFormBase object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The current database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The Lingotek service.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The Lingotek content translation service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param string $entity_type_id
   *   The entity type id.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\lingotek\FormComponent\LingotekFormComponentFieldManager $form_field_manager
   *   The form-field plugin manager.
   * @param \Drupal\lingotek\FormComponent\LingotekFormComponentFilterManager $form_filter_manager
   *   The form-filter plugin manager.
   * @param \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionManager $form_actions_manager
   *   The form-actions plugin manager.
   * @param \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionOptionManager $form_action_options_manager
   *   The form-action options plugin manager.
   * @param \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor $form_bulk_action_executor
   *   The form-action options plugin manager.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LingotekInterface $lingotek, LingotekConfigurationServiceInterface $lingotek_configuration, LanguageLocaleMapperInterface $language_locale_mapper, ContentTranslationManagerInterface $content_translation_manager, LingotekContentTranslationServiceInterface $translation_service, PrivateTempStoreFactory $temp_store_factory, StateInterface $state, ModuleHandlerInterface $module_handler, $entity_type_id, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, LingotekFormComponentFieldManager $form_field_manager, LingotekFormComponentFilterManager $form_filter_manager, LingotekFormComponentBulkActionManager $form_actions_manager, LingotekFormComponentBulkActionOptionManager $form_action_options_manager, LingotekFormComponentBulkActionExecutor $form_bulk_action_executor) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->contentTranslationManager = $content_translation_manager;
    $this->lingotek = $lingotek;
    $this->translationService = $translation_service;
    $this->tempStoreFactory = $temp_store_factory;
    $this->lingotek = $lingotek;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->state = $state;
    $this->moduleHandler = $module_handler;
    $this->entityTypeId = $entity_type_id;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->formBulkActionManager = $form_actions_manager;
    $this->formBulkActionOptionsManager = $form_action_options_manager;
    $form_component_arguments = [
      'form_id' => $this->getFormId(),
      'entity_type_id' => $this->entityTypeId,
    ];
    $this->formFields = $form_field_manager->getApplicable($form_component_arguments);
    $this->formFilters = $form_filter_manager->getApplicable($form_component_arguments);
    $this->formBulkActions = $form_actions_manager->getApplicable($form_component_arguments);
    $this->formBulkActionOptions = $form_action_options_manager->getOptions();
    $this->formBulkActionExecutor = $form_bulk_action_executor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('lingotek'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('content_translation.manager'),
      $container->get('lingotek.content_translation'),
      $container->get('tempstore.private'),
      $container->get('state'),
      $container->get('module_handler'),
      \Drupal::routeMatch()->getParameter('entity_type_id'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.lingotek_form_field'),
      $container->get('plugin.manager.lingotek_form_filter'),
      $container->get('plugin.manager.lingotek_form_bulk_action'),
      $container->get('plugin.manager.lingotek_form_bulk_action_option'),
      $container->get('lingotek.form_bulk_action_executor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    // Add the filters if any.
    if ($filters = $this->getFilters()) {
      $form['filters'] = [
        '#type' => 'details',
        '#title' => $this->t('Filter'),
        '#open' => TRUE,
        '#weight' => 5,
        '#tree' => TRUE,
      ];

      foreach ($filters as $filter_id => $filter) {
        $form['filters'][$filter_id] = $filter;
      }

      // Filter actions
      $form['filters']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['clearfix']],
      ];
      $form['filters']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Filter'),
        '#submit' => ['::filterForm'],
      ];
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetFilterForm'],
      ];
    }

    // Add the bulk operations if any.
    $options = $this->getBulkOptions();
    if (!empty($options)) {
      $form['options'] = [
        '#type' => 'details',
        '#title' => $this->t('Bulk document management'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['container-inline']],
        '#weight' => 10,
      ];
      foreach ($options as $id => $component) {
        $form['options'][$id] = $component;
      }
    }

    // Add the headers.
    $headers = $this->getHeaders();

    // Get all the entities that need to be displayed.
    $entities = $this->getFilteredEntities();

    // Generate the rows based on those entities.
    $rows = [];
    if (!empty($entities)) {
      $rows = $this->getRows($entities);
    }

    $pager = $this->getPager();
    if (!empty($pager)) {
      $form['pager'] = [
        '#type' => 'pager',
        '#weight' => 50,
      ];
      $form['items_per_page'] = $pager;
    }

    $form['table'] = [
      '#header' => $headers,
      '#options' => $rows,
      '#empty' => $this->t('No content available'),
      '#type' => 'tableselect',
      '#weight' => 30,
    ];

    $form['#attached']['library'][] = 'lingotek/lingotek';
    $form['#attached']['library'][] = 'lingotek/lingotek.manage';
    return $form;
  }

  /**
   * Builds the table headers
   */
  protected function getHeaders() {
    if (is_null($this->headers)) {
      $this->headers = [];

      foreach ($this->formFields as $field_id => $field) {
        $this->headers[$field_id] = $field->getHeader($this->entityTypeId);
      }
    }

    return $this->headers;
  }

  /**
   * Load the entities corresponding with the given identifiers.
   *
   * @param string[] $values
   *   Array of values that identify the selected entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities.
   */
  abstract protected function getSelectedEntities($values);

  /**
   * Gets the key used for persisting filtering options in the temp storage.
   *
   * @return string
   *   Temp storage identifier where filters are persisted.
   */
  abstract protected function getTempStorageFilterKey();

  /**
   * Form submission handler for resetting the filters.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetFilterForm(array &$form, FormStateInterface $form_state) {
    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());
    $temp_store->delete('filters');
  }

  /**
   * Form submission handler for filtering.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function filterForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());
    $values = $form_state->getValue('filters') ?? [];
    $temp_store->set('filters', $values);
    // If we apply any filters, we need to go to the first page again.
    $form_state->setRedirect('<current>');
  }

  /**
   * Gets the bulk options form array structure.
   *
   * @return array
   *   A form array.
   */
  protected function getBulkOptions() {
    $element = [];
    $element['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $this->generateBulkOptions(),
    ];
    $options = $this->generateBulkActionOptions();

    $element['options'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $element['options'] = array_merge($element['options'], $options);

    $element['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
    ];
    return $element;
  }

  protected function generateBulkActionOptions() {
    $options = [];

    $optionActionsMap = [];

    foreach ($this->formBulkActions as $plugin) {
      $pluginOptions = $plugin->getOptions();
      array_walk($pluginOptions, function ($option_id) use ($plugin, &$optionActionsMap) {
        $optionActionsMap[$option_id][] = $plugin->getPluginId();
      });
    }

    foreach ($optionActionsMap as $option_id => $actions) {
      $this->formBulkActionOptions[$option_id]->registerBulkActions($actions);
      $options[$option_id] = $this->formBulkActionOptions[$option_id]->buildFormElement();
    }

    return $options;
  }

  /**
   * Gets the entities that needs to be displayed based on the current filters.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities
   */
  abstract protected function getFilteredEntities();

  /**
   * Gets the filters for rendering.
   *
   * @return array
   *   A form array.
   */
  abstract protected function getFilters();

  /**
   * Gets the rows for rendering based on the passed entity list.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entity_list
   *
   * @return array
   *   A render array.
   */
  abstract protected function getRows($entity_list);

  /**
   * Gets the pager.
   *
   * @return array
   *   A render array.
   */
  abstract protected function getPager();

  /**
   * Gets a row of fields for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A renderable array.
   */
  protected function getRow($entity) {
    foreach ($this->formFields as $field_id => $field) {
      $row[$field_id] = $field->getData($entity);
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operation = $form_state->getValue('operation');
    $options = $form_state->getValue('options');
    $values = array_keys(array_filter($form_state->getValue(['table'])));
    $entities = $this->getSelectedEntities($values);

    $result = $this->formBulkActionExecutor->execute($this->formBulkActions[$operation], $entities, $options, $this->formBulkActions['upload'] ?? NULL);
    if ($result) {
      $redirect = $this->formBulkActions[$operation]->getPluginDefinition()['redirect'] ?? NULL;
      if ($redirect) {
        if (strpos($redirect, 'entity:') === 0) {
          [, $redirect_template] = explode(':', $redirect);
          if (count($entities) > 0) {
            $entity = reset($entities);
            $form_state->setRedirectUrl(Url::fromUserInput($entity->getEntityType()
              ->getLinkTemplate($redirect_template), ['query' => $this->getDestinationWithQueryArray()]));
          }
        }
        else {
          $form_state->setRedirect($redirect, [], ['query' => $this->getDestinationWithQueryArray()]);
        }
      }
    }
    else {
      // Ensure selection is kept.
      $form_state->setRebuild();
    }
  }

  /**
   * Get the bulk operations for the management form.
   *
   * @return array
   *   Array with the bulk operations.
   */
  public function generateBulkOptions() {
    $operations = [];
    if ($this->formBulkActions) {
      foreach ($this->formBulkActions as $action_id => $plugin) {
        $parents = [];

        if ($group = $plugin->getGroup()) {
          $parents[] = (string) $group;
        }

        $parents[] = $action_id;
        NestedArray::setValue($operations, $parents, $plugin->getTitle());
      }
    }
    return $operations;
  }

  protected function getDestinationWithQueryArray() {
    return ['destination' => \Drupal::request()->getRequestUri()];
  }

}
