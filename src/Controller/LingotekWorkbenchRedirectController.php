<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for redirecting to the Lingotek TMS workbench.
 *
 * @package Drupal\lingotek\Controller
 */
class LingotekWorkbenchRedirectController extends LingotekControllerBase {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a LingotekControllerBase object.
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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Request $request, ConfigFactoryInterface $config_factory, LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, FormBuilderInterface $form_builder, LoggerInterface $logger, TimeInterface $time = NULL) {
    parent::__construct($request, $config_factory, $lingotek, $language_locale_mapper, $form_builder, $logger);
    $this->time = $time;
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
      $container->get('datetime.time')
    );
  }

  /**
   * Redirects to the workbench of the given document and locale in the TMS.
   */
  public function redirectToWorkbench($doc_id, $locale) {
    // Get account settings to build workbench link.
    $account = $this->config('lingotek.settings')->get('account');
    // Generate the uri to the Lingotek Workbench.
    $uri = $this->generateWorkbenchUri($doc_id, $locale, $account);
    return new TrustedRedirectResponse(Url::fromUri($uri)->toString());
  }

  /**
   * Generates a workbench uri for this account given a document id and locale.
   *
   * @param string $document_id
   *   The document id.
   * @param string $locale
   *   Lingotek translation language.
   * @param array $account
   *   Array describing the account.
   *
   * @return string
   *   The uri of the workbench for this account for editing this translation.
   */
  protected function generateWorkbenchUri($document_id, $locale, $account) {
    $base_url = $account['host'];
    // https://{environment}/workbench/document/{uuid}/locale/{es-MX}
    $workbench_uri = $base_url . '/workbench/document/' . $document_id . '/locale/' . $locale;
    return $workbench_uri;
  }

}
