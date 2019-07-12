<?php

namespace Drupal\lingotek;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\Plugin\Field\LingotekContentMetadataFieldItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LingotekContentTranslationHandler implements LingotekContentTranslationHandlerInterface, EntityHandlerInterface {
  use DependencySerializationTrait;

  /**
   * The type of the entity being translated.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Information about the entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The array of installed field storage definitions for the entity type, keyed
   * by field name.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   */
  protected $fieldStorageDefinitions;

  /**
   * Initializes an instance of the content translation controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The info array of the given entity type.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository
   *   The entity last installed schema repository.
   */
  public function __construct(EntityTypeInterface $entity_type, LanguageManagerInterface $language_manager, EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository) {
    $this->entityTypeId = $entity_type->id();
    $this->entityType = $entity_type;
    $this->languageManager = $language_manager;
    $this->fieldStorageDefinitions = $entity_last_installed_schema_repository->getLastInstalledFieldStorageDefinitions($this->entityTypeId);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('language_manager'),
      $container->get('entity.last_installed_schema.repository')
    );
  }

  public function getFieldDefinitions() {
    $definitions = [];
    $definitions['lingotek_metadata'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Lingotek metadata'))
      ->setComputed(TRUE)
      ->setClass(LingotekContentMetadataFieldItemList::class)
      ->setDescription(t('The Lingotek profile defining this translation.'))
      ->setSetting('target_type', 'lingotek_content_metadata')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setReadOnly(FALSE);
    return $definitions;
  }

}
