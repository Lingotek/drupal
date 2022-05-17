<?php

namespace Drupal\lingotek\FormComponent;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;

class LingotekFormComponentBulkActionExecutor {

  public function doExecute(LingotekFormComponentBulkActionInterface $action, array $entities, array $options, LingotekFormComponentBulkActionInterface $fallbackAction = NULL) {
    try {
      $result = $action->execute($entities, $options, $this);
    }
    catch (LingotekDocumentArchivedException $archivedException) {
      if ($fallbackAction) {
        $result = $fallbackAction->execute($entities, $options, $this);
      }
      else {
        throw $archivedException;
      }
    }
    return $result;
  }

  public function execute(LingotekFormComponentBulkActionInterface $action, array $entities, array $options, LingotekFormComponentBulkActionInterface $fallbackAction = NULL) {
    if ($action->isBatched() && $action->hasBatchBuilder()) {
      return $action->createBatch($this, $entities, $options, $fallbackAction);
    }
    elseif ($action->isBatched()) {
      return $this->createBatch($action, $entities, $options, $fallbackAction);
    }
    else {
      return $action->execute($entities, $options, $this);
    }
  }

  public function doExecuteSingle(LingotekFormComponentBulkActionInterface $action, ContentEntityInterface $entity, array $options, LingotekFormComponentBulkActionInterface $fallbackAction = NULL, array &$context) {
    try {
      $result = $action->executeSingle($entity, $options, $this, $context);
    }
    catch (LingotekDocumentArchivedException $archivedException) {
      if ($fallbackAction) {
        $result = $fallbackAction->executeSingle($entity, $options, $this, $context);
      }
      else {
        throw $archivedException;
      }
    }
    return $result;
  }

  /**
   * Create and set a batch.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   Array of entities to process.
   * @param array $options
   *   Array of options.
   */
  protected function createBatch(LingotekFormComponentBulkActionInterface $action, $entities, array $options, LingotekFormComponentBulkActionInterface $fallbackAction = NULL) {
    $operations = [];
    foreach ($entities as $entity) {
      $operations[] = [[$this, 'doExecuteSingle'], [$action, $entity, $options, $fallbackAction]];
    }
    $batch = [
      'title' => $action->getTitle(),
      'operations' => $operations,
      'finished' => [$action, 'finished'],
      'progressive' => TRUE,
    ];
    batch_set($batch);
    return TRUE;
  }

}
