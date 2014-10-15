<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekTranslatableEntity;
use Drupal\lingotek\Controller\LingotekControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LingotekEntityController extends LingotekControllerBase {

  protected $translations_link;

  public function checkUpload($doc_id) {
    $te = LingotekTranslatableEntity::loadByDocId($doc_id);
    if (!$te) {
      // TODO: log warning
      return $this->translationsPageRedirect($te->entity);
    }
    if ($te->checkSourceStatus()) {
      drupal_set_message(t('The import for @entity_type #@entity_id is complete.', array('@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    } else {
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
    if ($te->checkTargetStatus($locale) == Lingotek::STATUS_READY) {
      drupal_set_message(t('The @locale translation for @entity_type #@entity_id is ready for download.', array('@locale' => $locale, '@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    } else {
      drupal_set_message(t('The @locale translation for @entity_type #@entity_id is still in progress.', array('@locale' => $locale, '@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    }
    return $this->translationsPageRedirect($te->entity);
  }

  public function addTarget($doc_id, $locale) {
    $te = LingotekTranslatableEntity::loadByDocId($doc_id);
    if (!$te) {
      // TODO: log warning
      return $this->translationsPageRedirect($te->entity);
    }
    if ($te->addTarget($locale)) {
      drupal_set_message(t("Locale '@locale' was added as a translation target for @entity_type #@entity_id.", array('@locale' => $locale, '@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())));
    } else {
      drupal_set_message(t("There was a problem adding '@locale' as a translation target for @entity_type #@entity_id.", array('@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id())), 'warning');
    }
    return $this->translationsPageRedirect($te->entity);
  }

  public function upload($entity_type, $entity_id) {

  }

  public function update($doc_id) {

  }

  public function download($doc_id, $locale) {
    $te = LingotekTranslatableEntity::loadByDocId($doc_id);
    if (!$te) {
      // TODO: log warning
      return $this->translationsPageRedirect($te->entity);
    }
    if ($te->download($locale)) {
      drupal_set_message(t('The translation of @entity_type #@entity_id into @locale has been downloaded.', array('@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id(), '@locale' => $locale)));
    } else {
      drupal_set_message(t('The translation of @entity_type #@entity_id into @locale failed to download.', array('@entity_type' => $te->entity->getEntityTypeId(), '@entity_id' => $te->entity->id(), '@locale' => $locale)), 'error');
    }
    return $this->translationsPageRedirect($te->entity);
  }

  protected function translationsPageRedirect($entity) {
    $entity_type = $entity->getEntityTypeId();
    $link = $entity_type . '/' . $entity->id() . '/translations';
    return new RedirectResponse(Url::fromUri('base://' . $link)->toString() );
  }

}
