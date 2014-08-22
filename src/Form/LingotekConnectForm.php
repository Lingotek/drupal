<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSetupForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekConnectForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekConnectForm object.
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
    /*
     * SOMETHING FOR THE RETURN TRIP
    $config = $this->configFactory->get('lingotek.settings');
    $login = $config->get('account.login');
    $form['lingotek_login'] = array(
      '#type' => 'radio',
      '#title' => $this->t('Configure Lingotek World Text'),
      '#default_value' => $login,
      '#options' => array(
        'upper' => $this->t('UPPER'),
        'title' => $this->t('Title'),
      ),
      '#description' => $this->t('Choose the case of your "Lingotek, world!" message.'),
    );

    return parent::buildForm($form, $form_state);
    */

    $form = parent::buildForm($form, $form_state);
    if (!isset($form['#attached'])) {
      $form['#attached'] = array();
    }
    if (!isset($form['#attached']['js'])) {
      $form['#attached']['js'] = array();
    }
      $form['#attached']['js'][] = drupal_get_path('module', 'lingotek') . '/js/connect.js';


  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->get('lingotek.settings')
      ->set('account.login', $form_state['values']['lingotek_login'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}
