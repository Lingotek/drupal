<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Field;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase;

/**
 * Defines a Lingotek form-field plugin for an entity ID.
 *
 * @LingotekFormComponentField(
 *   id = "translations",
 *   title = @Translation("Translations"),
 *   weight = 400,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   }
 * )
 */
class Translations extends LingotekFormComponentFieldBase {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function getData(EntityInterface $entity) {
    $statuses = $this->translationService->getTargetStatuses($entity);
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = $this->lingotekConfiguration->getEntityProfile($entity);
    array_walk($statuses, function (&$status, $langcode) use ($entity, $profile) {
      if ($profile !== NULL && $profile->hasDisabledTarget($langcode)) {
        $status = Lingotek::STATUS_DISABLED;
      }
    });
    $languages = $this->lingotekConfiguration->getEnabledLanguages();
    foreach ($languages as $langcode => $language) {
      if ($profile !== NULL && $profile->hasDisabledTarget($langcode)) {
        $statuses[$langcode] = Lingotek::STATUS_DISABLED;
      }
    }
    return [
      'data' => [
        '#type' => 'lingotek_target_statuses',
        '#entity' => $entity,
        '#source_langcode' => $entity->language()->getId(),
        '#statuses' => $statuses,
      ],
    ];
  }

}
