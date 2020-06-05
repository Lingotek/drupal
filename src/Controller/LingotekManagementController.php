<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class LingotekManagementController extends LingotekControllerBase {

  /**
   * Constructs a LingotekManagementController object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The lingotek service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   */
  public function __construct(Request $request, ConfigFactoryInterface $config_factory, LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, FormBuilderInterface $form_builder, LoggerInterface $logger, UrlGeneratorInterface $url_generator) {
    parent::__construct($request, $config_factory, $lingotek, $language_locale_mapper, $form_builder, $logger);
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory'),
      $container->get('lingotek'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('form_builder'),
      $container->get('logger.channel.lingotek'),
      $container->get('url_generator')
    );
  }

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $entity_types = \Drupal::service('lingotek.configuration')->getEnabledEntityTypes();
    $entity_type_id = NULL;
    if (!empty($entity_types)) {
      // Prioritize node as main content type.
      if (array_key_exists('node', $entity_types)) {
        $entity_type_id = 'node';
      }
      else {
        $entity_type_keys = array_keys($entity_types);
        $entity_type_id = reset($entity_type_keys);
      }
    }
    if ($entity_type_id) {
      return $this->redirect("lingotek.manage.$entity_type_id");
    }

    $build['enable_content_translation']['#markup'] =
      $this->t('You need to enable content translation first. You can enable translation for the desired content entities on the <a href=":translation-entity">Content language</a> page.',
        [':translation-entity' => $this->urlGenerator->generateFromRoute('language.content_settings_page')]) . '<br/>';
    $build['enable_lingotek']['#markup'] =
      $this->t('Then you need to configure how you want to translate your content with Lingotek. Enable translation for the desired content entities on the <a href=":lingotek-translation-entity">Lingotek settings</a> page.',
        [':lingotek-translation-entity' => $this->urlGenerator->generateFromRoute('lingotek.settings')]);

    return $build;
  }

}
