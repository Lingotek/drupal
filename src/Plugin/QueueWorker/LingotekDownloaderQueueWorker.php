<?php

namespace Drupal\Lingotek\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\lingotek\Lingotek;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Provides base functionality for the Lingotek Queue Workers.
 *
 * @QueueWorker(
 *   id = "lingotek_downloader_queue_worker",
 *   title = @Translation("Lingotek Download Queue"),
 *   cron = {"time" = 60}
 * )
 */
class LingotekDownloaderQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $locale = $data['locale'];
    $entity_type_id = $data['entity_type_id'];
    $entity_id = $data['entity_id'];
    $document_id = $data['document_id'];

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->setTargetStatus($entity, $locale, Lingotek::STATUS_READY);
    $download = $translation_service->downloadDocument($entity, $locale);

    if ($download === FALSE || $download === NULL) {
      $message = new FormattableMarkup('No download for target @locale happened in document @document on @entity @bundle @id.', [
        '@locale' => $locale,
        '@document' => $document_id,
        '@entity' => $entity->label(),
        '@id' => $entity->id(),
        '@bundle' => $entity->bundle(),
      ]);

      \Drupal::logger('lingotek')->error($message);
      throw new \Exception($message);
    }
  }

}
