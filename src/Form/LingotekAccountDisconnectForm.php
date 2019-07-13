<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to disconnect from Lingotek.
 */
class LingotekAccountDisconnectForm extends ConfirmFormBase {

  /**
   * A lingotek connector object
   *
   * @var \Drupal\lingotek\LingotekInterface
   */
  protected $lingotek;

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekAccountDisconnect object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(LingotekInterface $lingotek, ConfigFactoryInterface $config_factory) {
    $this->lingotek = $lingotek;
    $this->setConfigFactory($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_account_disconnect';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disconnect from Lingotek?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Disconnect');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('lingotek.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('lingotek.settings');
    $config->set('account.access_token', NULL);
    $config->set('account.login_id', NULL);
    $config->set('account.callback_url', NULL);
    $config->save();

    $this->logger('lingotek')->notice('Account disconnected from Lingotek.');
    $this->messenger()->addStatus($this->t('You were disconnected from Lingotek.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
