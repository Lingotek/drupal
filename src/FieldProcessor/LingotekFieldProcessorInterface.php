<?php

namespace Drupal\lingotek\FieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\lingotek\LingotekContentTranslationEntityRevisionResolver;

/**
 * A Lingotek field processor extracts data for upload and stores it on download.
 */
interface LingotekFieldProcessorInterface {

  /**
   * Check if this processor applies to a given field name.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity to process.
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity);

  /**
   * Extract data for the given field in the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity to extract data from.
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $data
   *   The data being extracted.
   * @param array $visited
   *   We register the entities already extracted, avoiding infinite cycles.
   * @param string $revision_mode
   *   The mode to use for resolving the revision.
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = [], string $revision_mode = LingotekContentTranslationEntityRevisionResolver::RESOLVE_LATEST_TRANSLATION_AFFECTED);

  /**
   * Extract data for the given field in the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   Content entity to store data in.
   * @param string $langcode
   *   The langcode being saved.
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   Uploaded content entity revision as a reference.
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $field_data
   *   The field data for being stored.
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data);

}
