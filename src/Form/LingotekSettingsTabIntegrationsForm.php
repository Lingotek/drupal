<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\lingotek\LingotekInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure integration modules settings for Lingotek.
 */
class LingotekSettingsTabIntegrationsForm extends LingotekConfigFormBase {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * The render cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $renderCache;

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekConfigFormBase object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $render_cache
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator.
   */
  public function __construct(LingotekInterface $lingotek, ConfigFactoryInterface $config, ModuleHandlerInterface $module_handler, RouteBuilderInterface $router_builder, CacheBackendInterface $render_cache, UrlGeneratorInterface $url_generator = NULL, LinkGeneratorInterface $link_generator = NULL) {
    parent::__construct($lingotek, $config, $url_generator, $link_generator);
    $this->moduleHandler = $module_handler;
    $this->routerBuilder = $router_builder;
    $this->renderCache = $render_cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('router.builder'),
      $container->get('cache.render'),
      $container->get('url_generator'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_integrations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('lingotek.settings');

    $form = parent::buildForm($form, $form_state);

    if ($this->moduleHandler->moduleExists('paragraphs')) {
      if (!isset($form['contrib'])) {
        $form['contrib']['#tree'] = TRUE;
      }
      $form['contrib']['paragraphs'] = [
        '#title' => $this->t('Paragraphs'),
        '#type' => 'details',
      ];
      $form['contrib']['paragraphs']['enable_bulk_management'] = [
        '#title' => $this->t('Enable paragraphs to be managed individually instead of embedded in their parent entity.'),
        '#type' => 'checkbox',
        '#default_value' => $config->get('preference.contrib.paragraphs.enable_bulk_management'),
      ];
    }
    $form['actions']['submit']['#value'] = $this->t('Save settings');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $needs_rebuild = FALSE;
    $needs_saving = FALSE;
    $config = $this->configFactory()->getEditable('lingotek.settings');
    $form_values = $form_state->getValues();
    if ($this->moduleHandler->moduleExists('paragraphs')) {
      $old_value = $config->get('preference.contrib.paragraphs.enable_bulk_management', FALSE);
      if ($old_value !== $form_values['contrib']['paragraphs']['enable_bulk_management']) {
        $config->set('preference.contrib.paragraphs.enable_bulk_management', boolval($form_values['contrib']['paragraphs']['enable_bulk_management']));
        $needs_saving = TRUE;
        $needs_rebuild = TRUE;
      }
    }
    if ($needs_saving) {
      $config->save();
    }
    if ($needs_rebuild) {
      $this->routerBuilder->rebuild();
      // We need to clear the render cache.
      $this->renderCache->deleteAll();
    }
    $form_state->setRedirect('lingotek.settings');
    parent::submitForm($form, $form_state);
  }

}
