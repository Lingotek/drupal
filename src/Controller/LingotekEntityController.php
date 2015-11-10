<?php

namespace Drupal\lingotek\Controller;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekLocale;
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
    if ($translation_service->checkSourceStatus($entity)) {
      drupal_set_message(t('The import for @entity_type %title is complete.', array('@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label())));
    } else {
      drupal_set_message(t('The import for @entity_type %title is still pending.', array('@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label())));
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
    if ($translation_service->checkTargetStatus($entity, $drupal_language->id()) === Lingotek::STATUS_READY) {
      drupal_set_message(t('The @locale translation for @entity_type %title is ready for download.', array('@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label())));
    } else {
      drupal_set_message(t('The @locale translation for @entity_type %title is still in progress.', array('@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label())));
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
    if ($translation_service->addTarget($entity, $locale)) {
      drupal_set_message(t("Locale '@locale' was added as a translation target for @entity_type %title.", array('@locale' => $locale, '@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label())));
    } else {
      drupal_set_message(t("There was a problem adding '@locale' as a translation target for @entity_type %title.", array('@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale)), 'warning');
    }
    return $this->translationsPageRedirect($entity);
  }

  public function upload($entity_type, $entity_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $entity = $this->entityManager()->getStorage($entity_type)->load($entity_id);
    if ($translation_service->uploadDocument($entity)) {
      drupal_set_message(t('@entity_type %title has been uploaded.', ['@entity_type' => ucfirst($entity->getEntityTypeId()), '%title' => $entity->label()]));
    }
    return $this->translationsPageRedirect($entity);
  }

  public function update($doc_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $entity = $translation_service->loadByDocumentId($doc_id);
    if ($translation_service->updateDocument($entity)) {
      drupal_set_message(t('@entity_type %title has been updated.', ['@entity_type' => ucfirst($entity->getEntityTypeId()), '%title' => $entity->label()]));
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
    if ($translation_service->downloadDocument($entity, $locale)) {
      drupal_set_message(t('The translation of @entity_type %title into @locale has been downloaded.', array('@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale)));
    } else {
      drupal_set_message(t('The translation of @entity_type %title into @locale failed to download.', array('@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '@locale' => $locale)), 'error');
    }
    return $this->translationsPageRedirect($entity);
  }

  protected function translationsPageRedirect($entity) {
    $entity_type_id = $entity->getEntityTypeId();
    $uri = Url::fromRoute("entity.$entity_type_id.content_translation_overview", [$entity_type_id => $entity->id()]);
    return new RedirectResponse($uri->setAbsolute(TRUE)->toString());
  }

}
