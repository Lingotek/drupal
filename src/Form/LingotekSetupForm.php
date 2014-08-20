<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSetupForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSetupForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekSetupForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\Context\ContextInterface $context
   *   The configuration context to use.
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
    return 'lingotek.setup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {

    $config = $this->config('lingotek.settings');
    $current_login_id = $config->get('account.login');
    $current_login_key = $config->get('account.token');
    $current_first_name = $config->get('account.first_name');
    $current_last_name = $config->get('account.last_name');
    $current_email = $config->get('account.email');
  
    $form['description'] = array(
      '#type' => 'item',
      '#title' => 'Create Account',
      '#description' => 'New to Lingotek?  Create a free account. <p>A Lingotek account is required to process your language translations.  <strong>All fields are required.</strong></p>'
    );
  
    $form['first_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#default_value' => $current_first_name,
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => TRUE,
    );
    $form['last_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#default_value' => $current_last_name,
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => TRUE,
    );
    $form['email'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email address'),
      '#default_value' => $current_email,
      '#size' => 40,
      '#maxlength' => 128,
      '#required' => TRUE,
    );
  
    $form['lingotek_button_spacer'] = array('#markup' => '<div>&nbsp;</div>');
  
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Next'), // Create Account
      '#button_type' => 'primary'
    );
    //$form['lingotek_back_button'] = lingotek_setup_link('admin/config/lingotek/account-settings', t('Enterprise Customers - Connect Here'));
    //$form['lingotek_support_footer'] = lingotek_support_footer();

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('lingotek.settings')
      ->set('case', $form_state['values']['lingotek_case'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
