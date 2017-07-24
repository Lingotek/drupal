<?php

namespace Drupal\lingotek_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the lingotek service.
 */
class LingotekTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides lingotek class to mock communication with the server.
    $definition = $container->getDefinition('lingotek');
    $definition->setClass('Drupal\lingotek_test\LingotekFake');
  }

}
