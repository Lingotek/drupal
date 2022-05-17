<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Field;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentFieldBase;

/**
 * Defines a Lingotek form-field plugin for the translation profile.
 *
 * @LingotekFormComponentField(
 *   id = "profile",
 *   title = @Translation("Profile"),
 *   weight = 500,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   }
 * )
 */
class Profile extends LingotekFormComponentFieldBase {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function getData(EntityInterface $entity) {
    if ($this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, TRUE)) {
        return $profile->label();
      }

      return '';
    }

    return $this->t('Not enabled');
  }

}
