<?php

namespace Drupal\lingotek\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Base class for form-component plugins.
 */
abstract class LingotekFormComponentBase extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The group the plugin belongs to.
   *
   * @var string|\Drupal\Core\Annotation\Translation
   */
  public $group;

  /**
   * The plugin's weight.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * The form IDs the plugin applies to.
   *
   * @var array
   */
  public $form_ids = [];

  /**
   * The entity type IDs the plugin applies to.
   *
   * @var array
   */
  public $entity_types = [];

}
