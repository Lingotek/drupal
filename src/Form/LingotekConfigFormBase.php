<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekConfigFormBase.
 */

namespace Drupal\lingotek\Form;

use Drupal\lingotek\LingotekInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
abstract class LingotekConfigFormBase extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekConfigFormBase object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The factory for configuration objects.
   */
  public function __construct(LingotekInterface $lingotek, ConfigFactoryInterface $config) {
    $this->L = $lingotek;
    parent::__construct($config);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('lingotek'),
      $container->get('config.factory')
    );
  }

  public function getEditableConfigNames() {
    return [
    ];
  }

}
