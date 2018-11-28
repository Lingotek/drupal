<?php

namespace Drupal\lingotek\Element;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Provides a Lingotek target status element.
 *
 * @RenderElement("lingotek_target_status")
 */
class LingotekTargetStatus extends RenderElement {

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
    $element['#status_text'] = $this->getTargetStatusText($element['#entity'], $element['#status'], $element['#language']);
    return $element;
  }

  /**
   * Get the source status label.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $status
   *   The target status.
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   The source status human-friendly label.
   */
  protected function getTargetStatusText(ContentEntityInterface $entity, $status, $langcode) {
    $language = ConfigurableLanguage::load($langcode);

    switch ($status) {
      case Lingotek::STATUS_UNTRACKED:
        return $language->label() . ' - ' . $this->t('Translation exists, but it is not being tracked by Lingotek');

      case Lingotek::STATUS_REQUEST:
        return $language->label() . ' - ' . $this->t('Request translation');

      case Lingotek::STATUS_PENDING:
        return $language->label() . ' - ' . $this->t('In-progress');

      case Lingotek::STATUS_READY:
        return $language->label() . ' - ' . $this->t('Ready for Download');

      case Lingotek::STATUS_INTERMEDIATE:
        return $language->label() . ' - ' . $this->t('In-progress (interim translation downloaded)');

      case Lingotek::STATUS_CURRENT:
        return $language->label() . ' - ' . $this->t('Current');

      case Lingotek::STATUS_EDITED:
        return $language->label() . ' - ' . $this->t('Not current');

      case Lingotek::STATUS_ERROR:
        return $language->label() . ' - ' . $this->t('Error');

      default:
        return $language->label() . ' - ' . ucfirst(strtolower($status));
    }
  }

  /**
   * Get the target action url based on the source status.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $target_status
   *   The target status.
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Url
   *   An url object.
   */
  protected function getTargetActionUrl(ContentEntityInterface &$entity, $target_status, $langcode) {
    $url = NULL;
    $document_id = \Drupal::service('lingotek.content_translation')->getDocumentId($entity);
    $locale = \Drupal::service('lingotek.language_locale_mapper')->getLocaleForLangcode($langcode);
    if ($document_id) {
      if ($target_status == Lingotek::STATUS_REQUEST) {
        $url = Url::fromRoute('lingotek.entity.request_translation',
          [
            'doc_id' => $document_id,
            'locale' => $locale,
          ],
          ['query' => $this->getDestinationWithQueryArray()]);
      }
      if ($target_status == Lingotek::STATUS_PENDING) {
        $url = Url::fromRoute('lingotek.entity.check_target',
          [
            'doc_id' => $document_id,
            'locale' => $locale,
          ],
          ['query' => $this->getDestinationWithQueryArray()]);
      }
      if ($target_status == Lingotek::STATUS_READY || $target_status == Lingotek::STATUS_ERROR) {
        $url = Url::fromRoute('lingotek.entity.download',
          [
            'doc_id' => $document_id,
            'locale' => $locale,
          ],
          ['query' => $this->getDestinationWithQueryArray()]);
      }
      if ($target_status == Lingotek::STATUS_CURRENT ||
        $target_status == Lingotek::STATUS_INTERMEDIATE ||
        $target_status == Lingotek::STATUS_EDITED) {
        $url = Url::fromRoute('lingotek.workbench', [
          'doc_id' => $document_id,
          'locale' => $locale,
        ]);
      }
      if ($target_status == Lingotek::STATUS_UNTRACKED) {
        $url = Url::fromRoute('lingotek.entity.request_translation',
          [
            'doc_id' => $document_id,
            'locale' => $locale,
          ],
          ['query' => $this->getDestinationWithQueryArray()]);
      }
    }
    return $url;
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
