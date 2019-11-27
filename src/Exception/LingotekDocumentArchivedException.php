<?php

namespace Drupal\lingotek\Exception;

/**
 * An exception for issues when a document is already archived.
 *
 * @package Drupal\lingotek\Exception
 */
class LingotekDocumentArchivedException extends LingotekException {

  /**
   * The document id.
   *
   * @var string
   */
  protected $documentId;

  /**
   * Construct the exception. Note: The message is NOT binary safe.
   *
   * @param string $document_id
   *   The document id.
   * @param string $message
   *   (optional) The Exception message to throw.
   * @param int $code
   *   (optional) The Exception code.
   * @param \Throwable $previous
   *   (optional) The previous throwable used for the exception chaining.
   */
  public function __construct($document_id, $message = "", int $code = 0, \Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->documentId = $document_id;
  }

  /**
   * Get the document id which was already archived.
   *
   * @return string
   */
  public function getDocumentId() {
    return $this->documentId;
  }

}
