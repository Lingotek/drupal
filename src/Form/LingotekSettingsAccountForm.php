<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsAccountForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsAccountForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekSettingsAccountForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactory $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.account_form';
  }

  /** * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('lingotek.settings');

    $form = parent::buildForm($form, $form_state);
    

    $form['account']['connected'] = array(
    );
    $form['account']['login_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Login'),
      '#description' => t('The login used to connect with the Lingotek service.'),
      '#value' => $config->get('account.login_id'),
      '#disabled' => TRUE,
    );
    $form['account']['access_token'] = array(
      '#type' => 'textfield',
      '#title' => t('Access Token'),
      '#description' => t('The token currently useed when communicating with the Lingotek service.'),
      '#value' => $config->get('account.access_token'),
      '#disabled' => TRUE,
    );

    // Provide new button to continue
    $form['actions']['submit']['#value'] = t('Next');

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Everything is saved in the previous step, so redirect to community form.
    $form_state->setRedirect('lingotek.setup_community');

    parent::submitForm($form, $form_state);
  }

}
