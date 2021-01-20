<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekFilterManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a common base class for Profile forms.
 */
class LingotekProfileFormBase extends EntityForm {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * The Lingotek Filter manager.
   *
   * @var \Drupal\lingotek\LingotekFilterManagerInterface
   */
  protected $lingotekFilterManager;

  /**
   * Constructs a LingotekProfileFormBase object.
   *
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LingotekFilterManagerInterface $lingotek_filter_manager
   *   The Lingotek Filter manager.
   */
  public function __construct(LingotekConfigurationServiceInterface $lingotek_configuration, LingotekFilterManagerInterface $lingotek_filter_manager) {
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekFilterManager = $lingotek_filter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.configuration'),
      $container->get('lingotek.filter_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_profile_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $this->entity;
    $form['id'] = [
      '#type' => 'machine_name',
      '#description' => $this->t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$profile->isNew(),
      '#default_value' => $profile->id(),
      '#machine_name' => [
        'exists' => '\Drupal\lingotek\Entity\LingotekProfile::load',
        'source' => ['label'],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => $this->t('The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".'),
      ],
    ];
    $form['label'] = [
      '#id' => 'label',
      '#type' => 'textfield',
      '#title' => $this->t('Profile Name'),
      '#required' => TRUE,
      '#default_value' => $profile->label(),
    ];
    $form['current_future_note'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Profile settings impacting all entities (new and existing)') . '</h3><hr />',
    ];
    $form['auto_upload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Upload Content Automatically'),
      '#description' => $this->t('When enabled, your Drupal content (including saved edits) will automatically be uploaded to Lingotek for translation. When disabled, you are required to manually upload your content by clicking the "Upload" button on the Translations tab.'),
      '#disabled' => $profile->isLocked(),
      '#default_value' => $profile->hasAutomaticUpload(),
    ];
    $form['auto_request'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Request Translations Automatically'),
      '#description' => $this->t('When enabled, translations will automatically be requested from Lingotek. When disabled, you are required to manually request translations by clicking the "Request translation" button on the Translations tab.'),
      '#disabled' => $profile->isLocked(),
      '#default_value' => $profile->hasAutomaticRequest(),
    ];
    $form['auto_download'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Download Translations Automatically'),
      '#description' => $this->t('When enabled, completed translations will automatically be downloaded from Lingotek. When disabled, you are required to manually download translations by clicking the "Download" button on the Translations tab.'),
      '#disabled' => $profile->isLocked(),
      '#default_value' => $profile->hasAutomaticDownload(),
    ];
    $form['auto_download_worker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use a Queue Worker to Download Translations'),
      '#description' => $this->t('When enabled, completed translations will automatically be queued for download. This worker can be processed multiple ways, e.g. using cron.'),
      '#disabled' => $profile->isLocked(),
      '#default_value' => $profile->hasAutomaticDownloadWorker(),
      '#states' => [
        'visible' => [
          ':input[name="auto_download"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $options = [
      'global_setting' => $this->t('Use global setting'),
      'yes' => $this->t('Yes'),
      'no' => $this->t('No'),
    ];

    $form['append_type_to_title'] = [
      '#type' => 'select',
      '#title' => $this->t('Append Entity Type to TMS Document Name'),
      '#description' => $this->t('When enabled, the content/entity type will be appended to the title when uploading to TMS. The source and target titles will remain unchanged.'),
      '#options' => $options,
      '#default_value' => $profile->getAppendContentTypeToTitle(),
      '#disabled' => $profile->isLocked(),
    ];

    $form['future_only_note'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Profile settings impacting only new nodes') . '</h3><hr />',
    ];

    $projects = $this->config('lingotek.settings')->get('account.resources.project');
    $default_project = $this->config('lingotek.settings')->get('default.project');
    $default_project_name = isset($projects[$default_project]) ? $projects[$default_project] : '';

    $form['project'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Project'),
      '#options' => ['default' => $this->t('Default (%project)', ['%project' => $default_project_name])] + $projects,
      '#description' => $this->t('The default Translation Memory Project where translations are saved.'),
      '#default_value' => $profile->getProject(),
    ];

    $workflows = $this->config('lingotek.settings')->get('account.resources.workflow');
    $default_workflow = $this->config('lingotek.settings')->get('default.workflow');

    if ($default_workflow === 'project_default') {
      $default_workflow_name = $this->t('Project Default');
    }
    else {
      $default_workflow_name = isset($workflows[$default_workflow]) ? $workflows[$default_workflow] : '';
    }

    if ($default_workflow === 'project_default') {
      // If the default workflow is project_default, then we hide both project_default and default
      $hideWorkflowOverrideConditions = [['value' => 'project_default'], ['value' => 'default']];
    }
    else {
      // If the default workflow is something other than project_default, we don't want to hide it
      // to ensure that the user can override it
      $hideWorkflowOverrideConditions = ['value' => 'project_default'];
    }

    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Workflow'),
      '#options' => [
        'project_default' => $this->t('Project Default'),
        'default' => $this->t('Default (%workflow)', ['%workflow' => $default_workflow_name]),
      ] + $workflows,
      '#description' => $this->t('The default Workflow which would be used for translations.'),
      '#default_value' => $profile->getWorkflow(),
    ];

    $vaults = $this->config('lingotek.settings')->get('account.resources.vault');
    $default_vault = $this->config('lingotek.settings')->get('default.vault');
    $default_vault_name = isset($vaults[$default_vault]) ? $vaults[$default_vault] : '';

    // We have two defaults: default vault, or the Project Workflow Template
    // Default vault.
    $form['vault'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Vault'),
      '#options' => [
          'default' => $this->t('Default (%vault)', ['%vault' => $default_vault_name]),
          'project_default' => $this->t('Use Project Workflow Template Default'),
        ] + $vaults,
      '#description' => $this->t('The default Translation Memory Vault where translations are saved.'),
      '#default_value' => $profile->getVault(),
    ];

    /** @var \Drupal\lingotek\LingotekFilterManagerInterface $filter_manager */
    $filters = $this->lingotekFilterManager->getLocallyAvailableFilters();
    $form['filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Filter'),
      '#options' => [
          'default' => $this->t('Use Global Default (%filter)', ['%filter' => $this->lingotekFilterManager->getDefaultFilterLabel()]),
          'project_default' => $this->t('Use Project Default'),
          'drupal_default' => $this->t('Use Drupal Default'),
      ] + $filters,
      '#description' => $this->t('The default FPRM Filter used when uploading or updating a document.'),
      '#default_value' => $profile->getFilter(),
    ];
    $form['subfilter'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Subfilter'),
      '#options' => [
          'default' => $this->t('Use Global Default (%filter)', ['%filter' => $this->lingotekFilterManager->getDefaultSubfilterLabel()]),
          'project_default' => $this->t('Use Project Default'),
          'drupal_default' => $this->t('Use Drupal Default'),
        ] + $filters,
      '#description' => $this->t('The default FPRM Subfilter used when uploading or updating a document.'),
      '#default_value' => $profile->getSubfilter(),
    ];

