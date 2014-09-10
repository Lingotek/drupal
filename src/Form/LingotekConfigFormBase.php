<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekConfigFormBase.
 */

namespace Drupal\lingotek\Form;

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
abstract class LingotekConfigFormBase extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactory $config_factory, LingotekApiInterface $lingotek_api) {
    parent::__construct($config_factory);
    $this->api = $lingotek_api;
    $this->settings = $this->configFactory->get('lingotek.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('lingotek.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {

  }

  /** * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
