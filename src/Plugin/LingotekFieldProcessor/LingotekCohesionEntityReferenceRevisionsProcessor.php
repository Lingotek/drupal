<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "cohesion_entity_reference_revisions",
 *   weight = 5,
 * )
 */
class LingotekCohesionEntityReferenceRevisionsProcessor extends LingotekEntityReferenceRevisionsProcessor implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return 'cohesion_entity_reference_revisions' === $field_definition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    $cohesionLayoutTranslatable = $field_definition->isTranslatable();
    $target_entity_type_id = $field_definition->getFieldStorageDefinition()
      ->getSetting('target_type');
    if ($cohesionLayoutTranslatable) {
      $translation->{$field_name} = NULL;
    }
    $delta = 0;
    $fieldValues = [];
    foreach ($field_data as $index => $field_item) {
      $embedded_entity_id = $revision->get($field_name)->get($index)
        ->get('target_id')
        ->getValue();
      /** @var \Drupal\Core\Entity\RevisionableInterface $embedded_entity */
      $embedded_entity = $this->entityTypeManager->getStorage($target_entity_type_id)
        ->load($embedded_entity_id);
      if ($embedded_entity !== NULL) {
        $this->lingotekContentTranslation->saveTargetData($embedded_entity, $langcode, $field_item);
        // Now the embedded entity is saved, but we need to ensure
        // the reference will be saved too. Ensure it's the same revision.
        $fieldValues[$delta] = ['target_id' => $embedded_entity->id(), 'target_revision_id' => $embedded_entity->getRevisionId()];
        $delta++;
      }
    }
    // If the cohesion layout was not translatable, we avoid at all costs to modify the field,
    // as this will override the source and may have unintended consequences.
    if ($cohesionLayoutTranslatable) {
      $translation->set($field_name, $fieldValues);
    }
  }

}
