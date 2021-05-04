<?php

namespace Drupal\lingotek\EventSubscriber;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AcquiaContentHubExcludeLingotekContentMetadataFromSerializationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD
    $events['serialize_content_entity_field'][] = [
      'onSerializeContentField',
      1001,
    ];
    return $events;
  }

  /**
   * Serialize content field event.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   Serialized CDF Entity Field event.
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    $field = $event->getField();
    if (!$this->includeField($field)) {
      $event->setExcluded();
      $event->stopPropagation();
    }
  }

  /**
   * Whether we should include this field in the dependency calculation.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The entity field.
   *
   * @return bool
   *   TRUE if we should include the field, FALSE otherwise.
   */
  protected function includeField(FieldItemListInterface $field) {
    $definition = $field->getFieldDefinition();
    if ($definition->getType() === 'entity_reference' && $field->getSetting('target_type') === 'lingotek_content_metadata') {
      return FALSE;
    }
    return TRUE;
  }

}
