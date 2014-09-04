<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsConnectForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsConnectForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekSettingsConnectForm object.
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
    return 'lingotek.connect_form';
  }

  /** * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('lingotek.settings');

    // build the redirecting link for authentication to Lingotek
    $host = $config->get('account.host');
    $auth_path = $config->get('account.authorize_path');
    $id = $config->get('account.default_client_id');
    $return_uri = url(current_path(), array('absolute' => TRUE)) . '?success=true';
    $login = $config->get('account.type');

    $form = parent::buildForm($form, $form_state);
    if (!isset($form['#attached'])) {
      $form['#attached'] = array();
    }
    if (!isset($form['#attached']['js'])) {
      $form['#attached']['js'] = array();
    }

    $form['#attached']['js'][] = drupal_get_path('module', 'lingotek') . '/js/connect.js';
    $form['intro'] = array(
      '#type' => 'markup',
      '#markup' => $this->t('Get started by clicking the button below to connect your Lingotek account to this Drupal site.'),
    );
    //$form['actions']['submit']['#value'] = $this->t('Connect Account');
    unset($form['actions']['submit']);

    $lingotek_connect_link = $host . '/' . $auth_path . '?client_id=' . $id . '&response_type=token&redirect_uri=' . urlencode($return_uri);

    $form['actions']['submit'][] = array(
      '#theme' => 'menu_local_action',
      '#link' => array(
        'title' => t('Connect to Lingotek'),
        'href' => $lingotek_connect_link,
        'localized_options' => array(
          'attributes' => array(
            'title' => t('Connect to Lingotek'),
            'class' => array('button--primary'),
          )
        )
      ),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // do nothing for now
  }
}
