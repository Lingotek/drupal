<?php

namespace Drupal\lingotek;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface LingotekConfigMetadataInterface extends ConfigEntityInterface {

  /**
   * Gets the document id.
   */
  public function getDocumentId();

  /**
   * Sets the document id.
   *
   * @param string $document_id
   *   The document id.
   *
   * @return $this
   */
  public function setDocumentId($document_id);

  /**
   * Gets the source status.
   */
  public function getSourceStatus();

  /**
   * Sets the source status.
   *
   * @param array $source_status
   *   The source status, as an associative array langcode => status_code.
   *
   * @return $this
   */
  public function setSourceStatus(array $source_status);

  /**
   * Gets the target status.
   */
  public function getTargetStatus();

  /**
   * Sets the target status.
   *
   * @param array $target_status
   *   The target status, as an associative array langcode => status_code.
   *
   * @return $this
   */
  public function setTargetStatus(array $target_status);

  /**
   * Gets the profile of the document.
   */
  public function getProfile();

  /**
   * Sets the profile of the document.
   *
   * @param string $profile
   *   The profile of the document.
   *
   * @return $this
   */
  public function setProfile($profile);

  /**
   * Gets the hash of the uploaded document.
   */
  public function getHash();

  /**
   * Sets the hash of the uploaded document.
   *
   * @param string $hash
   *   The hash of the uploaded document.
   *
   * @return $this
   */
  public function setHash($hash);

  /**
   * Gets the job ID of the uploaded document.
   */
  public function getJobId();

  /**
   * Sets the job ID of the uploaded document.
   *
   * @param string $job_id
   *   The job ID of the uploaded document.
   *
   * @return $this
   */
  public function setJobId($job_id);

  /**
   * Sets the timestamp for the time of the initial upload.
   *
   * @param int $timestamp
   *   The unix timestamp of the event.
   *
   * @return $this
   */
  public function setLastUploaded($timestamp);

  /**
   * Gets the timestamp for the time of the initial upload.
   *
   * @return int
   */
  public function getLastUploaded();

  /**
   * Sets the timestamp for the time of the initial upload.
   *
   * @param int $timestamp
   *   The unix timestamp of the event.
   *
   * @return $this
   */
  public function setLastUpdated($timestamp);

  /**
   * Gets the timestamp for the last time document was updated.
   *
   * @return int
   */
  public function getLastUpdated();

}
