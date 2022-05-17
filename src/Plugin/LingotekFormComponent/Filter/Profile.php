<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\Filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\lingotek\FormComponent\LingotekFormComponentFilterBase;

/**
 * Defines a Lingotek form-filter plugin for the translation profile.
 *
 * @LingotekFormComponentFilter(
 *   id = "profile",
 *   title = @Translation("Profile"),
 *   form_ids = {
 *     "lingotek_management",
 *   },
 *   weight = 600,
 *   group = @Translation("Advanced options"),
 * )
 */
class Profile extends LingotekFormComponentFilterBase {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildElement($default_value = NULL) {
    return [
      '#type' => 'select',
      '#title' => $this->getTitle(),
      '#options' => ['' => $this->t('All')] + $this->lingotekConfiguration->getProfileOptions(),
      '#multiple' => TRUE,
      '#default_value' => $default_value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $entity_type_id, array $entities, $value, SelectInterface &$query = NULL) {
    if (!in_array('', $value, TRUE)) {
      parent::filter($entity_type_id, $entities, $value, $query);
      $entity_type = $this->getEntityType($entity_type_id);

      $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
      $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
        'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
      $query->condition('metadata.profile', $value, 'IN');

      if ($unions = $query->getUnion()) {
        foreach ($unions as $union) {
          $union['query']->innerJoin($metadata_type->getBaseTable(), 'metadata',
            'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
          $union['query']->condition('metadata.profile', $value, 'IN');
        }
      }
    }
  }

}
