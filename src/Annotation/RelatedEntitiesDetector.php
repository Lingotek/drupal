<?php

namespace Drupal\lingotek\Annotation;

use Drupal\Component\Annotation\AnnotationBase;

/**
 * Defines a RelatedEntitiesDetector annotation object
 *
 * @Annotation
 */
class RelatedEntitiesDetector extends AnnotationBase {

  /**
   * The plugin ID
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the RelatedEntitiesDetector type
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The human-readable description of the RelatedEntitiesDetector type
   *
   * @ingroup_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * The plugin weight
   *
   * @var int
   */
  public $weight;

  /**
   * {@inheritdoc}
   */
  public function get() {
    return [
      'id' => $this->id,
      'title' => $this->title,
      'description' => $this->description,
      'weight' => $this->weight,
      'class' => $this->class,
      'provider' => $this->provider,
    ];
  }

}
