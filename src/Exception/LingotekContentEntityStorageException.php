<?php

namespace Drupal\lingotek\Exception;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * An exception for issues when storing content entity translations.
 *
 * @package Drupal\lingotek\Exception
 */
class LingotekContentEntityStorageException extends LingotekException {

  /**
   * The entity that could not be saved.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * @var string
   */
  protected $table;

  public function __construct(ContentEntityInterface $entity, \Exception $previous = NULL, $message = NULL, $code = 0) {
    parent::__construct($message, $code, $previous);
    $this->entity = $entity;
    $this->table = $this->extractTableFromPreviousExceptionMessage($previous);
    $this->code = $previous->getCode();
  }

  /**
   * Gets the table name that failed to update.
   */
  public function getTable() {
    return $this->table;
  }

  /**
   * Extract the problematic table from the previous exception message.
   *
   * @param \Exception $previous
   *
   * @returns
   *  A string with the problematic table name.
   */
  protected function extractTableFromPreviousExceptionMessage(\Exception $previous = NULL) {
    $table = '';
    if ($previous !== NULL) {
      // Previous message would be like:
      //    "Data too long for column 'name' at row 2"
      $previous_message = $previous->getMessage();
      $strings = explode("'", $previous_message);
      $table = count($strings) > 1 ? $strings[1] : '';
    }
    return $table;
  }

}
