<?php

/**
 * @file
 * Defines LingotekDocument.
 */
 
/**
 * A class representing a Lingotek Document
 */
class LingotekDocument {
  /**
   * A Lingotek Document ID.
   *
   * @var int
   */
  public $document_id;
  
  /**
   * A reference to the Lingotek API.
   *
   * @var LingotekApi
   */
  protected $api = NULL;
  
  /**
   * Static store for Documents already loaded in this request.
   */
  public static $documents = array();
  
  /**
   * Constructor.
   *
   * @param $document_id
   *   A Lingotek Document ID.
   */
  public function __construct($document_id) {
    $this->document_id = intval($document_id);
  }
  
  /**
   * Gets the translation targets associated with this document.
   *
   * @return array
   *   An array of Translation Target, as returned by a getDocument
   *   Lingotek API call
   */
  public function translationTargets() {
    $targets = array();
    
    if ($document = LingotekApi::instance()->getDocument($this->document_id)) {
      if (!empty($document->translationTargets)) {
        foreach ($document->translationTargets as $target) {
          $targets[lingotek_drupal_language($target->language)] = $target;
        }        
      }
    }
    
    return $targets;
  }
  
  /**
   * Determines whether or not the document has Translation Targets in a complete-eligible phase.
   *
   * @return bool
   *   TRUE if complete-eligible phases are present. FALSE otherwise.
   */
  public function hasPhasesToComplete() {
    $result = FALSE;
    
    if (class_exists('LingotekPhase')) {
      foreach ($this->translationTargets() as $target) {
        $current_phase = LingotekPhase::loadWithData($this->api->currentPhase($target->id));
        if ($current_phase->canBeMarkedComplete()) {
          $result = TRUE;
          break;
        }
      }
    }
    
    return $result;
  }
  
  /**
   * Injects reference to an API object.
   *
   * @param LingotekApi $api
   *   An instantiated Lingotek API object.
   */
  public function setApi(LingotekApi $api) {
    $this->api = $api;
  }
  
  /**
   * Factory method for getting a loaded LingotekDocument object.
   *
   * @param int $document_id
   *   A Lingotek Document ID.
   *
   * @return LingotekDocument
   *   A loaded LingotekDocument object.
   */
  public static function load($document_id) {
    $document_id = intval($document_id);
    if (empty($documents[$document_id])) {
      $document = new LingotekDocument($document_id);
      $document->setApi(LingotekApi::instance());
      $documents[$document_id] = $document;
    }
    
    return $documents[$document_id];
  }
}
