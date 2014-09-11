<?php

/**
 * @file
 * Contains \Drupal\lingotek\Controller\LingotekControllerBase.
 */

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\LingotekInterface;
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
   * A lingotek connector object
   *
   * @var \Drupal\lingotek\LingotekInterface
   */
  protected $L;

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
   */
  public function __construct(Request $request, LingotekInterface $lingotek, FormBuilderInterface $form_builder) {
    $this->request = $request;
    $this->L = $lingotek;
    $this->formBuilder = $form_builder;

    $this->checkSetup();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $request_stack = $container->get('request_stack');
    return new static(
        $request_stack->getCurrentRequest(), $container->get('lingotek'), $container->get('form_builder')
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
      $this->L->set('access_token', $access_token);
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
    $info = $this->L->get('account');
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
      //return $this->redirect('lingotek.setup_account');
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
