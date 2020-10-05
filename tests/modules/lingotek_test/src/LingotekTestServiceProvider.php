<?php

namespace Drupal\lingotek_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

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
    $definition->setClass('Drupal\lingotek_test\LingotekFake')
      ->setArguments([
        new Reference('lingotek.api'),
        new Reference('lingotek.language_locale_mapper'),
        new Reference('config.factory'),
      ]);
    $definition = $container->getDefinition('config.factory');
    $definition->setClass('Drupal\lingotek_test\LingotekFakeConfigFactory');
  }

}
