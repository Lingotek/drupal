<?php

namespace Drupal\lingotek\EventSubscriber;

use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent;

class DepCalcExcludeLingotekContentMetadataSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // DependencyCalculatorEvents::FILTER_FIELDS from depcalc module.
    $events['depcalc_filter_fields'][] = [
      'onFilterFields',
      1002,
    ];
    return $events;
  }

  /**
   * Filter fields.
   *
   * @param \Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent $event
   *   Filter Dependency Calculation Fields.
   */
  public function onFilterFields(FilterDependencyCalculationFieldsEvent $event) {
    $fields = array_filter($event->getFields(), function ($field) {
      return $this->includeField($field);
    }, ARRAY_FILTER_USE_BOTH);

    $event->setFields(...$fields);
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
