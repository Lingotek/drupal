<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\lingotek\LingotekInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tab for running Lingotek utilities in the settings page.
 */
class LingotekSettingsTabUtilitiesForm extends LingotekConfigFormBase {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The lingotek service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder service.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator.
   */
  public function __construct(LingotekInterface $lingotek, ConfigFactoryInterface $config, StateInterface $state, RouteBuilderInterface $route_builder, UrlGeneratorInterface $url_generator = NULL, LinkGeneratorInterface $link_generator = NULL) {
    parent::__construct($lingotek, $config, $url_generator, $link_generator);
    $this->state = $state;
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek'),
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('router.builder'),
      $container->get('url_generator'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_utilities_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['utilities'] = [
      '#type' => 'details',
      '#title' => $this->t('Utilities'),
    ];

    $lingotek_table = [
      '#type' => 'table',
      '#empty' => $this->t('No Entries'),
    ];

    // Refresh resources via API row
    $api_refresh_row = [];
    $api_refresh_row['refresh_description'] = [
      '#markup' => '<h5>' . $this->t('Refresh Project, Workflow, Vault, and Filter Information') . '</h5>' . '<p>' . $this->t('This module locally caches the available projects, workflows, vaults, and filters. Use this utility whenever you need to pull down names for any newly created projects, workflows, vaults, or filters from the Lingotek Translation Management System.') . '</p>',
    ];
    $api_refresh_row['actions']['refresh_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
      '#button_type' => 'primary',
      '#submit' => ['::refreshResources'],
    ];

    // Update Callback URL row
    $callback_url = $this->configFactory()->get('lingotek.settings')->get('account.callback_url');
    $update_callback_url_row = [];
    $update_callback_url_row['update_description'] = [
      '#markup' => '<h5>' . $this->t('Update Notification Callback URL') . '</h5>' . '<p>' . $this->t('Update the notification callback URL. This can be run whenever your site is moved (e.g., domain name change or sub-directory re-location) or whenever you would like your security token re-generated.') . '</p>' . $this->t('<b>Current notification callback URL:</b> %callback_url', ['%callback_url' => $callback_url]),
    ];
    $update_callback_url_row['actions']['update_url'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update URL'),
      '#button_type' => 'primary',
      '#submit' => ['::updateCallbackUrl'],
    ];

    // Disassociate All Translations row
    $disassociate_row = [];
    $disassociate_row['disassociate_description'] = [
      '#markup' => '<h5>' . $this->t('Disassociate All Translations (use with caution)') . '</h5>' . '<p>' . $this->t('Should only be used to change the Lingotek project or TM vault associated with the node’s translation. Option to disassociate node translations on Lingotek’s servers from the copies downloaded to Drupal. Additional translation using Lingotek will require re-uploading the node’s content to restart the translation process.') . '</p>',
    ];

    $disassociate_row['actions']['#type'] = 'actions';
    $disassociate_row['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Disassociate'),
      '#submit' => ['::disassociateAllTranslations'],
      '#attributes' => [
        'class' => ['button', 'button--danger'],
      ],
    ];

    $debug_enabled = $this->state->get('lingotek.enable_debug_utilities', FALSE);
    $enable_debug_utilities_row = [];
    $enable_debug_utilities_row['enable_debug_utilities_description'] = [
      '#markup' => '<h5>' . $this->t('Debug utilities') . '</h5>' . '<p>' . $this->t('Should only be used to debug Lingotek') . '</p>',
    ];
    $enable_debug_utilities_row['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $debug_enabled ? $this->t('Disable debug operations') : $this->t('Enable debug operations'),
      '#button_type' => 'primary',
      '#submit' => ['::switchDebugUtilities'],
    ];

    $lingotek_table['api_refresh'] = $api_refresh_row;
    $lingotek_table['update_url'] = $update_callback_url_row;
    $lingotek_table['disassociate'] = $disassociate_row;
    $lingotek_table['enable_debug_utilities'] = $enable_debug_utilities_row;

    $form['utilities']['lingotek_table'] = $lingotek_table;

    return $form;
  }

  public function switchDebugUtilities() {
    $value = $this->state->get('lingotek.enable_debug_utilities', FALSE);
    $this->state->set('lingotek.enable_debug_utilities', !$value);
    $this->routeBuilder->rebuild();
    $this->messenger()->addStatus($this->t('Debug utilities has been %enabled.', ['%enabled' => !$value ? $this->t('enabled') : $this->t('disabled')]));
  }

  /**
   * Submit handler for refreshing the resources: projects, workflows, vaults,
   * and filters.
   */
  public function refreshResources() {
    $resources = $this->lingotek->getResources(TRUE);
    $this->messenger()->addStatus($this->t('Project, workflow, vault, and filter information have been refreshed.'));
  }

  public function disassociateAllTranslations(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('lingotek.confirm_disassociate');
  }

  public function updateCallbackUrl() {
    $new_callback_url = \Drupal::urlGenerator()->generateFromRoute('lingotek.notify', [], ['absolute' => TRUE]);
    $config = $this->configFactory()->get('lingotek.settings');
    $configEditable = $this->configFactory()->getEditable('lingotek.settings');
    $configEditable->set('account.callback_url', $new_callback_url);
    $configEditable->save();

    $defaultProject = $config->get('default.project');
    $new_response = $this->lingotek->setProjectCallBackUrl($defaultProject, $new_callback_url);

    if ($new_response) {
      $this->messenger()->addStatus($this->t('The callback URL has been updated.'));
    }
  }

}