    // We add the overrides.
    $form['intelligence_metadata_overrides'] = [
      '#type' => 'details',
      '#title' => $this->t('Lingotek Intelligence Metadata overrides'),
      '#tree' => TRUE,
    ];

    // We include the Lingotek Intelligence Metadata form and alter it for
    // adapting it to profiles. We want to make this optional.
    $subform = \Drupal::formBuilder()->getForm(LingotekIntelligenceMetadataForm::class, $this->getRequest(), $this->getEntity());
    $form['intelligence_metadata_overrides']['override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override general Lingotek Intelligence metadata when using this profile'),
      '#description' => $this->t('When enabled, general Lingotek Intelligence metadata will be overridden by the options here when using this profile.'),
      '#default_value' => $profile->hasIntelligenceMetadataOverrides(),
    ];
    $form['intelligence_metadata_overrides']['form'] = $subform['intelligence_metadata'];
    $form['intelligence_metadata_overrides']['form']['#states'] = [
      'visible' => [
        ':input[name="intelligence_metadata_overrides[override]"]' => ['checked' => TRUE],
      ],
    ];
    unset($form['intelligence_metadata_overrides']['form']['actions']);

    $form['language_overrides'] = [
      '#type' => 'details',
      '#title' => $this->t('Target language specific settings'),
      '#tree' => TRUE,
    ];
    $languages = \Drupal::languageManager()->getLanguages();
    // Filter the disabled languages.
    $languages = array_filter($languages, function (LanguageInterface $language) {
      $configLanguage = ConfigurableLanguage::load($language->getId());
      return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
    });

    // We want to have them alphabetically.
    ksort($languages);
    foreach ($languages as $langcode => $language) {
      $form['language_overrides'][$langcode] = [
        'overrides' => [
          '#type' => 'select',
          '#title' => $language->getName() . ' (' . $language->getId() . ')',
          '#options' => [
            'default' => $this->t('Use default settings'),
            'custom' => $this->t('Use custom settings'),
            'disabled' => $this->t('Disabled'),
          ],
          '#default_value' => $profile->hasCustomSettingsForTarget($langcode) ? 'custom' : 'default',
        ],
        'custom' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => 'profile-language-overrides-container',
          ],
          '#states' => [
            'visible' => [
              ':input[name="language_overrides[' . $langcode . '][overrides]"]' => ['value' => 'custom'],
            ],
          ],
          'workflow' => [
            '#type' => 'select',
            '#title' => $this->t('Default Workflow'),
            '#options' => [
              'default' => $this->t('Default (%workflow)', ['%workflow' => $default_workflow_name]),
            ] + $workflows,
            '#description' => $this->t('The default Workflow which would be used for translations.'),
            '#default_value' => $profile->hasCustomSettingsForTarget($langcode) ? $profile->getWorkflowForTarget($langcode) : 'default',
            '#states' => [
              'invisible' => [
                ':input[name="workflow"]' => $hideWorkflowOverrideConditions,
              ],
            ],
          ],
          // If using overrides, we can never specify the document vault as this
          // cannot be empty, nor force to use the project template vault, as it
          // is unknown to us.
          'vault' => [
            '#type' => 'select',
            '#title' => $this->t('Target Save-To Vault'),
            '#options' => ['default' => $this->t('Default (%vault)', ['%vault' => $default_vault_name])] + $vaults,
            '#description' => $this->t("The Translation Memory Vault where this target's translations are saved."),
            '#default_value' => $profile->hasCustomSettingsForTarget($langcode) ? $profile->getVaultForTarget($langcode) : 'default',
          ],
          'auto_request' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Request Translations Automatically'),
            '#description' => $this->t('When enabled, translations will automatically be requested from Lingotek. When disabled, you are required to manually request translations by clicking the "Request translation" button on the Translations tab.'),
            '#disabled' => $profile->isLocked(),
            '#default_value' => $profile->hasAutomaticRequestForTarget($langcode),
          ],
          'auto_download' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Download Translations Automatically'),
            '#description' => $this->t('When enabled, completed translations will automatically be downloaded from Lingotek. When disabled, you are required to manually download translations by clicking the "Download" button on the Translations tab.'),
            '#disabled' => $profile->isLocked(),
            '#default_value' => $profile->hasAutomaticDownloadForTarget($langcode),
          ],
        ],
      ];
    }
    $form['#attached']['library'][] = 'lingotek/lingotek.settings';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\lingotek\Entity\LingotekProfile $profile */
    $profile = $this->getEntity();
    /** @var \Drupal\Core\Form\FormInterface $subform */
    $form_object = new LingotekIntelligenceMetadataForm();
    $input = $form_state->getUserInput();
    $inner_form_state = new FormState();
    $inner_form_state->addBuildInfo('args', [$this->getRequest(), $profile]);
    $inner_form_state->setFormObject($form_object);
    $inner_form_state->setUserInput($form_state->getUserInput());
    $inner_form_state->setValue('intelligence_metadata', $input['intelligence_metadata']);

    $subform = [];
    $form_object->submitForm($subform, $inner_form_state);

    $profile->setIntelligenceMetadataOverrides($form_state->getValue(['intelligence_metadata_overrides', 'override']));

    parent::save($form, $form_state);
  }

}
