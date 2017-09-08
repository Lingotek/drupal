<?php

namespace Drupal\frozenintime;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the time service.
 */
class FrozenintimeServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides language_manager class to test domain language negotiation.
    $definition = $container->getDefinition('datetime.time');
    $definition->setClass('Drupal\frozenintime\FrozenTime');
  }

}
