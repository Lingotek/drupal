<?php

namespace Drupal\lingotek\Element;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Trait for lingotek_target_status and lingotek_target_statuses reuse.
 */
trait LingotekTargetTrait {

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
      if ($target_status == Lingotek::STATUS_DISABLED) {
        $url = NULL;
      }
    }
    return $url;
  }

  /**
   * Get secondary target actions, which will be shown when expanded.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $target_status
   *   The target status.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of links.
   */
  protected function getSecondaryTargetActionUrls(ContentEntityInterface &$entity, $target_status, $langcode) {
    $url = NULL;
    $document_id = \Drupal::service('lingotek.content_translation')->getDocumentId($entity);
    $locale = \Drupal::service('lingotek.language_locale_mapper')->getLocaleForLangcode($langcode);
    $langcode_upper = strtoupper($langcode);
    $actions = [];
    if ($document_id) {
      if ($target_status == Lingotek::STATUS_REQUEST) {
        $actions[] = [
          'title' => $this->t('Request translation'),
          'url' => Url::fromRoute('lingotek.entity.request_translation', [
            'doc_id' => $document_id,
            'locale' => $locale,
          ], ['query' => $this->getDestinationWithQueryArray()]),
        ];
      }
      if ($target_status == Lingotek::STATUS_PENDING) {
        $actions[] = [
          'title' => $this->t('Check translation status'),
          'url' => Url::fromRoute('lingotek.entity.check_target',
            [
              'doc_id' => $document_id,
              'locale' => $locale,
            ],
            ['query' => $this->getDestinationWithQueryArray()]),
          'new_window' => FALSE,
        ];
        $actions[] = [
          'title' => $this->t('Open in Lingotek Workbench'),
          'url' => Url::fromRoute('lingotek.workbench', [
            'doc_id' => $document_id,
            'locale' => $locale,
          ]),
          'new_window' => TRUE,
        ];
      }
      if ($target_status == Lingotek::STATUS_READY) {
        $actions[] = [
          'title' => $this->t('Download translation'),
          'url' => Url::fromRoute('lingotek.entity.download',
            [
              'doc_id' => $document_id,
              'locale' => $locale,
            ],
            ['query' => $this->getDestinationWithQueryArray()]),
          'new_window' => FALSE,
        ];
        $actions[] = [
          'title' => $this->t('Open in Lingotek Workbench'),
          'url' => Url::fromRoute('lingotek.workbench', [
            'doc_id' => $document_id,
            'locale' => $locale,
          ]),
          'new_window' => TRUE,
        ];
        // TODO add url for disassociate.
      }
      if ($target_status == Lingotek::STATUS_ERROR) {
        $actions[] = [
          'title' => $this->t('Retry request'),
          'url' => Url::fromRoute('lingotek.entity.request_translation',
            [
              'doc_id' => $document_id,
              'locale' => $locale,
            ],
            ['query' => $this->getDestinationWithQueryArray()]),
          'new_window' => FALSE,
        ];
        $actions[] = [
          'title' => $this->t('Retry download'),
          'url' => Url::fromRoute('lingotek.entity.download',
            [
              'doc_id' => $document_id,
              'locale' => $locale,
            ],
            ['query' => $this->getDestinationWithQueryArray()]),
          'new_window' => FALSE,
        ];

      }
      if ($target_status == Lingotek::STATUS_CURRENT) {
        if ($entity->hasLinkTemplate('canonical') && $entity->hasTranslation($langcode)) {
          $actions[] = [
            'title' => $this->t('View translation'),
            'url' => $entity->getTranslation($langcode)->toUrl(),
            'new_window' => FALSE,
          ];
        }
        $actions[] = [
          'title' => $this->t('Open in Lingotek Workbench'),
          'url' => Url::fromRoute('lingotek.workbench', [
            'doc_id' => $document_id,
            'locale' => $locale,
          ]),
          'new_window' => TRUE,
        ];
      }
      if ($target_status == Lingotek::STATUS_INTERMEDIATE) {
        if ($entity->hasLinkTemplate('canonical') && $entity->hasTranslation($langcode)) {
          $actions[] = [
            'title' => $this->t('View translation'),
            'url' => $entity->getTranslation($langcode)->toUrl(),
            'new_window' => FALSE,
          ];
        }
        $actions[] = [
          'title' => $this->t('Open in Lingotek Workbench'),
          'url' => Url::fromRoute('lingotek.workbench', [
            'doc_id' => $document_id,
            'locale' => $locale,
          ]),
          'new_window' => TRUE,
        ];
      }
      if ($target_status == Lingotek::STATUS_EDITED) {
        if ($entity->hasLinkTemplate('canonical') && $entity->hasTranslation($langcode)) {
          $actions[] = [
            'title' => $this->t('View translation'),
            'url' => $entity->getTranslation($langcode)->toUrl(),
            'new_window' => FALSE,
          ];
        }
        $actions[] = [
          'title' => $this->t('Open in Lingotek Workbench'),
          'url' => Url::fromRoute('lingotek.workbench', [
            'doc_id' => $document_id,
            'locale' => $locale,
          ]),
          'new_window' => TRUE,
        ];
      }
    }
    if ($target_status == Lingotek::STATUS_UNTRACKED) {
      if ($entity->hasLinkTemplate('canonical') && $entity->hasTranslation($langcode)) {
        $actions[] = [
          'title' => $this->t('View translation'),
          'url' => $entity->getTranslation($langcode)->toUrl(),
          'new_window' => FALSE,
        ];
      }
      if ($document_id) {
        $actions[] = [
          'title' => $this->t('Request translation'),
          'url' => Url::fromRoute('lingotek.entity.request_translation',
            [
              'doc_id' => $document_id,
              'locale' => $locale,
            ],
            ['query' => $this->getDestinationWithQueryArray()]),
          'new_window' => FALSE,
        ];
      }
    }

    return $actions;
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

  /**
   * Get the target status label.
   *
   * @param string $status
   *   The target status.
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   The source status human-friendly label.
   */
  protected function getTargetStatusText($status, $langcode) {
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

      case Lingotek::STATUS_CANCELLED:
        return $language->label() . ' - ' . $this->t('Cancelled by user');

      default:
        return $language->label() . ' - ' . ucfirst(strtolower($status));
    }
  }

}
