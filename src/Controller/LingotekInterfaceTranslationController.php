<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekInterfaceTranslationServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class LingotekInterfaceTranslationController extends LingotekControllerBase {

  /**
   * The Lingotek interface translation service.
   *
   * @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface
   */
  protected $lingotekInterfaceTranslation;

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
   * @param \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $lingotek_interface_translation
   *   The Lingotek interface translation service.
   */
  public function __construct(Request $request, ConfigFactoryInterface $config_factory, LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, FormBuilderInterface $form_builder, LoggerInterface $logger, LingotekInterfaceTranslationServiceInterface $lingotek_interface_translation) {
    parent::__construct($request, $config_factory, $lingotek, $language_locale_mapper, $form_builder, $logger);
    $this->lingotekInterfaceTranslation = $lingotek_interface_translation;
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
      $container->get('lingotek.interface_translation')
    );
  }

  public function upload(Request $request) {
    $component = $request->query->get('component');
    if ($component) {
      // It is not a config entity, but a config object.
      if ($this->lingotekInterfaceTranslation->getDocumentId($component)) {
        return $this->update($component);
      }
      else {
        try {
          if ($this->lingotekInterfaceTranslation->uploadDocument($component)) {
            $this->messenger()->addStatus($this->t('%label uploaded successfully', ['%label' => $component]));
          }
        }
        catch (LingotekDocumentArchivedException $exception) {
          $this->messenger()->addError($this->t('Document %label has been archived. Please upload again.',
            ['%label' => $component]));
        }
        catch (LingotekDocumentLockedException $exception) {
          $this->messenger()->addError($this->t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
            ['%label' => $component]));
        }
        catch (LingotekPaymentRequiredException $exception) {
          $this->messenger()->addError($this->t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
        }
        catch (LingotekApiException $e) {
          // Mark the document as failed.
          $this->messenger()->addError($this->t('The upload for %label failed. Please try again.',
            ['%label' => $component]));
        }
      }
    }
    return new RedirectResponse($request->getRequestUri());
  }

  public function update(Request $request) {
    $component = $request->query->get('component');
    if ($component) {
      try {
        if ($this->lingotekInterfaceTranslation->updateDocument($component)) {
          $this->messenger()->addStatus($this->t('%label has been updated.', ['%label' => $component]));
        }
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addError($this->t('Document %label has been archived. Please upload again.', ['%label' => $component]));
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError($this->t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
          ['%label' => $component]));
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError($this->t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t('%label update failed. Please try again.',
          ['%label' => $component]));
      }
    }
    return new RedirectResponse($request->getRequestUri());
  }

  public function checkUpload(Request $request) {
    $component = $request->query->get('component');
    if ($component) {
      try {
        if ($this->lingotekInterfaceTranslation->checkSourceStatus($component)) {
          $this->messenger()->addStatus($this->t('The import for %label is complete.', ['%label' => $component]));
        }
        else {
          $this->messenger()->addStatus($this->t('The import for %label is still pending.', ['%label' => $component]));
        }
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t('%label status check failed. Please try again.',
          ['%label' => $component]));
      }
    }
    return new RedirectResponse($request->getRequestUri());
  }

  public function requestTranslation(Request $request) {
    $component = $request->query->get('component');
    $locale = $request->query->get('locale');
    if ($component && $locale) {
      try {
        if ($this->lingotekInterfaceTranslation->addTarget($component, $locale)) {
          $this->messenger()->addStatus($this->t("Locale '@locale' was added as a translation target for %label.", ['@locale' => $locale, '%label' => $component]));
        }
        else {
          $this->messenger()->addWarning($this->t("There was a problem adding '@locale' as a translation target for %label.", ['@locale' => $locale, '%label' => $component]));
        }
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addError($this->t('Document %label has been archived. Please upload again.',
          ['%label' => $component]));
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError($this->t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
          ['%label' => $component]));
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError($this->t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t("Requesting '@locale' translation for %label failed. Please try again.",
          ['%label' => $component, '@locale' => $locale]));
      }
    }
    return new RedirectResponse($request->getRequestUri());
  }

  public function checkTranslation(Request $request) {
    $component = $request->query->get('component');
    $locale = $request->query->get('locale');
    if ($component && $locale) {
      $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->getId();
      try {
        if ($this->lingotekInterfaceTranslation->checkTargetStatus($component, $langcode) === Lingotek::STATUS_READY) {
          $this->messenger()->addStatus($this->t('The @locale translation for %label is ready for download.', ['@locale' => $locale, '%label' => $component]));
        }
        else {
          $this->messenger()->addStatus($this->t('The @locale translation for %label is still in progress.', ['@locale' => $locale, '%label' => $component]));
        }
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addError($this->t('Document %label has been archived. Please upload again.',
          ['%label' => $component]));
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError($this->t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
          ['%label' => $component]));
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError($this->t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t("The request for %label '@locale' translation status failed. Please try again.",
          ['%label' => $component, '@locale' => $locale]));
      }
    }
    return new RedirectResponse($request->getRequestUri());
  }

  public function download(Request $request) {
    $component = $request->query->get('component');
    $locale = $request->query->get('locale');
    if ($component && $locale) {
      try {
        if ($this->lingotekInterfaceTranslation->downloadDocument($component, $locale)) {
          $this->messenger()->addStatus($this->t('The translation of %label into @locale has been downloaded.', ['@locale' => $locale, '%label' => $component]));
        }
        else {
          $this->messenger()->addStatus($this->t("The '@locale' translation download for %label failed. Please try again.", ['@locale' => $locale, '%label' => $component]));
        }
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addError($this->t('Document %label has been archived. Please upload again.',
          ['%label' => $component]));
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError($this->t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
          ['%label' => $component]));
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError($this->t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
      }
      catch (LingotekApiException $e) {
        $this->messenger()->addError($this->t("The '@locale' translation download for %label failed. Please try again.",
          ['%label' => $component, '@locale' => $locale]));
      }
    }
    return new RedirectResponse($request->getRequestUri());
  }

}
