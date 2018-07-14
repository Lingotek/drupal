<?php

namespace Drupal\lingotek\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\LanguageFormatter;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;

/**
 * Plugin implementation of the 'lingotek_translation_status' formatter.
 *
 * @FieldFormatter(
 *   id = "lingotek_source_status",
 *   label = @Translation("Lingotek source status"),
 *   field_types = {
 *     "language",
 *   }
 * )
 */
class LingotekSourceStatusFormatter extends LanguageFormatter {

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    $entity = $item->getEntity();
    $source_status = Lingotek::STATUS_UNTRACKED;
    if ($entity instanceof LingotekContentMetadata) {
      // $entity is the metadata of another entity. Let's get the source.
      $entity = $this->entityManager->getStorage($entity->getContentEntityTypeId())->load($entity->getContentEntityId());
      $source_status = \Drupal::service('lingotek.content_translation')->getSourceStatus($entity);
    }

    $data = [
      'data' => [
        '#type' => 'lingotek_source_status',
        '#entity' => $entity,
        '#language' => $item->language,
        '#status' => $source_status,
      ],
    ];
    if ($source_status == Lingotek::STATUS_EDITED && !\Drupal::service('lingotek.content_translation')->getDocumentId($entity)) {
      $data['data']['#context']['status'] = strtolower(Lingotek::STATUS_REQUEST);
    }
    return $data;

  }

  protected function getDestinationWithQueryArray() {
    return ['destination' => \Drupal::request()->getRequestUri()];
  }

}
