<?php

namespace Drupal\lingotek\Exception;

/**
 * An exception for issues when a document is already locked because a new
 * version exists.
 *
 * @package Drupal\lingotek\Exception
 */
class LingotekDocumentLockedException extends LingotekException {

  /**
   * The old document id.
   *
   * @var string
   */
  protected $oldDocumentId;

  /**
   * The document id.
   *
   * @var string
   */
  protected $newDocumentId;

  /**
   * Construct the exception. Note: The message is NOT binary safe.
   *
   * @param string $old_document_id
   *   The old document id.
   * @param string $new_document_id
   *   The new document id
   * @param string $message
   *   (optional) The Exception message to throw.
   * @param int $code
   *   (optional) The Exception code.
   * @param \Throwable $previous
   *   (optional) The previous throwable used for the exception chaining.
   */
  public function __construct($old_document_id, $new_document_id, $message = "", int $code = 0, \Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->oldDocumentId = $old_document_id;
    $this->newDocumentId = $new_document_id;
  }

  /**
   * Get the document id which was already locked.
   *
   * @return string
   */
  public function getOldDocumentId() {
    return $this->oldDocumentId;
  }

  /**
   * Get the new document id for that document.
   *
   * @return string
   */
  public function getNewDocumentId() {
    return $this->newDocumentId;
  }

}
