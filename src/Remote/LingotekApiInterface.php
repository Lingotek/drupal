<?php

namespace Drupal\lingotek\Remote;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface LingotekApiInterface {

  public static function create(ContainerInterface $container);

  public function getAccountInfo();

  /**
   * Get the available locales on Lingotek.
   *
   * @return array|bool
   *   Array of locales (as in de-DE, es-ES). FALSE if there is an error.
   */
  public function getLocales();

  public function addDocument($args);

  public function patchDocument($id, $args);

  /**
   * Delete a document on Lingotek.
   *
   * @param string $id
   *   The document id.
   *
   * @return mixed
   *
   * @deprecated in 8.x-2.14, will be removed in 8.x-2.16. Use ::cancelDocument instead.
   */
  public function deleteDocument($id);

  /**
   * Cancels a document on Lingotek.
   *
   * @param string $document_id
   *   The document id.
   *
   * @return mixed
   */
  public function cancelDocument($document_id);

  /**
   * Cancels the document target with this document id and locale from the Lingotek service.
   *
   * @param string $document_id
   *   The document id.
   * @param string $locale
   *   The locale target we want to cancel the translation.
   *
   * @return mixed
   */
  public function cancelDocumentTarget($document_id, $locale);

  public function getDocument($id);

  public function documentExists($id);

  public function getDocumentTranslationStatuses($id);

  public function getDocumentTranslationStatus($id, $locale);

  public function getDocumentInfo($id);

  public function getDocumentStatus($id);

  public function addTranslation($id, $locale, $workflow_id = NULL);

  public function getTranslation($id, $locale, $useSource);

  public function deleteTranslation($id, $locale);

  public function getCommunities();

  public function getProjects($community_id);

  public function getVaults($community_id);

  public function getWorkflows($community_id);

  /**
   * Get the available filters on Lingotek.
   *
   * @return
   *   Array of filters as in (id, label). FALSE if there is an error.
   */
  public function getFilters();

}
