<?php

namespace Drupal\lingotek\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'lingotek_translation_statuses' formatter.
 *
 * @FieldFormatter(
 *   id = "lingotek_translation_statuses",
 *   label = @Translation("Lingotek translation statuses"),
 *   field_types = {
 *     "lingotek_language_key_value",
 *   }
 * )
 */
class LingotekTranslationStatusesFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

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
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $entity = $items->getEntity();
    if ($entity instanceof LingotekContentMetadata) {
      // $entity is the metadata of another entity. Let's get the source.
      $entity = \Drupal::entityTypeManager()->getStorage($entity->getContentEntityTypeId())->load($entity->getContentEntityId());
    }
    $statuses = [];
    foreach ($items as $delta => $item) {
      $value = $item->getValue();
      $langcode = $value['language'];
      $status = $value['value'];
      $statuses[$langcode] = $status;
    }

    return [
      '0' => [
        '#type' => 'lingotek_target_statuses',
        '#entity' => $entity,
        '#source_langcode' => $entity->language()->getId(),
        '#statuses' => $statuses,
      ],
      '#items' => [
        '0' => [
          '#type' => 'lingotek_target_statuses',
          '#entity' => $entity,
          '#source_langcode' => $entity->language()->getId(),
          '#statuses' => $statuses,
        ],
      ],
      '#attached' => [
        'library' => [
          'lingotek/lingotek',
          'lingotek/lingotek.target_actions',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getType() === 'lingotek_language_key_value';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // We need to implement this, but the ::view() method itself is hacking its
    // way around so this is never called.
    return FALSE;
  }

}
