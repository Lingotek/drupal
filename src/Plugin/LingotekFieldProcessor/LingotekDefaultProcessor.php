<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\lingotek\Exception\LingotekContentEntityFieldTooLongStorageException;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "default",
 *   weight = 0,
 * )
 */
class LingotekDefaultProcessor extends PluginBase implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Constructs a new UploadToLingotekAction action.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LingotekConfigurationServiceInterface $lingotek_configuration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lingotek.configuration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = []) {
    // If there is only one relevant attribute, upload it.
    // Get the column translatability configuration.
    module_load_include('inc', 'content_translation', 'content_translation.admin');
    $column_element = content_translation_field_sync_widget($field_definition);
    $field = $entity->get($field_name);
    $field_type = $field_definition->getType();
    $storage_definition = $field_definition->getFieldStorageDefinition();

    if ($field->isEmpty()) {
      $data[$field_name] = [];
    }
    foreach ($field as $fkey => $fval) {
      // If we have only one relevant column, upload that. If not, check our
      // settings.
      if (!$column_element) {
        $properties = $fval->getProperties();
        foreach ($properties as $property_name => $property_value) {
          if (isset($storage_definition)) {
            $property_definition = $storage_definition->getPropertyDefinition($property_name);
            $data_type = $property_definition->getDataType();
            if (($data_type === 'string' || $data_type === 'uri') && !$property_definition->isComputed()) {
              if (isset($fval->$property_name) && !empty($fval->$property_name)) {
                $data[$field_name][$fkey][$property_name] = $fval->get($property_name)
                  ->getValue();
              }
              // If there is a path item, we need to handle that the pid is a
              // string but we don't want to upload it. See
              // https://www.drupal.org/node/2689253.
              // TODO: Create a path one.
              if ($field_type === 'path') {
                unset($data[$field_name][$fkey]['pid']);
              }
            }
          }
        }
      }
      else {
        $configured_properties = $this->lingotekConfiguration->getFieldPropertiesLingotekEnabled($entity->getEntityTypeId(), $entity->bundle(), $field_name);
        $properties = $fval->getProperties();
        foreach ($properties as $pkey => $pval) {
          if (isset($configured_properties[$pkey]) && $configured_properties[$pkey]) {
            $data[$field_name][$fkey][$pkey] = $pval->getValue();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    // Initialize delta in case there are no items in $field_data.
    $delta = -1;
    // Save regular fields.
    foreach ($field_data as $delta => $delta_data) {
      foreach ($delta_data as $property => $property_data) {
        $property_definition = $field_definition->getFieldStorageDefinition()->getPropertyDefinition($property);
        $data_type = $property_definition->getDataType();
        if ($data_type === 'uri') {
          // Validate an uri.
          if (!\Drupal::pathValidator()->isValid($property_data)) {
            \Drupal::logger('lingotek')->warning('Field %field for %type %label in language %langcode not saved, invalid uri "%uri"',
              ['%field' => $field_name, '%type' => $translation->getEntityTypeId(), '%label' => $translation->label(), '%langcode' => $langcode, '%uri' => $property_data]);
            // Let's default to the original value given that there was a problem.
            $property_data = $revision->get($field_name)->offsetGet($delta)->{$property};
          }
        }
        if ($data_type === 'string' && $maxLength = $field_definition->getSetting('max_length')) {
          if (mb_strlen($property_data) > $maxLength) {
            throw new LingotekContentEntityFieldTooLongStorageException($revision, $field_definition->getName());
          }
        }
        if ($translation->get($field_name)->offsetExists($delta) && method_exists($translation->get($field_name)->offsetGet($delta), "set")) {
          $translation->get($field_name)->offsetGet($delta)->set($property, html_entity_decode($property_data));
        }
        elseif ($translation->get($field_name)) {
          $translation->get($field_name)->appendItem()->set($property, html_entity_decode($property_data));
        }
      }
    }

    // Remove the rest of deltas that were no longer found in the document downloaded from lingotek.
    $continue = TRUE;
    while ($continue) {
      if ($translation->get($field_name)->offsetExists($delta + 1)) {
        $translation->get($field_name)->removeItem($delta + 1);
      }
      else {
        $continue = FALSE;
      }
    }
  }

}
