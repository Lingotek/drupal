<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProfileLingotekBulkActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct(LingotekConfigurationServiceInterface $lingotek_configuration) {
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * @{inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('lingotek.configuration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $definitions = parent::getDerivativeDefinitions($base_plugin_definition);
    if (empty($definitions)) {
      $plugin_derivatives = [];
      $profiles = $this->lingotekConfiguration->getProfileOptions();
      foreach ($profiles as $profile_id => $profile) {
        $plugin_derivatives[$profile_id] = [
            'profile' => $profile,
            'profile_id' => $profile_id,
          ] + $base_plugin_definition;
      }
      $this->derivatives = $plugin_derivatives;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
