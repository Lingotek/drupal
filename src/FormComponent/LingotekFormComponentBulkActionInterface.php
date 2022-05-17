<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for Lingotek form-bulk-action plugins.
 *
 * @package Drupal\lingotek\FormComponent
 */
interface LingotekFormComponentBulkActionInterface extends LingotekFormComponentInterface {

  public function getOptions();

  public function executeSingle(ContentEntityInterface $entity, array $options, LingotekFormComponentBulkActionExecutor $executor, array &$context);

  public function execute(array $entities, array $options, LingotekFormComponentBulkActionExecutor $executor);

  public function isBatched();

  public function hasBatchBuilder();

}
