<?php

namespace Drupal\lingotek;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface LingotekInterface {

  public function get($key);

  public function set($key, $value);

  public static function create(ContainerInterface $container);

  /**
   * Get the available locales on Lingotek.
   *
   * @return array
   *   Array of locales (as in de-DE, es-ES). Empty array if there is an error.
   */
  public function getLocales();

  public function getAccountInfo();

  public function getResources($force = FALSE);

  public function getWorkflows($force = FALSE);

  public function getVaults($force = FALSE);

  public function getCommunities($force = FALSE);

  public function getProjects($force = FALSE);

  public function getDefaults();

  public function getProject($project_id);

  /**
   * Get all the available filters.
   *
   * @param bool $force
   *   If we should force a refresh or we can use the local storage.
   * @return array
   *   Array of filters as in (id, label).
   */
  public function getFilters($force = FALSE);

  public function setProjectCallBackUrl($project_id, $callback_url);

  /**
   * Uploads a document to the Lingotek service.
   *
   * @param string $title
   *   The title of the document as it will be seen in the TMS.
   * @param string|array $content
   *   The content of the document. It can be a json string or an array that will
   *   be json encoded.
   * @param string $locale
   *   The Lingotek locale.
   * @param string $url
   *   (optional) The document url in the site if any. This allows support for in-context review.
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   (optional) The profile being used.
   * @param string $job_id
   *   (optional) The job ID that will be associated.
   *
   * @return string
   *   The document ID assigned to the uploaded document.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function uploadDocument($title, $content, $locale, $url = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL);

  /**
   * Updates a document in the Lingotek service.
   *
   * @param string $doc_id
   *   The document id to update.
   * @param string|array $content
   *   The content of the document. It can be a json string or an array that will
   *   be json encoded.
   * @param string $url
   *   (optional) The document url in the site if any. This allows support for in-context review.
   * @param string $title
   *   (optional) The title of the document as it will be seen in the TMS.
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   (optional) The profile being used.
   * @param string $job_id
   *   (optional) The job ID that will be associated.
   *
   * @return bool|string
   *   TRUE if the document was successfully updated. FALSE if not (v5.1).
   *   New document ID if the document was successfully updated. FALSE if not (v5.2).
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function updateDocument($doc_id, $content, $url = NULL, $title = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL);

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
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
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

  /**
   * Checks the status of the translation.
   *
   * @param string $doc_id
   *   The document ID in Lingotek.
   * @param $locale
   *   The locale we want to know the translation status.
   *
   * @return bool|int
   *   Returns TRUE if the document translation is completed. FALSE if it was not
   *   requested. The percentage if it's still in progress.
   */
  public function getDocumentTranslationStatus($doc_id, $locale);

  public function getDocumentTranslationStatuses($doc_id);

  public function downloadDocument($doc_id, $locale);

  /**
   * Deletes the document with this document id from the Lingotek service.
   *
   * @param string $doc_id
   *   The document id in Lingotek.
   * @return bool
   *   TRUE if the document was successfully deleted. FALSE if not.
   *
   * @deprecated in 8.x-2.14, will be removed in 8.x-2.16. Use ::cancelDocument instead.
   */
  public function deleteDocument($doc_id);

  /**
   * Cancels the document with this document id from the Lingotek service.
   *
   * @param string $doc_id
   *   The document id in Lingotek.
   *
   * @return bool
   *   TRUE if the document was successfully cancelled. FALSE if not.
   */
  public function cancelDocument($doc_id);

  /**
   * Cancels the document target with this document id and locale from the Lingotek service.
   *
   * @param string $doc_id
   *   The document id in Lingotek.
   * @param string $locale
   *   The locale target we want to cancel the translation.
   *
   * @return bool
   *   TRUE if the document target was successfully cancelled. FALSE if not.
   */
  public function cancelDocumentTarget($doc_id, $locale);

}
