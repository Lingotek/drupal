<?php

/**
 * @file
 * Contains \Drupal\lingotek\Controller\LingotekControllerBase.
 */

namespace Drupal\lingotek\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for handling all Lingotek-related routes.
 */
abstract class LingotekControllerBase extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Lingotek module-specific settings
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $settings;

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
  public function __construct(Request $request, FormBuilderInterface $form_builder, LoggerInterface $logger) {
    $this->request = $request;
    $this->formBuilder = $form_builder;
    $this->logger = $logger;

    $this->checkSetup();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $request_stack = $container->get('request_stack');
    return new static(
      $request_stack->getCurrentRequest(),
      $container->get('form_builder'),
      $container->get('logger.factory')->get('lingotek')
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
      $this->config('lingotek.settings')->set('access_token', $access_token);
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

}
