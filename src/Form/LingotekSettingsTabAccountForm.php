<?php

namespace Drupal\lingotek\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\LingotekFilterManagerInterface;
use Drupal\lingotek\LingotekInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabAccountForm extends LingotekConfigFormBase {

  /**
   * The Lingotek Filter manager.
   *
   * @var \Drupal\lingotek\LingotekFilterManagerInterface
   */
  protected $lingotekFilterManager;

  /**
   * Constructs a LingotekSettingsTabAccountForm object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The Lingotek service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   * @param \Drupal\lingotek\LingotekFilterManagerInterface $lingotek_filter_manager
   *   The Lingotek Filter manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator.
   */
  public function __construct(LingotekInterface $lingotek, ConfigFactoryInterface $config, LingotekFilterManagerInterface $lingotek_filter_manager, UrlGeneratorInterface $url_generator = NULL, LinkGeneratorInterface $link_generator = NULL) {
    parent::__construct($lingotek, $config, $url_generator, $link_generator);
    $this->lingotekFilterManager = $lingotek_filter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek'),
      $container->get('config.factory'),
      $container->get('lingotek.filter_manager'),
      $container->get('url_generator'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_account_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lingotek.settings');
    $isEnterprise = $this->t('Yes');
    $connectionStatus = $this->t('Inactive');

    if ($config->get('account.plan_type') == 'basic') {
      $isEnterprise = $this->t('No');
    }

    try {
      if ($this->lingotek->getAccountInfo()) {
        $connectionStatus = $this->t('Active');
      }
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addWarning($this->t('There was a problem checking your account status.'));
    }

    $statusRow = [
      ['#markup' => $this->t('Status:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $connectionStatus],
      ['#markup' => ''],
    ];
    $planRow = [
      ['#markup' => $this->t('Enterprise:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $isEnterprise],
      ['#markup' => ''],
    ];
    $activationRow = [
      ['#markup' => $this->t('Activation Name:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $config->get('account.login_id')],
      [],
    ];
    $tokenRow = [
      ['#markup' => $this->t('Access Token:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $config->get('account.access_token')],
      ['#markup' => ''],
    ];

    $resources = $this->lingotek->getResources();

    $default_community = $config->get('default.community');
    $default_community_name = isset($resources['community'][$default_community]) ? $resources['community'][$default_community] : '';
    $communityRow = [
      ['#markup' => $this->t('Community:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => new FormattableMarkup('@name (@id)', ['@name' => $default_community_name, '@id' => $default_community])],
      ['#markup' => $this->linkGenerator->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $default_workflow = $config->get('default.workflow');

    if ($default_workflow === 'project_default') {
      $default_workflow_name = $this->t('Project Default');
    }
    else {
      $default_workflow_name = isset($resources['workflow'][$default_workflow]) ? $resources['workflow'][$default_workflow] : '';
    }

    $workflowRow = [
      ['#markup' => $this->t('Default Workflow:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => new FormattableMarkup('@name (@id)', ['@name' => $default_workflow_name, '@id' => $default_workflow])],
      ['#markup' => $this->linkGenerator->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $default_project = $config->get('default.project');
    $default_project_name = isset($resources['project'][$default_project]) ? $resources['project'][$default_project] : '';
    $projectRow = [
      ['#markup' => $this->t('Default Project:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => new FormattableMarkup('@name (@id)', ['@name' => $default_project_name, '@id' => $default_project])],
      ['#markup' => $this->linkGenerator->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $filters = $this->lingotekFilterManager->getLocallyAvailableFilters();
    if ($filters > 0) {
      $default_filter = $this->lingotekFilterManager->getDefaultFilter();
      $default_filter_label = $this->lingotekFilterManager->getDefaultFilterLabel();
      $default_subfilter = $this->lingotekFilterManager->getDefaultSubfilter();
      $default_subfilter_label = $this->lingotekFilterManager->getDefaultSubfilterLabel();
      $filterRow = [
        ['#markup' => $this->t('Default Filter:'), '#prefix' => '<b>', '#suffix' => '</b>'],
        ['#markup' => new FormattableMarkup('@name (@id)', ['@name' => $default_filter_label, '@id' => $default_filter])],
        ['#markup' => $this->linkGenerator->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
      ];
      $subfilterRow = [
        ['#markup' => $this->t('Default Subfilter:'), '#prefix' => '<b>', '#suffix' => '</b>'],
        ['#markup' => new FormattableMarkup('@name (@id)', ['@name' => $default_subfilter_label, '@id' => $default_subfilter])],
        ['#markup' => $this->linkGenerator->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
      ];
    }

    $default_vault = $config->get('default.vault');
    $default_vault_name = isset($resources['vault'][$default_vault]) ? $resources['vault'][$default_vault] : '';

    $vaultRow = [
      ['#markup' => $this->t('Default Vault:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $default_vault ? new FormattableMarkup('@name (@id)', ['@name' => $default_vault_name, '@id' => $default_vault]) : ''],
      ['#markup' => $this->linkGenerator->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $tmsRow = [
      ['#markup' => $this->t('Lingotek TMS Server:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $config->get('account.host')],
      ['#markup' => ''],
    ];
    $gmcRow = [
      ['#markup' => $this->t('Lingotek GMC Server:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => 'https://gmc.lingotek.com'],
      ['#markup' => ''],
    ];

    $accountTable = [
      '#type' => 'table',
      '#empty' => $this->t('No Entries'),
    ];

    $accountTable['status_row'] = $statusRow;
    $accountTable['plan_row'] = $planRow;
    $accountTable['activation_row'] = $activationRow;
    $accountTable['token_row'] = $tokenRow;
    $accountTable['community_row'] = $communityRow;
    $accountTable['workflow_row'] = $workflowRow;
    $accountTable['project_row'] = $projectRow;
    $accountTable['vault_row'] = $vaultRow;
    $accountTable['filter_row'] = $filterRow;
    $accountTable['subfilter_row'] = $subfilterRow;
    $accountTable['tms_row'] = $tmsRow;
    $accountTable['gmc_row'] = $gmcRow;

    $form['account'] = [
      '#type' => 'details',
      '#title' => $this->t('Account'),
    ];

    $form['account']['account_table'] = $accountTable;
    $form['account']['actions']['#type'] = 'actions';
    $form['account']['actions']['disconnect'] = [
      '#type' => 'submit',
      '#value' => $this->t('Disconnect'),
      '#button_type' => 'danger',
      '#submit' => [[$this, 'disconnect']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function disconnect(array &$form, FormStateInterface $form_state) {
    // Redirect to the confirmation form.
    $form_state->setRedirect('lingotek.account_disconnect');
  }

}
