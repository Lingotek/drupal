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

  public function checkTargets($doc_id) {

  }

  public function addTarget($doc_id, $locale) {

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
