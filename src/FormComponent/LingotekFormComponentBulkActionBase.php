<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerAwareTrait;

/**
 * Base class for Lingotek form bulk action plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
abstract class LingotekFormComponentBulkActionBase extends LingotekFormComponentBase implements LingotekFormComponentBulkActionInterface {

  use LoggerAwareTrait;
  use DependencySerializationTrait;

  public function getTitle() {
    $args = [];
    if (isset($this->pluginDefinition['langcode'])) {
      $args['%langcode'] = $this->pluginDefinition['langcode'];
    }
    if (isset($this->pluginDefinition['language'])) {
      $args['%language'] = $this->pluginDefinition['language'];
    }
    if (isset($this->pluginDefinition['profile_id'])) {
      $args['%profile_id'] = $this->pluginDefinition['profile_id'];
    }
    if (isset($this->pluginDefinition['profile'])) {
      $args['%profile'] = $this->pluginDefinition['profile'];
    }

    return new TranslatableMarkup($this->pluginDefinition['title']->getUntranslatedString(), $args);
  }

  public function execute(array $entities, array $options, LingotekFormComponentBulkActionExecutor $executor) {
    // Do nothing by default.
  }

  public function executeSingle(ContentEntityInterface $entity, array $options, LingotekFormComponentBulkActionExecutor $executor, array &$context) {
    // Do nothing by default.
  }

  public function finished($success, $results, $operations) {
    if ($success) {
      $batch = &batch_get();
      $this->messenger()->addStatus('Operations completed.');
    }
    $redirect = $batch['sets'][0]['batch_redirect'] ?? NULL;
    if ($redirect !== NULL) {
      return new LocalRedirectResponse($redirect);
    }
  }

  public function buildFormElement() {
    return [];
  }

  public function getOptions() {
    return $this->pluginDefinition['options'] ?? [];
  }

  public function isBatched() {
    return !empty($this->pluginDefinition['batch']);
  }

  public function hasBatchBuilder() {
    return $this->isBatched() && !empty($this->pluginDefinition['batch']['function']);
  }

  /**
   * Gets the messenger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The messenger.
   */
  public function logger() {
    if (!isset($this->logger)) {
      $this->logger = \Drupal::logger('lingotek');
    }
    return $this->logger;
  }

}
