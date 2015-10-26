<?php

/**
 * @file
 * Contains \Drupal\lingotek\Controller\LingotekControllerBase.
 */

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\LingotekInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\lingotek\LingotekSetupTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for handling all Lingotek-related routes.
 */
abstract class LingotekControllerBase extends ControllerBase {

  use LingotekSetupTrait;

  /**
   * A Symfony request instance
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * A router instance for controlling redirects.
   *
   * @var TBD
   */
  protected $router;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a LingotekControllerBase object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(Request $request, LingotekInterface $lingotek, FormBuilderInterface $form_builder, LoggerInterface $logger) {
    $this->request = $request;
    $this->lingotek = $lingotek;
    $this->formBuilder = $form_builder;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('lingotek'),
      $container->get('form_builder'),
      $container->get('logger.channel.lingotek')
    );
  }

  /**
   * Checks if site is connected to Lingotek.
   *
   * @return boolean TRUE if connected, FALSE otherwise.
   */
  public function connected() {
    $access_token = $this->request->query->get('access_token');
    if ($access_token) {
      $this->lingotek->set('access_token', $access_token);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Return a Lingotek form (convenience function)
   *
   * @param type $form_path
   */
  protected function getLingotekForm($local_form_path) {
    return $this->formBuilder->getForm('\\Drupal\\lingotek\\Form\\' . $local_form_path, $this->request);
  }

//  /**
//   * {@inheritdoc}
//   */
//  public function redirect($route_name, $route_parameters = array(), $status = 302) {
//    // TODO: initialize the route first, if it doesn't exist yet.
//    if ($this->router->getRouteCollection()->get($route_name)) {
//      return parent::redirect($route_name, $route_parameters, $status);
//    }
//  }
}
