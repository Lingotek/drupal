<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\State\StateInterface;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
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
   * @param \Drupal\Core\State\StateInterface
   *   The state key/value store.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder service.
   */
  public function __construct(LingotekInterface $lingotek, ConfigFactoryInterface $config, StateInterface $state, RouteBuilderInterface $route_builder) {
    parent::__construct($lingotek, $config);
    $this->state = $state;
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('lingotek'),
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('router.builder')
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
    
    $form['utilities'] = array(
      '#type' => 'details',
      '#title' => $this->t('Utilities'),
    );

    $lingotek_table = array(
      '#type' => 'table',
      '#empty' => $this->t('No Entries'),
    );

    // Refresh resources via API row
    $api_refresh_row = array();
    $api_refresh_row['refresh_description'] = array(
      '#markup' => '<H5>' . $this->t('Refresh Project, Workflow, and Vault Information') . '</H5>' . '<p>' . $this->t('This module locally caches the available projects, workflows, and vaults. Use this utility whenever you need to pull down names for any newly created projects, workflows, or vaults from the Lingotek Translation Management System.') . '</p>',
    );
    $api_refresh_row['actions']['refresh_button'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
      '#button_type' => 'primary',
      '#submit' => array('::refreshResources'),
    );

    // Update Callback URL row
    $update_callback_url_row = array();
    $update_callback_url_row['update_description'] = array(
      '#markup' => '<H5>' . $this->t('Update Notification Callback URL') . '</H5>' . '<p>' . $this->t('Update the notification callback URL. This can be run whenever your site is moved (e.g., domain name change or sub-directory re-location) or whenever you would like your security token re-generated.') . '</p><b>' . $this->t('Current notification callback URL: ' . '</b>' . $this->t('<i>' . $this->lingotek->get('account.callback_url') . '</i>')),
    );
    $update_callback_url_row['actions']['update_url'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Update URL'),
      '#button_type' => 'primary',
      '#submit' => array('::updateCallbackUrl'),
    );

    // Disassociate All Translations row
    $disassociate_row = array();
    $disassociate_row['disassociate_description'] = array(
      '#markup' => '<H5>' . $this->t('Disassociate All Translations (use with caution)') . '</H5>' . '<p>' . $this->t('Should only be used to change the Lingotek project or TM vault associated with the node’s translation. Option to disassociate node translations on Lingotek’s servers from the copies downloaded to Drupal. Additional translation using Lingotek will require re-uploading the node’s content to restart the translation process.') . '</p>',
    );
    
    $disassociate_row['actions']['#type'] = 'actions';
    $disassociate_row['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Disassociate'),
      '#submit' => array('::disassociateAllTranslations'),
      '#attributes' => array(
          'class' => array('button', 'button--danger'),
      ),
    );

    $debug_enabled = $this->state->get('lingotek.enable_debug_utilities', FALSE);
    $enable_debug_utilities_row = [];
    $enable_debug_utilities_row['enable_debug_utilities_description'] = [
      '#markup' => '<H5>' . $this->t('Debug utilities') . '</H5>' . '<p>' . $this->t('Should only be used to debug Lingotek') . '</p>',
    ];
    $enable_debug_utilities_row['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $debug_enabled ? $this->t('Disable debug operations') : $this->t('Enable debug operations'),
      '#button_type' => 'primary',
      '#submit' => array('::switchDebugUtilities'),
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
    drupal_set_message($this->t('Debug utilities has been %enabled.', ['%enabled' => !$value ? $this->t('enabled') : $this->t('disabled')]));
  }

  public function refreshResources() {
    $resources = $this->lingotek->getResources(TRUE);
    $this->lingotek->set('account.resources', $resources);
    drupal_set_message($this->t('Project, workflow, and vault information have been refreshed.'));
  }

  /**
   * Disassociate all content and config translations.
   */
  public function disassociateAllTranslations() {
    $error = FALSE;

    $error &= $this->disassociateAllContentTranslations();
    $error &= $this->disassociateAllConfigTranslations();

    if ($error) {
      drupal_set_message($this->t('Some translations may have been disassociated, but some failed.'), 'warning');
    }
    else {
      drupal_set_message($this->t('All translations have been disassociated.'));
    }
  }

  /**
   * Disassociate all config translations.
   */
  protected function disassociateAllConfigTranslations() {
    $error = FALSE;
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');

    /** @var LingotekConfigMetadata[] $all_config_metadata */
    $all_config_metadata = LingotekConfigMetadata::loadMultiple();
    foreach ($all_config_metadata as $config_metadata) {
      try {
        $mapper = $config_metadata->getConfigMapper();
        if ($mapper instanceof ConfigEntityMapper) {
          $entity = $mapper->getEntity();
          $translation_service->deleteMetadata($entity);
        }
        else {
          $translation_service->deleteConfigMetadata($mapper->getPluginId());
        }
      }
      catch (LingotekApiException $exception) {
        $error = TRUE;
        drupal_set_message(t('The deletion of %title failed. Please try again.', array('%title' => $dependency_name)), 'error');
      }
    }
    return $error;
  }

  /**
   * Disassociate all content translations.
   */
  protected function disassociateAllContentTranslations() {
    $error = FALSE;
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $doc_ids = $translation_service->getAllLocalDocumentIds();
    foreach ($doc_ids as $doc_id) {
      $entity = $translation_service->loadByDocumentId($doc_id);
      if ($entity === NULL) {
        drupal_set_message(t('There is no entity in Drupal corresponding to the Lingotek document @doc_id. The record for this document has been removed from Drupal.', ['@doc_id' => $doc_id]), 'warning');
      }
      else {
        try {
          $translation_service->deleteMetadata($entity);
        }
        catch (LingotekApiException $exception) {
          $error = TRUE;
          drupal_set_message(t('The deletion of @entity_type %title failed. Please try again.', array('@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label())), 'error');
        }
      }
    }
    if ($error) {
      drupal_set_message($this->t('Some translations may have been disassociated, but some failed.'), 'warning');
    }
    else {
      drupal_set_message($this->t('All translations have been disassociated.'));
    }
    return $error;
  }

  public function updateCallbackUrl() {
    $new_callback_url = \Drupal::urlGenerator()->generateFromRoute('lingotek.notify', [], ['absolute' => TRUE]);
    $this->lingotek->set('account.callback_url', $new_callback_url);
    $new_response = $this->lingotek->setProjectCallBackUrl($this->lingotek->get('default.project'), $new_callback_url);
    
    if ($new_response) {
      drupal_set_message($this->t('The callback URL has been updated.'));
    }
  }

}
