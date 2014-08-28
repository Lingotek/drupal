<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSetupConnectForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSetupConnectForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekSetupConnectForm object.
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
    $login = $config->get('account.login');

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
      //'#markup' => $test_class->doSomething(),
    );
    //$form['actions']['submit']['#value'] = $this->t('Connect Account');
    unset($form['actions']['submit']);
    $form['actions']['output'][] = array(
      '#theme' => 'menu_local_action',
      '#link' => array(
        'title' => t('Connect to Lingotek'),
        'href' => 'https://cms.lingotek.com/',
        'localized_options' => array(
          'attributes' => array(
            'title' => t('Connect to Lingotek'),
          )
        )
      ),
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
