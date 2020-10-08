<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lingotek\LingotekInterfaceTranslationServiceInterface;
use Drupal\lingotek\LingotekSetupTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Class LingotekInterfaceTranslationForm.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekInterfaceTranslationForm extends FormBase {

  use StringTranslationTrait;

  use LingotekSetupTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The Lingotek interface translation service.
   *
   * @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface
   */
  protected $lingotekInterfaceTranslation;

  /**
   * Constructs a new LingotekInterfaceTranslationForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $lingotek_interface_translation
   *   The Lingotek interface translation service.
   */
  public function __construct(LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, LingotekInterfaceTranslationServiceInterface $lingotek_interface_translation) {
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->lingotekInterfaceTranslation = $lingotek_interface_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('lingotek.interface_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_interface_translation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    if (!$this->moduleHandler->moduleExists('potx')) {
      $form['missing_potx'] = [
        '#type' => 'markup',
        '#markup' => $this->t('The <a href=":potx">potx</a> module is required for interface translation with Lingotek', [':potx' => 'https://www.drupal.org/project/potx']),
      ];
      return $form;
    }

    $form['theme_vtab'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Themes'),
    ];
    $form['module_vtab'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Modules'),
    ];
    $form['profile_vtab'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Profiles'),
    ];

    $form['theme']['custom'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom themes'),
      '#open' => TRUE,
      '#group' => 'theme_vtab',
    ];
    $form['theme']['contrib'] = [
      '#type' => 'details',
      '#title' => $this->t('Contributed themes'),
      '#open' => FALSE,
      '#group' => 'theme_vtab',
    ];
    $form['theme']['core'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal Core themes'),
      '#open' => FALSE,
      '#group' => 'theme_vtab',
    ];

    $form['module']['custom'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom modules'),
      '#open' => TRUE,
      '#group' => 'module_vtab',
    ];
    $form['module']['contrib'] = [
      '#type' => 'details',
      '#title' => $this->t('Contributed modules'),
      '#open' => FALSE,
      '#group' => 'module_vtab',
    ];
    $form['module']['core'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal Core modules'),
      '#open' => FALSE,
      '#group' => 'module_vtab',
    ];

    $form['profile']['custom'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom profiles'),
      '#open' => TRUE,
      '#group' => 'profile_vtab',
    ];
    $form['profile']['contrib'] = [
      '#type' => 'details',
      '#title' => $this->t('Contributed profiles'),
      '#open' => FALSE,
      '#group' => 'profile_vtab',
    ];
    $form['profile']['core'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal Core profiles'),
      '#open' => FALSE,
      '#group' => 'profile_vtab',
    ];

    $tables = ['core', 'custom', 'contrib'];
    $componentTypes = ['theme', 'module', 'profile'];
    foreach ($componentTypes as $componentType) {
      foreach ($tables as $table) {
        $form[$componentType][$table]['table'] = [
          '#type' => 'table',
          '#header' => [
            'label' => $this->t('Label'),
            'machine_name' => $this->t('Machine name'),
            'source' => $this->t('Source'),
            'translations' => $this->t('Translations'),
          ],
          '#empty' => $this->t('No %component to translate in this category', ['%component' => $componentType]),
          '#rows' => [],
        ];
      }
    }

    $themes = $this->themeHandler->listInfo();
    foreach ($themes as $theme => $themeInfo) {
      $component = $themeInfo->getPath();

      $targetStatuses = $this->lingotekInterfaceTranslation->getTargetStatuses($component);
      unset($targetStatuses['EN']);
      $row = [
        'label' => $themeInfo->info['name'],
        'machine_name' => $themeInfo->getName(),
        'source' => [
          'data' => [
            '#type' => 'lingotek_source_status',
            '#ui_component' => $component,
            '#language' => $this->languageManager->getLanguage('en'),
            '#status' => $this->lingotekInterfaceTranslation->getSourceStatus($component),
          ],
        ],
        'translations' => [
          'data' => [
            '#type' => 'lingotek_target_statuses',
            '#ui_component' => $component,
            '#source_langcode' => 'en',
            '#statuses' => $this->lingotekInterfaceTranslation->getTargetStatuses($component),
          ],
        ],
      ];

      [$path_part1, $path_part2] = explode('/', $themeInfo->getPath());
      if ($path_part1 == 'core') {
        $form['theme']['core']['table']['#rows'][] = $row;
      }
      elseif ($path_part1 == 'contrib' || $path_part2 == 'contrib') {
        $form['theme']['contrib']['table']['#rows'][] = $row;
      }
      elseif ($path_part1 == 'custom' || $path_part2 == 'custom') {
        $form['theme']['custom']['table']['#rows'][] = $row;
      }
      else {
        $form['theme']['custom']['table']['#rows'][] = $row;
      }
    }
    if ($this->moduleHandler->moduleExists('cohesion')) {
      $template_location = COHESION_TEMPLATE_PATH;
      // Get real path to templates and extract relative path for interface translation.
      if ($wrapper = \Drupal::service('stream_wrapper_manager')
        ->getViaUri($template_location)) {
        $template_path = $wrapper->basePath() . '/cohesion/templates';
      }
      // This is a fake component.
      $component = $template_path;

      $row = [
        'label' => 'Cohesion templates',
        'machine_name' => 'cohesion_templates',
        'source' => [
          'data' => [
            '#type' => 'lingotek_source_status',
            '#ui_component' => $component,
            '#language' => $this->languageManager->getLanguage('en'),
            '#status' => $this->lingotekInterfaceTranslation->getSourceStatus($component),
          ],
        ],
        'translations' => [
          'data' => [
            '#type' => 'lingotek_target_statuses',
            '#ui_component' => $component,
            '#source_langcode' => 'en',
            '#statuses' => $this->lingotekInterfaceTranslation->getTargetStatuses($component),
          ],
        ],
      ];
      $form['theme']['custom']['table']['#rows'][] = $row;
    }

    $modules = $this->moduleHandler->getModuleList();
    foreach ($modules as $module => $moduleInfo) {
      $component = $moduleInfo->getPath();
      $type = $moduleInfo->getType();
      // We don't inject this service as its interface is not considered "stable".
      /** @var \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList */
      $moduleExtensionList = \Drupal::service('extension.list.module');
      $userFriendlyName = $moduleExtensionList->getName($module);

      $row = [
        'label' => $userFriendlyName,
        'machine_name' => $moduleInfo->getName(),
        'source' => [
          'data' => [
            '#type' => 'lingotek_source_status',
            '#ui_component' => $component,
            '#language' => $this->languageManager->getLanguage('en'),
            '#status' => $this->lingotekInterfaceTranslation->getSourceStatus($component),
          ],
        ],
        'translations' => [
          'data' => [
            '#type' => 'lingotek_target_statuses',
            '#ui_component' => $component,
            '#source_langcode' => 'en',
            '#statuses' => $this->lingotekInterfaceTranslation->getTargetStatuses($component),
          ],
        ],
      ];

      [$path_part1, $path_part2] = explode('/', $moduleInfo->getPath());
      if ($path_part1 == 'core') {
        $form[$type]['core']['table']['#rows'][] = $row;
      }
      elseif ($path_part1 == 'contrib' || $path_part2 == 'contrib') {
        $form[$type]['contrib']['table']['#rows'][] = $row;
      }
      elseif ($path_part1 == 'custom' || $path_part2 == 'custom') {
        $form[$type]['custom']['table']['#rows'][] = $row;
      }
      else {
        $form[$type]['custom']['table']['#rows'][] = $row;
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['clear_metadata'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Lingotek interface translation metadata'),
      '#button_type' => 'danger',
      '#submit' => [[$this, 'clearInterfaceMetadata']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function clearInterfaceMetadata(array &$form, FormStateInterface $form_state) {
    // Redirect to the confirmation form.
    $form_state->setRedirect('lingotek.manage_interface_translation.clear_metadata');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing for now, there's no form actually.
  }

}
