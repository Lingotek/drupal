<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Field;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase;

/**
 * Defines a Lingotek form-field plugin for a translation's source status.
 *
 * @LingotekFormComponentField(
 *   id = "source",
 *   title = @Translation("Source"),
 *   weight = 300,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   }
 * )
 */
class Source extends LingotekFormComponentFieldBase {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function getData(EntityInterface $entity) {
    $langcode_source = LingotekLocale::convertLingotek2Drupal($this->translationService->getSourceLocale($entity));
    $language = $this->languageManager->getLanguage($langcode_source);
    $source_status = $this->translationService->getSourceStatus($entity);
    $data = [
      'data' => [
        '#type' => 'lingotek_source_status',
        '#entity' => $entity,
        '#language' => $language,
        '#status' => $source_status,
      ],
    ];
    if ($source_status == Lingotek::STATUS_EDITED && !$this->translationService->getDocumentId($entity)) {
      $data['data']['#context']['status'] = strtolower(Lingotek::STATUS_REQUEST);
    }
    return $data;
  }

}
