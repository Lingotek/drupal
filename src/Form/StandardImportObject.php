<?php

namespace Drupal\lingotek\Form;

/**
 *@file
 * Used to hold all important information about a post or page to be imported
 *@author Unknown
 */
class StandardImportObject {
  private $title;
  private $content;
  private $error;

  /**
   * Constructor
   * @author Unknown
   * @param string $title
   *   This is what will become the title of the post/page
   *   when the document is imported into WP
   * @param string $content
   *   This is what will become the body of the post/page
   *   when the document is imported into WP
   * @param bool $error
   *   Captures if there was an error in preparing the document
   */
  public function __construct($title, $content, $error = FALSE) {
    $this->title = $title;
    $this->content = $content;
    $this->error = $error;
  }

  /**
   * Gets the title of the object
   * @author Unknown
   * @return string $this->title
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Sets the title of the object
   * @author Unknown
   * @param string $title
   *   What the title of the import should be
   * @return self
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * Gets the Content of the object
   * @author Unknown
   * @return string $this->content
   */
  public function getContent() {
    return $this->content;
  }

  /**
   * Sets the content of the object
   * @author Unknown
   * @param string $content
   *   What the body of the import should be
   * @return self
   */
  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  /**
   * Sets the error for the object
   * @author Unknown
   * @param bool $trueOrFalse
   *   TRUE means there was an error, FALSE means there
   *   was not.
   */
  public function setError($trueOrFalse) {
    $this->error = $trueOrFalse;
  }

  /**
   * Checks to see if the object has an error.
   * @author Unknown
   * @return bool $this->error
   */
  public function hasError() {
    return $this->error;
  }

}
