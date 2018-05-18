<?php

namespace Drupal\lingotek\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'language_key_value' field type.
 *
 * @FieldType(
 *   id = "lingotek_language_key_value",
 *   label = @Translation("Language Key / Value"),
 *   description = @Translation("This field stores language keyed value pairs."),
 *   category = @Translation("Language Key / Value"),
 *   default_formatter = "lingotek_translation_status"
 * )
 */
class LanguageKeyValueItem extends StringItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    // Get the schema from the text field.
    $schema = parent::schema($field_definition);
    // Add an index for key.
    $schema['indexes']['language'] = ['language'];
    $schema['columns'] += [
      'language' => [
        'description' => 'Stores the "Language" value.',
        'type' => 'varchar_ascii',
        'length' => 12,
        'not null' => TRUE,
        'default' => '',
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['language'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Language Key'))
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $value = parent::generateSampleValue($field_definition);
    // ToDo: This should be really random.
    $value['language'] = self::getLangcode();
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Return TRUE if there is no key.
    return (!isset($this->values) || (empty($this->values['language'])));
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    if (is_object($this->values['language'])) {
      $this->values['language'] = $this->values['language']->getId();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (is_object($values['language'])) {
      $values['language'] = $values['language']->getId();
    }
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $values = parent::getValue();
    if (is_object($values['language'])) {
      $values['language'] = $values['language']->getId();
    }
    return $values;
  }

}
