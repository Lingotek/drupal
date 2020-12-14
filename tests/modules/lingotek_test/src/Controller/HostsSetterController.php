<?php

namespace Drupal\lingotek_test\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for pointing up the host and sandbox in config to the local site.
 *
 * This is needed as workaround, as we need to reference the local site from
 * configuration but it isn't possible from yaml files or without a valid HTTP
 * request.
 *
 * @package Drupal\lingotek_test\Controller
 */
class HostsSetterController extends ControllerBase {

  /**
   * Constructs the HostsSetterController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
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
   * Helper method for setting up a valid host for testing.
   *
   * Required for the workbench links.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with the current basepath and a success message.
   */
  public function setHosts(Request $request) {
    $basepath = $request->getSchemeAndHttpHost();

    $config = $this->configFactory->getEditable('lingotek.settings')
      ->set('account.host', $basepath);
    $config->save();

    return new JsonResponse(['message' => 'Success setting host to ' . $basepath, 'basepath' => $basepath]);
  }

}
