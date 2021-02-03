<?php

namespace Drupal\lingotek\Element;

use Drupal\Core\Render\Element\RenderElement;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Provides a Lingotek target status element.
 *
 * @RenderElement("lingotek_target_status")
 */
class LingotekTargetStatus extends RenderElement {

  use LingotekTargetTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [$this, 'preRender'],
      ],
      '#theme' => 'lingotek_target_status',
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
   * Calculates the url and status title and adds them to the render array.
   *
   * @param array $element
   *   The element as a render array.
   *
   * @return array
   *   The element as a render array.
   */
  public function preRender(array $element) {
    $isSourceLanguage = $element['#entity']->language()->getId() === $element['#language'];
    if ($isSourceLanguage) {
      return [];
    }
    if (NULL === ConfigurableLanguage::load($element['#language'])) {
      return [];
    }
    $element['#url'] = $this->getTargetActionUrl($element['#entity'], $element['#status'], $element['#language']);
    $element['#new_window'] = !($element['#entity']->hasTranslation($element['#language']) && $element['#status'] == Lingotek::STATUS_REQUEST) && in_array($element['#status'], [Lingotek::STATUS_CURRENT, Lingotek::STATUS_INTERMEDIATE, Lingotek::STATUS_EDITED]);
    $element['#status_text'] = $this->getTargetStatusText($element['#status'], $element['#language']);
    $element['#actions'] = $this->getSecondaryTargetActionUrls($element['#entity'], $element['#status'], $element['#language']);
    return $element;
  }

}
