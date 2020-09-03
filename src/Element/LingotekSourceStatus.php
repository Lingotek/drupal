<?php

namespace Drupal\lingotek\Element;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Url;
use Drupal\lingotek\Lingotek;

/**
 * Provides a Lingotek source status element.
 *
 * @RenderElement("lingotek_source_status")
 */
class LingotekSourceStatus extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [$this, 'preRender'],
      ],
      '#theme' => 'lingotek_source_status',
      '#attached' => [
        'library' => [
          'lingotek/lingotek',
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
    if (isset($element['#entity'])) {
      $element['#url'] = $this->getSourceActionUrl($element['#entity'], $element['#status']);
      $element['#status_title'] = $this->getSourceStatusText($element['#entity'], $element['#status']);
    }
    elseif (isset($element['#ui_component'])) {
      $element['#url'] = $this->getSourceActionUrlForUI($element['#ui_component'], $element['#status']);
      $element['#status_title'] = $this->getSourceStatusTextForUI($element['#ui_component'], $element['#status']);
    }
    return $element;
  }

  /**
   * Get the source action url based on the source status.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $source_status
   *   The source status.
   *
   * @return \Drupal\Core\Url
   *   An url object.
   */
  protected function getSourceActionUrl(ContentEntityInterface &$entity, $source_status) {
    $url = NULL;
    if ($source_status == Lingotek::STATUS_IMPORTING) {
      $url = Url::fromRoute('lingotek.entity.check_upload',
        ['doc_id' => \Drupal::service('lingotek.content_translation')->getDocumentId($entity)],
        ['query' => $this->getDestinationWithQueryArray()]);
    }
    if ($source_status == Lingotek::STATUS_EDITED || $source_status == Lingotek::STATUS_UNTRACKED || $source_status == Lingotek::STATUS_ERROR || $source_status == Lingotek::STATUS_CANCELLED) {
      if ($doc_id = \Drupal::service('lingotek.content_translation')->getDocumentId($entity)) {
        $url = Url::fromRoute('lingotek.entity.update',
          ['doc_id' => $doc_id],
          ['query' => $this->getDestinationWithQueryArray()]);
      }
      else {
        $url = Url::fromRoute('lingotek.entity.upload',
          [
            'entity_type' => $entity->getEntityTypeId(),
            'entity_id' => $entity->id(),
          ],
          ['query' => $this->getDestinationWithQueryArray()]);
      }
    }
    return $url;
  }

  protected function getSourceActionUrlForUI($component, $source_status) {
    $url = NULL;
    if ($source_status == Lingotek::STATUS_IMPORTING) {
      $url = Url::fromRoute('lingotek.interface_translation.check_upload', [],
        ['query' => ['component' => $component] + $this->getDestinationWithQueryArray()]);
    }
    if ($source_status == Lingotek::STATUS_EDITED || $source_status == Lingotek::STATUS_UNTRACKED || $source_status == Lingotek::STATUS_ERROR || $source_status == Lingotek::STATUS_CANCELLED) {
      if ($doc_id = \Drupal::service('lingotek.interface_translation')->getDocumentId($component)) {
        $url = Url::fromRoute('lingotek.interface_translation.update', [],
          ['query' => ['component' => $component] + $this->getDestinationWithQueryArray()]);
      }
      else {
        $url = Url::fromRoute('lingotek.interface_translation.upload', [],
          ['query' => ['component' => $component] + $this->getDestinationWithQueryArray()]);
      }
    }
    return $url;
  }

  /**
   * Get the source status label.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $source_status
   *   The source status
   *
   * @return string
   *   The source status human-friendly label.
   */
  protected function getSourceStatusText(ContentEntityInterface $entity, $source_status) {
    switch ($source_status) {
      case Lingotek::STATUS_UNTRACKED:
      case Lingotek::STATUS_REQUEST:
        return t('Upload');

      case Lingotek::STATUS_DISABLED:
        return t('Disabled, cannot request translation');

      case Lingotek::STATUS_EDITED:
        return (\Drupal::service('lingotek.content_translation')->getDocumentId($entity)) ?
          t('Re-upload (content has changed since last upload)') : t('Upload');

      case Lingotek::STATUS_IMPORTING:
        return t('Source importing');

      case Lingotek::STATUS_CURRENT:
        return t('Source uploaded');

      case Lingotek::STATUS_ERROR:
        return t('Error');

      case Lingotek::STATUS_CANCELLED:
        return $this->t('Cancelled by user');

      default:
        return ucfirst(strtolower($source_status));
    }
  }

  protected function getSourceStatusTextForUI($component, $source_status) {
    switch ($source_status) {
      case Lingotek::STATUS_UNTRACKED:
      case Lingotek::STATUS_REQUEST:
        return t('Upload');

      case Lingotek::STATUS_DISABLED:
        return t('Disabled, cannot request translation');

      case Lingotek::STATUS_EDITED:
        return (\Drupal::service('lingotek.interface_translation')->getDocumentId($component)) ?
          t('Re-upload (content has changed since last upload)') : t('Upload');

      case Lingotek::STATUS_IMPORTING:
        return t('Source importing');

      case Lingotek::STATUS_CURRENT:
        return t('Source uploaded');

      case Lingotek::STATUS_ERROR:
        return t('Error');

      case Lingotek::STATUS_CANCELLED:
        return $this->t('Cancelled by user');

      default:
        return ucfirst(strtolower($source_status));
    }
  }

  /**
   * Get a destination query with the current uri.
   *
   * @return array
   *   A valid query array for the Url construction.
   */
  protected function getDestinationWithQueryArray() {
    return ['destination' => \Drupal::request()->getRequestUri()];
  }

}
