<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Lingotek;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LingotekEntityController extends LingotekControllerBase {

  protected $translations_link;

  public function checkUpload($doc_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $entity = $translation_service->loadByDocumentId($doc_id);
    if (!$entity) {
      // TODO: log warning
      return $this->translationsPageRedirect($entity);
    }
    try {
      if ($translation_service->checkSourceStatus($entity)) {
        drupal_set_message(t('The import for @entity_type %title is complete.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      else {
        drupal_set_message(t('The import for @entity_type %title is still pending.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    catch (LingotekApiException $exception) {
      drupal_set_message(t('The check for @entity_type status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
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

    $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
    try {
      if ($translation_service->checkTargetStatus($entity, $drupal_language->id()) === Lingotek::STATUS_READY) {
        drupal_set_message(t('The @locale translation for @entity_type %title is ready for download.', ['@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      else {
        drupal_set_message(t('The @locale translation for @entity_type %title is still in progress.', ['@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
    }
    catch (LingotekApiException $exc) {
      drupal_set_message(t('The request for @entity_type translation status failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
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
    try {
      if ($translation_service->addTarget($entity, $locale)) {
        drupal_set_message(t("Locale '@locale' was added as a translation target for @entity_type %title.", ['@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
      }
      else {
        drupal_set_message(t("There was a problem adding '@locale' as a translation target for @entity_type %title.", ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale]), 'warning');
      }
    }
    catch (LingotekApiException $exception) {
      drupal_set_message(t('The translation request for @entity_type failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
    }
    return $this->translationsPageRedirect($entity);
  }

  public function upload($entity_type, $entity_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    try {
      if ($translation_service->uploadDocument($entity)) {
        drupal_set_message(t('@entity_type %title has been uploaded.', ['@entity_type' => ucfirst($entity->getEntityTypeId()), '%title' => $entity->label()]));
      }
    }
    catch (LingotekApiException $exception) {
      $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      drupal_set_message(t('The upload for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
    }
    return $this->translationsPageRedirect($entity);
  }

  public function update($doc_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $entity = $translation_service->loadByDocumentId($doc_id);
    try {
      if ($translation_service->updateDocument($entity)) {
        drupal_set_message(t('@entity_type %title has been updated.', ['@entity_type' => ucfirst($entity->getEntityTypeId()), '%title' => $entity->label()]));
      }
    }
    catch (LingotekApiException $exception) {
      $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
      drupal_set_message(t('The update for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
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

    try {
      if ($translation_service->downloadDocument($entity, $locale)) {
        drupal_set_message(t('The translation of @entity_type %title into @locale has been downloaded.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale]));
      }
      else {
        drupal_set_message(t('The translation of @entity_type %title into @locale failed to download.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale]), 'error');
      }
    }
    catch (LingotekApiException $exception) {
      drupal_set_message(t('The download for @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]), 'error');
    }
    catch (LingotekContentEntityStorageException $storage_exception) {
      drupal_set_message(t('The download for @entity_type %title failed because of the length of one field translation value: %table.',
        ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%table' => $storage_exception->getTable()]), 'error');
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
