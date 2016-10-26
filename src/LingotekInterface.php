<?php

namespace Drupal\lingotek;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface LingotekInterface {

  public function get($key);

  public function set($key, $value);

  public static function create(ContainerInterface $container);

  public function getAccountInfo();

  public function getResources($force = FALSE);

  public function getWorkflows($force = FALSE);

  public function getVaults($force = FALSE);

  public function getCommunities($force = FALSE);

  public function getProjects($force = FALSE);

  public function getDefaults();

  public function getProject($project_id);

  public function setProjectCallBackUrl($project_id, $callback_url);

  /**
   * Uploads a document to the Lingotek service.
   *
   * @param string $title
   *   The title of the document as it will be seen in the TMS.
   * @param $content
   *   The content of the document
   * @param string $locale
   *   The Lingotek locale.
   * @param string $url
   *   The document url in the site if any. This allows support for in-context review.
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   The profile being used.
   *
   * @return mixed
   */
  public function uploadDocument($title, $content, $locale, $url = NULL, LingotekProfileInterface $profile = NULL);

  /**
   * Updates a document in the Lingotek service.
   *
   * @param string $doc_id
   *   The document id to update.
   * @param $content
   *   The content of the document
   * @param string $url
   *   (optional) The document url in the site if any. This allows support for in-context review.
   * @param string $title
   *   (optional) The title of the document as it will be seen in the TMS.
   *
   * @return boolean
   */
  public function updateDocument($doc_id, $content, $url = NULL, $title = NULL);

  /**
   * @param $doc_id
   * @return mixed
   *
   * @deprecated in 8.x-1.4. Use ::getDocumentStatus() instead.
   */
  public function documentImported($doc_id);

  /**
   * Requests a translation to the Lingotek service.
   *
   * @param string $doc_id
   *   The document id to translate.
   * @param string $locale
   *   The Lingotek locale to request.
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   The profile being used.
   *
   * @return mixed
   */
  public function addTarget($doc_id, $locale, LingotekProfileInterface $profile = NULL);


  public function getDocumentStatus($doc_id);

  /**
   * Gets the last edited timestamp from Lingotek service.
   *
   * @param string $doc_id
   *   The document id in Lingotek.
   *
   * @return int
   *   The timestamp.
   */
  public function getUploadedTimestamp($doc_id);

  public function getDocumentTranslationStatus($doc_id, $locale);

  public function downloadDocument($doc_id, $locale);

  /**
   * Deletes the document with this document id from the Lingotek service.
   *
   * @param string $doc_id
   *   The document id in Lingotek.
   * @return bool
   *   TRUE if the document was successfully deleted. FALSE if not.
   */
  public function deleteDocument($doc_id);

}