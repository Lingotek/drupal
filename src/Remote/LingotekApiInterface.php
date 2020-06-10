<?php

namespace Drupal\lingotek\Remote;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

interface LingotekApiInterface extends ContainerInjectionInterface {

  /**
   * Get the account information.
   *
   * @return array|bool
   *   Array with account info. FALSE in case of error.
   *   The keys are user_id, username, login_id, email, and name.
   */
  public function getAccountInfo();

  /**
   * Get the available locales on Lingotek.
   *
   * @return array|bool
   *   Array of locales (as in de-DE, es-ES). FALSE if there is an error.
   */
  public function getLocales();

  /**
   * Adds a document to Lingotek.
   *
   * @param array $args
   *   The document data.
   * @see http://devzone.lingotek.com
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   *
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function addDocument($args);

  /**
   * Gets the document content from Lingotek.
   *
   * @param string $doc_id
   *   The document id.
   *
   * @return string
   *   The content.
   *
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function getDocumentContent($doc_id);

  /**
   * Updates a document in Lingotek.
   *
   * @param string $id
   *   The document id.
   * @param array $args
   *   The document data.
   * @see http://devzone.lingotek.com
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   *
   * @throws \Drupal\lingotek\Exception\LingotekApiException
   */
  public function patchDocument($id, $args);

  /**
   * Cancels a document on Lingotek.
   *
   * @param string $document_id
   *   The document id.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
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
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function cancelDocumentTarget($document_id, $locale);

  /**
   * Gets the document target translation statuses from the Lingotek service.
   *
   * @param string $id
   *   The document id.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function getDocumentTranslationStatuses($id);

  /**
   * Gets the document target translation status for a given locale from the Lingotek service.
   *
   * @param string $id
   *   The document id.
   * @param string $locale
   *   The target locale.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function getDocumentTranslationStatus($id, $locale);

  /**
   * Gets the document information from the Lingotek service.
   *
   * @param string $id
   *   The document id.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function getDocumentInfo($id);

  /**
   * Gets the document status from the Lingotek service.
   *
   * @param string $id
   *   The document id.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function getDocumentStatus($id);

  /**
   * Adds a document target translation for a given locale from the Lingotek service.
   *
   * @param string $id
   *   The document id.
   * @param string $locale
   *   The target locale.
   * @param string $workflow_id
   *   (Optional) The workflow ID.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function addTranslation($id, $locale, $workflow_id = NULL);

  /**
   * Gets a document target translation for a given locale from the Lingotek service.
   *
   * @param string $id
   *   The document id.
   * @param string $locale
   *   The target locale.
   * @param bool $useSource
   *   (Optional) Flag indicating if should return the source if this is not yet
   *   translated. By default is FALSE.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function getTranslation($id, $locale, $useSource);

  /**
   * Deletes a document target translation for a given locale from the Lingotek service.
   *
   * @param string $id
   *   The document id.
   * @param string $locale
   *   The target locale.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function deleteTranslation($id, $locale);

  /**
   * Gets the communities associated with the current account.
   *
   * @return array|bool
   *   Array with communities information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getCommunities();

  /**
   * Gets the project with the given ID.
   *
   * @param string $id
   *   The project ID.
   *
   * @return array|bool
   *   Array with project information.
   */
  public function getProject($id);

  /**
   * Gets the community related projects.
   *
   * @param string $community_id
   *   The community ID.
   *
   * @return array|bool
   *   Array with projects information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getProjects($community_id);

  /**
   * Gets the community related vaults.
   *
   * @param string $community_id
   *   The community ID.
   *
   * @return array|bool
   *   Array with vaults information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getVaults($community_id);

  /**
   * Gets the community related workflows.
   *
   * @param string $community_id
   *   The community ID.
   *
   * @return array|bool
   *   Array with communities information. FALSE in case of error.
   *   The keys are the ids, and values are the name of the resource.
   */
  public function getWorkflows($community_id);

  /**
   * Get the available filters on Lingotek.
   *
   * @return array|bool
   *   Array of filters as in (id, label). FALSE if there is an error.
   */
  public function getFilters();

}
