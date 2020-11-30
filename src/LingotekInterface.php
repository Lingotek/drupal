<?php

namespace Drupal\lingotek;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

interface LingotekInterface extends ContainerInjectionInterface {

  /**
   * Get the available locales on Lingotek.
   *
   * @return array
   *   Array of locales (as in de-DE, es-ES). Empty array if there is an error.
   */
  public function getLocales();

  /**
   * Gets data from the configuration object.
   *
   * @param string $key
   *   A string that maps to a key within the configuration data.
   *   For instance in the following configuration array:
   *   @code
   *   array(
   *     'foo' => array(
   *       'bar' => 'baz',
   *     ),
   *   );
   *   @endcode
   *   A key of 'foo.bar' would return the string 'baz'. However, a key of 'foo'
   *   would return array('bar' => 'baz').
   *   If no key is specified, then the entire data array is returned.
   *
   * @return mixed
   *   The data that was requested.
   *
   * @deprecated in lingotek:3.0.1 and is removed from lingotek:4.0.0.
   *   Use configuration or configuration services directly.
   * @see \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  public function get($key);

  /**
   * Gets data from the mutable configuration object.
   * Returns an mutable configuration object for a given name.
   *
   * Should not be used for config that will have runtime effects. Therefore it
   * is always loaded override free.
   *
   * @param string $key
   *   A string that maps to a key within the configuration data.
   *
   * @return mixed
   *   The data that was requested.
   *
   * @see ::get
   *
   * @deprecated in lingotek:3.0.1 and is removed from lingotek:4.0.0.
   *   Use configuration or configuration services directly.
   * @see \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  public function getEditable($key);

  /**
   * Set a setting value (and save).
   *
   * @param string $key
   *   The key for the setting.
   * @param mixed $value
   *   The value for the setting.
   *
   * @deprecated in lingotek:3.0.1 and is removed from lingotek:4.0.0.
   *   Use configuration or configuration services directly.
   * @see \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  public function set($key, $value);

  /**
   * Get the available locales on Lingotek with extra information.
   *
   * @return array
   *   Array of locales. Empty array if there is an error.
   *   The array has the locale as key, and the value is a nested array with
   *   the following keys: code, language_code, title, language, country_code,
   *   and country.
   */
  public function getLocalesInfo();

  /**
   * Get the account information.
   *
   * @return array|bool
   *   Array with account info. FALSE in case of error.
   *   The keys are user_id, username, login_id, email, and name.
   */
  public function getAccountInfo();

  /**
   * Gets the account related resources.
   *
   * @param bool $force
   *   Flag indicating if it must be forced to request from the API. If false,
   *   will use local cached data.
   *
   * @return array|bool
   *   Array with resources info. FALSE in case of error.
   *   The keys are community, project, vault, workflow, and filter. Each of them
   *   is a nested array with key the id, and value the name of the resource.
   */
  public function getResources($force = FALSE);

  /**
   * Gets the account related workflows.
   *
   * @param bool $force
   *   Flag indicating if it must be forced to request from the API. If false,
   *   will use local cached data.
   *
   * @return array|bool
   *   Array with workflows information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getWorkflows($force = FALSE);

  /**
   * Gets the account related vaults.
   *
   * @param bool $force
   *   Flag indicating if it must be forced to request from the API. If false,
   *   will use local cached data.
   *
   * @return array|bool
   *   Array with vaults information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getVaults($force = FALSE);

  /**
   * Gets the account related communities.
   *
   * @param bool $force
   *   Flag indicating if it must be forced to request from the API. If false,
   *   will use local cached data.
   *
   * @return array|bool
   *   Array with communities information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getCommunities($force = FALSE);

  /**
   * Gets the account related projects.
   *
   * @param bool $force
   *   Flag indicating if it must be forced to request from the API. If false,
   *   will use local cached data.
   *
   * @return array|bool
   *   Array with projects information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getProjects($force = FALSE);

  public function getDefaults();

  /**
   * Gets the project with the given ID.
   *
   * @param string $project_id
   *   The project ID.
   *
   * @return array|bool
   *   Array with project information.
   */
  public function getProject($project_id);

  /**
   * Get all the available filters.
   *
   * @param bool $force
   *   If we should force a refresh or we can use the local storage.
   *
   * @return array
   *   Array of filters as in (id, label).
   */
  public function getFilters($force = FALSE);

  /**
   * Sets the project callback url.
   *
   * @param string $project_id
   *   The project id.
   * @param string $callback_url
   *   The callback url.
   *
   * @return bool
   *   TRUE if successful, FALSE if not.
   */
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
   * @param string $locale
   *   (optional) The Lingotek locale.
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
  public function updateDocument($doc_id, $content, $url = NULL, $title = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL, $locale = NULL);

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
   * @return bool
   *   TRUE if the document was successfully updated. FALSE if not.
   *
   * @throws \Drupal\lingotek\Exception\LingotekPaymentRequiredException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentArchivedException
   * @throws \Drupal\lingotek\Exception\LingotekDocumentLockedException
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function addTarget($doc_id, $locale, LingotekProfileInterface $profile = NULL);

  /**
   * Gets a document status.
   *
   * @param string $doc_id
   *   The document id.
   *
   * @return bool
   *   TRUE if the document exists. FALSE if not.
   */
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
   * Gets the status of the translation.
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

  /**
   * Gets the status of the translations.
   *
   * @param string $doc_id
   *   The document ID in Lingotek.
   *
   * @return array
   *   Returns array keyed by the locale with the percentage of completion.
   */
  public function getDocumentTranslationStatuses($doc_id);

  /**
   * Gets the translation of a document for a given locale.
   *
   * @param string $doc_id
   *   The document ID in Lingotek.
   * @param $locale
   *   The locale we want to know the translation status.
   *
   * @return array
   *   Returns array with the content of the document.
   */
  public function downloadDocument($doc_id, $locale);

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
