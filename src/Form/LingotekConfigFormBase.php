<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator.
   */
  public function __construct(LingotekInterface $lingotek, ConfigFactoryInterface $config, UrlGeneratorInterface $url_generator = NULL, LinkGeneratorInterface $link_generator = NULL) {
    parent::__construct($config);
    $this->lingotek = $lingotek;
    if (!$url_generator) {
      @trigger_error('The url_generator service must be passed to LingotekConfigFormBase::__construct, it is required before Lingotek 9.x-1.0.', E_USER_DEPRECATED);
      $url_generator = \Drupal::service('url_generator');
    }
    $this->urlGenerator = $url_generator;
    if (!$link_generator) {
      @trigger_error('The link_generator service must be passed to LingotekConfigFormBase::__construct, it is required before Lingotek 9.x-1.0.', E_USER_DEPRECATED);
      $link_generator = \Drupal::service('link_generator');
    }
    $this->linkGenerator = $link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek'),
      $container->get('config.factory'),
      $container->get('url_generator')
    );
  }

  public function getEditableConfigNames() {
    return [];
  }

}
