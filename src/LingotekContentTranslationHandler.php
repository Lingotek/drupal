<?php

namespace Drupal\lingotek;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
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
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeInterface $entity_type, LanguageManagerInterface $language_manager, EntityManagerInterface $entity_manager) {
    $this->entityTypeId = $entity_type->id();
    $this->entityType = $entity_type;
    $this->languageManager = $language_manager;
    $this->fieldStorageDefinitions = $entity_manager->getLastInstalledFieldStorageDefinitions($this->entityTypeId);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('language_manager'),
      $container->get('entity.manager')
    );
  }

  public function getFieldDefinitions() {
    $definitions = [];

    // ToDo: Remove these when possible. See https://www.drupal.org/node/2859665
    // We need to keep these until we can purge data.
    // See https://www.drupal.org/node/2282119.
    $definitions['lingotek_document_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Lingotek document id'))
      ->setDescription(t('The Lingotek document id.'));

    $definitions['lingotek_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Lingotek hash'))
      ->setDescription(t('A hash of the Lingotek saved entity data, required for checking for changes.'));

    $definitions['lingotek_profile'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Lingotek profile'))
      ->setDescription(t('The Lingotek profile defining this translation.'))
      ->setSetting('target_type', 'lingotek_profile');

    $definitions['lingotek_translation_source'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Lingotek translation source'))
      ->setDescription(t('The source language from which this translation was created.'))
      ->setDefaultValue(LanguageInterface::LANGCODE_NOT_SPECIFIED)
      ->setTranslatable(TRUE);

    $definitions['lingotek_translation_status'] = BaseFieldDefinition::create('lingotek_language_key_value')
      ->setLabel(t('Lingotek translation status'))
      ->setDescription(t('The status of the source in case of being the source translation, or the status of the translation.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    if (!$this->hasCreatedTime()) {
      $definitions['lingotek_translation_created'] = BaseFieldDefinition::create('created')
        ->setLabel(t('Lingotek translation created time'))
        ->setDescription(t('The Unix timestamp when the translation was created.'))
        ->setTranslatable(TRUE);
    }

    if (!$this->hasChangedTime()) {
      $definitions['lingotek_translation_changed'] = BaseFieldDefinition::create('changed')
        ->setLabel(t('Translation changed time'))
        ->setDescription(t('The Unix timestamp when the translation was most recently saved.'))
        ->setTranslatable(TRUE);
    }

    // This is the only field that is used. Removal of the others at
    // https://www.drupal.org/node/2859665
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

  /**
   * Checks whether the entity type supports creation time natively.
   *
   * @return bool
   *   TRUE if metadata is natively supported, FALSE otherwise.
   */
  protected function hasCreatedTime() {
    return $this->checkFieldStorageDefinitionTranslatability('created');
  }

  /**
   * Checks whether the entity type supports modification time natively.
   *
   * @return bool
   *   TRUE if metadata is natively supported, FALSE otherwise.
   */
  protected function hasChangedTime() {
    return $this->entityType->isSubclassOf('Drupal\Core\Entity\EntityChangedInterface') && $this->checkFieldStorageDefinitionTranslatability('changed');
  }

  /**
   * Checks the field storage definition for translatability support.
   *
   * Checks whether the given field is defined in the field storage definitions
   * and if its definition specifies it as translatable.
   *
   * @param string $field_name
   *   The name of the field.
   *
   * @return bool
   *   TRUE if translatable field storage definition exists, FALSE otherwise.
   */
  protected function checkFieldStorageDefinitionTranslatability($field_name) {
    return array_key_exists($field_name, $this->fieldStorageDefinitions) && $this->fieldStorageDefinitions[$field_name]->isTranslatable();
  }

}
