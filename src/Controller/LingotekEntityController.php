<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class LingotekEntityController extends LingotekControllerBase {

  protected $translations_link;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a LingotekEntityController object.
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
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(Request $request, ConfigFactoryInterface $config_factory, LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, FormBuilderInterface $form_builder, LoggerInterface $logger, LingotekConfigurationServiceInterface $lingotek_configuration = NULL, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($request, $config_factory, $lingotek, $language_locale_mapper, $form_builder, $logger);
    if (!$lingotek_configuration) {
      @trigger_error('The lingotek.configuration service must be passed to LingotekEntityController::__construct, it is included in lingotek:3.2.0 and required for lingotek:4.0.0.', E_USER_DEPRECATED);
      $lingotek_configuration = \Drupal::service('lingotek.configuration');
    }
    $this->lingotekConfiguration = $lingotek_configuration;
    if (!$entity_type_bundle_info) {
      @trigger_error('The entity_type.bundle.info service must be passed to LingotekEntityController::__construct, it is included in lingotek:3.2.0 and required for lingotek:4.0.0.', E_USER_DEPRECATED);
      $entity_type_bundle_info = \Drupal::service('entity_type.bundle.info');
    }
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
      $container->get('lingotek.configuration'),
      $container->get('entity_type.bundle.info')
    );
  }

  public function checkUpload($doc_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $entity = $translation_service->loadByDocumentId($doc_id);
    if (!$entity) {
      // TODO: log warning
      return $this->translationsPageRedirect($entity);
    }
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot check upload for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return $this->translationsPageRedirect($entity);
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot check upload for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return $this->translationsPageRedirect($entity);
    }
    try {
      if ($translation_service->checkSourceStatus($entity)) {
        $this->messenger()->addStatus(t('The import for @entity_type %title is complete.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      else {
        $this->messenger()->addStatus(t('The import for @entity_type %title is still pending.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The check for @entity_type status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }

    return $this->translationsPageRedirect($entity);
  }

  public function checkTarget($doc_id, $locale) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $entity = $translation_service->loadByDocumentId($doc_id);
    if (!$entity) {
      // TODO: log warning
      return $this->translationsPageRedirect($entity);
    }
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot check target for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return $this->translationsPageRedirect($entity);
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot check target for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return $this->translationsPageRedirect($entity);
    }

    $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
    try {
      if ($translation_service->checkTargetStatus($entity, $drupal_language->id()) === Lingotek::STATUS_READY) {
        $this->messenger()->addStatus(t('The @locale translation for @entity_type %title is ready for download.', ['@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      else {
        $this->messenger()->addStatus(t('The @locale translation for @entity_type %title is still in progress.', ['@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    catch (LingotekApiException $exc) {
      $this->messenger()->addError(t('The request for @entity_type translation status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    return $this->translationsPageRedirect($entity);
  }

  public function addTarget($doc_id, $locale) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $entity = $translation_service->loadByDocumentId($doc_id);
    if (!$entity) {
      // TODO: log warning
      return $this->translationsPageRedirect($entity);
    }
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot request target for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return $this->translationsPageRedirect($entity);
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot request target for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return $this->translationsPageRedirect($entity);
    }

    try {
      if ($translation_service->addTarget($entity, $locale)) {
        $this->messenger()->addStatus(t("Locale '@locale' was added as a translation target for @entity_type %title.", ['@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      else {
        $this->messenger()->addWarning(t("There was a problem adding '@locale' as a translation target for @entity_type %title.", ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale]));
      }
    }
    catch (LingotekDocumentArchivedException $exception) {
      $this->messenger()->addError(t('Document @entity_type %title has been archived. Please upload again.', [
        '@entity_type' => $entity->getEntityTypeId(),
        '%title' => $entity->label(),
      ]));
    }
    catch (LingotekDocumentLockedException $exception) {
      $this->messenger()->addError(t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    catch (LingotekPaymentRequiredException $exception) {
      $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The translation request for @entity_type failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
    }
    return $this->translationsPageRedirect($entity);
  }

  public function upload($entity_type, $entity_id) {
    $entity = $this->entityTypeManager()->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      // TODO: log warning
      return $this->translationsPageRedirect($entity);
    }
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot upload @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return $this->translationsPageRedirect($entity);
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot upload @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return $this->translationsPageRedirect($entity);
    }
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    try {
      if ($translation_service->uploadDocument($entity)) {
        $this->messenger()->addStatus(t('@entity_type %title has been uploaded.', ['@entity_type' => ucfirst($entity->getEntityTypeId()), '%title' => $entity->label()]));
      }
      else {
        $this->messenger()->addError(t('The upload for @entity_type %title failed. Check your configuration and profile and try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    catch (LingotekPaymentRequiredException $exception) {
      $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The upload for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    return $this->translationsPageRedirect($entity);
  }

  public function update($doc_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $entity = $translation_service->loadByDocumentId($doc_id);
    if (!$entity) {
      // TODO: log warning
      return $this->translationsPageRedirect($entity);
    }
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot upload @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return $this->translationsPageRedirect($entity);
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot upload @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return $this->translationsPageRedirect($entity);
    }
    try {
      if ($translation_service->updateDocument($entity)) {
        $this->messenger()->addStatus(t('@entity_type %title has been updated.', ['@entity_type' => ucfirst($entity->getEntityTypeId()), '%title' => $entity->label()]));
      }
      else {
        $this->messenger()->addError(t('The upload for @entity_type %title failed. Check your configuration and profile and try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    catch (LingotekDocumentArchivedException $exception) {
      $this->messenger()->addError(t('Document @entity_type %title has been archived. Please upload again.', [
        '@entity_type' => $entity->getEntityTypeId(),
        '%title' => $entity->label(),
      ]));
    }
    catch (LingotekDocumentLockedException $exception) {
      $this->messenger()->addError(t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    catch (LingotekPaymentRequiredException $exception) {
      $this->messenger()->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The update for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    return $this->translationsPageRedirect($entity);
  }

  public function download($doc_id, $locale) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $entity = $translation_service->loadByDocumentId($doc_id);
    if (!$entity) {
      // TODO: log warning
      return $this->translationsPageRedirect($entity);
    }
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      \Drupal::messenger()->addWarning(t('Cannot download @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return $this->translationsPageRedirect($entity);
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot download @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]
      ));
      return $this->translationsPageRedirect($entity);
    }

    try {
      if ($translation_service->downloadDocument($entity, $locale)) {
        $this->messenger()->addStatus(t('The translation of @entity_type %title into @locale has been downloaded.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale]));
      }
      else {
        \Drupal::logger('lingotek')->warning('Error happened trying to download (%entity_id,%revision_id).', ['%entity_id' => $entity->id(), '%revision_id' => $entity->getRevisionId()]);
        $this->messenger()->addError(t('The translation of @entity_type %title into @locale failed to download.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale]));
      }
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The download for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    catch (LingotekContentEntityStorageException $storage_exception) {
      \Drupal::logger('lingotek')->error('The download for @entity_type %title failed because of the length of one field translation (%locale) value: %table.',
        ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%locale' => $locale, '%table' => $storage_exception->getTable()]);
      $this->messenger()->addError(t('The download for @entity_type %title failed because of the length of one field translation (%locale) value: %table.',
        ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%locale' => $locale, '%table' => $storage_exception->getTable()]));
    }
    return $this->translationsPageRedirect($entity);
  }

  protected function translationsPageRedirect(EntityInterface $entity) {
    $entity_type_id = $entity->getEntityTypeId();
    $uri = Url::fromRoute("entity.$entity_type_id.content_translation_overview", [$entity_type_id => $entity->id()]);
    $entity_type = $entity->getEntityType();
    if ($entity_type->hasLinkTemplate('canonical')) {
      return new RedirectResponse($uri->setAbsolute(TRUE)->toString());
    }
    else {
      return new RedirectResponse($this->request->getUri());
    }
  }

}
