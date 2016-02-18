<?php

namespace Drupal\lingotek\Entity;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\lingotek\LingotekConfigMetadataInterface;

/**
 * Defines the Lingotek config metadata entity.
 *
 * Saves the metadata of config objects.
 *
 * @ConfigEntityType(
 *   id = "lingotek_config_metadata",
 *   label = @Translation("Lingotek Config Metadata"),
 *   admin_permission = "administer lingotek",
 *   entity_keys = {
 *     "id" = "config_name",
 *   },
 * )
 */
class LingotekConfigMetadata extends ConfigEntityBase implements LingotekConfigMetadataInterface {

  /**
   * The config_name.
   *
   * @var string
   */
  protected $config_name;

  /**
   * The Lingotek document id.
   *
   * @var string
   */
  protected $document_id;

  /**
   * The Lingotek source status.
   *
   * @var array
   */
  protected $source_status = [];

  /**
   * The Lingotek target status.
   *
   * @var array
   */
  protected $target_status = [];

  /**
   * The Lingotek hash.
   *
   * @var string
   */
  protected $hash = NULL;

  /**
   * {@inheritdoc}
   */
  public function getDocumentId() {
    return $this->document_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setDocumentId($document_id) {
    $this->document_id = $document_id;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceStatus() {
    return $this->source_status;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceStatus(array $source_status) {
    $this->source_status = $source_status;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatus() {
    return $this->target_status;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatus(array $target_status) {
    $this->target_status = $target_status;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHash() {
    return $this->hash;
  }

  /**
   * {@inheritdoc}
   */
  public function setHash($hash) {
    $this->hash = $hash;

    return $this;
  }

  /**
   * Loads or creates the lingotek config metadata for a given config name.
   *
   * @param string $config_name
   *   The config name.
   * @return LingotekConfigMetadataInterface
   */
  public static function loadByConfigName($config_name) {
    if ($config_name == NULL) {
      return NULL;
    }
    $config = \Drupal::entityManager()->getStorage('lingotek_config_metadata')->load($config_name);
    if ($config == NULL) {
      $config = LingotekConfigMetadata::create(['config_name' => $config_name]);
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->config_name;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Create dependency on the config.
    $this->addDependency('config', $this->config_name);

    return $this;
  }

}
