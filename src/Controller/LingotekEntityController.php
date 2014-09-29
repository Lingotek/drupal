<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekTranslatableEntity;
use Drupal\lingotek\Controller\LingotekControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LingotekEntityController extends LingotekControllerBase {

  protected $translations_link;

  public function checkUpload($doc_id) {
    $te = LingotekTranslatableEntity::loadByDocId($doc_id);
    if (!$te) {
      // TODO: log warning
      return $this->translationsPageRedirect($te->entity);
    }
    if ($this->L->documentImported($doc_id)) {
      $te->setSourceStatus(Lingotek::STATUS_CURRENT);
      drupal_set_message(t('The import for @entity_type #@entity_id is complete.', array('@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    }
    else {
      drupal_set_message(t('The import for @entity_type #@entity_id is still pending.', array('@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    }
    return $this->translationsPageRedirect($te->entity);
  }

  public function checkTarget($doc_id, $locale) {
    $te = LingotekTranslatableEntity::loadByDocId($doc_id);
    if (!$te) {
      // TODO: log warning
      return $this->translationsPageRedirect($te->entity);
    }
    if ($this->L->getDocumentStatus($doc_id)) {
      $te->setTargetStatus($locale, Lingotek::STATUS_READY);
      drupal_set_message(t('The @locale translation for @entity_type #@entity_id is ready for download.', array('@locale' => $locale, '@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    }
    else {
      drupal_set_message(t('The @locale translation for @entity_type #@entity_id is ready for download.', array('@locale' => $locale, '@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    }
    return $this->translationsPageRedirect($te->entity);
  }

  public function addTarget($doc_id, $locale) {
    $te = LingotekTranslatableEntity::loadByDocId($doc_id);
    if (!$te) {
      // TODO: log warning
      return $this->translationsPageRedirect($te->entity);
    }
    if ($this->L->addTarget($doc_id, $locale)) {
      $te->setTargetStatus($locale, Lingotek::STATUS_PENDING);
      drupal_set_message(t("Locale '@locale' was added as a translation target for @entity_type #@entity_id.", array('@locale' => $locale, '@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    }
    else {
      drupal_set_message(t("There was a problem adding '@locale' as a translation target for @entity_type #@entity_id.", array('@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())), 'warning');
    }
    return $this->translationsPageRedirect($te->entity);
  }

  public function upload($entity_type, $entity_id) {

  }

  public function update($doc_id) {

  }

  public function download($doc_id) {

  }

  protected function translationsPageRedirect($entity) {
    return new RedirectResponse(url($entity->getEntityTypeId() . '/' . $entity->id() . '/translations'));
  }

}