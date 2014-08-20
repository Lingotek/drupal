<?php

/**
 * @file
 * Contains Drupal\lingotek\LingotekException.
 */

namespace Drupal\lingotek;

/**
 * TMGMT Exception class.
 */
class LingotekException extends \Exception {

  /**
   * @param string $message
   * @param int $code
   * @param Exception $previous
   */
  function __construct($message = '', $code = 0, Exception $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }
}
