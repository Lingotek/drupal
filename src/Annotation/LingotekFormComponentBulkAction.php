<?php

namespace Drupal\lingotek\Annotation;

/**
 * Defines a LingotekFormComponentBulkAction annotation object.
 *
 * @Annotation
 */
class LingotekFormComponentBulkAction extends LingotekFormComponentAnnotationBase {

  /**
   * The options this bulk action can use.
   *
   * @var array
   */
  public $options = [];

  /**
   * The batch definition for this bulk action, if it uses one.
   * It has:
   *   - title: The title of the batch
   *   - function: if defined, it can override the default batch creation function.
   *
   * @var array
   */
  public $batch = [];

  /**
   * The route name to redirect after submit.
   *
   * @var string
   */
  public $redirect = '';

}
