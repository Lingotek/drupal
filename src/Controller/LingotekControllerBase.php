<?php

/**
 * @file
 * Contains \Drupal\lingotek\Controller\LingotekControllerBase.
 */

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for handling all Lingotek-related routes.
 */
abstract class LingotekControllerBase extends ControllerBase {

  /**
   * A Symfony request instance
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * A lingotek api instance
   *
   * @var \Drupal\lingotek\Remote\LingotekApiInterface
   */
  protected $api;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * A config instance for lingotek.settings.
   *
   * @var TBD
   */
  protected $settings;

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
   */
  public function __construct(Request $request, LingotekApiInterface $lingotek_api, FormBuilderInterface $form_builder) {
    $this->request = $request;
    $this->api = $lingotek_api;
    $this->formBuilder = $form_builder;
    $this->settings = $this->config('lingotek.settings');

    $this->checkSetup();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $request_stack = $container->get('request_stack');
    return new static(
        $request_stack->getCurrentRequest(), $container->get('lingotek.api'), $container->get('form_builder')
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
      $this->settings->set('access_token', $access_token)->save();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Checks if Lingotek module is completely set up.
   *
   * @return boolean TRUE if connected, FALSE otherwise.
   */
  public function setupCompleted() {
    $info = $this->settings->get('account');
    if (!empty($info['access_token']) && !empty($info['login_id'])) {
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

  /**
   * Verify the Lingotek Translation module has been properly initialized.
   */
  protected function checkSetup() {
    if (!$this->setupCompleted()) {
      return $this->redirect('lingotek.setup_connect');
    }
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
