<?php

namespace Drupal\lingotek\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'lingotek_translation_status' formatter.
 *
 * @FieldFormatter(
 *   id = "lingotek_translation_status",
 *   label = @Translation("Lingotek translation status"),
 *   field_types = {
 *     "lingotek_language_key_value",
 *   }
 * )
 */
class LingotekTranslationStatusFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entity = $items->getEntity();
    if ($entity instanceof LingotekContentMetadata) {
      // $entity is the metadata of another entity. Let's get the source.
      $entity = \Drupal::entityTypeManager()->getStorage($entity->getContentEntityTypeId())->load($entity->getContentEntityId());
    }

    $sourceLanguage = $entity->language()->getId();

    foreach ($items as $delta => $item) {
      $value = $item->getValue();
      $langcode = $value['language'];
      $status = $value['value'];
      if ($langcode !== $sourceLanguage) {
        $elements[$delta] = [
          '#type' => 'lingotek_target_status',
          '#entity' => $entity,
          '#language' => $langcode,
          '#status' => $status,
        ];
      }
    }
    $elements['#attached'] = [
      'library' => [
        'lingotek/lingotek',
        'lingotek/lingotek.target_actions',
      ],
    ];
    $elements['#cache'] = ['max-age' => 0];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getType() === 'lingotek_language_key_value';
  }

}
