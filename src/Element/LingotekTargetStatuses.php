<?php

namespace Drupal\lingotek\Element;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigFieldMapper;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Provides a Lingotek target status element.
 *
 * @RenderElement("lingotek_target_statuses")
 */
class LingotekTargetStatuses extends RenderElement {

  use LingotekTargetTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [$this, 'preRender'],
      ],
      '#theme' => 'lingotek_target_statuses',
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
    $statuses = NULL;
    if (isset($element['#entity'])) {
      $statuses = $this->getTranslationsStatuses($element['#entity'], $element['#source_langcode'], $element['#statuses']);
    }
    if (isset($element['#mapper'])) {
      $statuses = $this->getTranslationsStatusesForConfigMapper($element['#mapper'], $element['#source_langcode'], $element['#statuses']);
    }
    elseif (isset($element['#ui_component'])) {
      $statuses = $this->getTranslationsStatusesForUI($element['#ui_component'], $element['#source_langcode'], $element['#statuses']);
    }
    $element['#statuses'] = $statuses;
    return $element;
  }

  /**
   * Gets the translation status of an entity in a format ready to display.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $source_langcode
   *   The source language code.
   * @param array $statuses
   *   Array of known statuses keyed by language code.
   *
   * @return array
   *   A render array.
   */
  protected function getTranslationsStatuses(ContentEntityInterface &$entity, $source_langcode, array $statuses) {
    $translations = [];
    $languages = \Drupal::languageManager()->getLanguages();
    $languages = array_filter($languages, function (LanguageInterface $language) {
      $configLanguage = ConfigurableLanguage::load($language->getId());
      return \Drupal::service('lingotek.configuration')->isLanguageEnabled($configLanguage);
    });
    foreach ($statuses as $langcode => $status) {
      if ($langcode !== $source_langcode && array_key_exists($langcode, $languages)) {
        // We may have an existing translation already.
        if ($entity->hasTranslation($langcode) && $status == Lingotek::STATUS_REQUEST) {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_UNTRACKED,
            'url' => $this->getTargetActionUrl($entity, Lingotek::STATUS_UNTRACKED, $langcode),
            'actions' => $this->getSecondaryTargetActionUrls($entity, Lingotek::STATUS_UNTRACKED, $langcode),
            'new_window' => FALSE,
          ];
        }
        else {
          $translations[$langcode] = [
            'status' => $status,
            'url' => $this->getTargetActionUrl($entity, $status, $langcode),
            'actions' => $this->getSecondaryTargetActionUrls($entity, $status, $langcode),
            'new_window' => in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_INTERMEDIATE, Lingotek::STATUS_EDITED]),
          ];
        }
      }
      array_walk($languages, function ($language, $langcode) use ($entity, &$translations) {
        if (!isset($translations[$langcode]) &&
            $langcode !== $entity->getUntranslated()->language()->getId()) {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_REQUEST,
            'url' => $this->getTargetActionUrl($entity, Lingotek::STATUS_REQUEST, $langcode),
            'actions' => $this->getSecondaryTargetActionUrls($entity, Lingotek::STATUS_REQUEST, $langcode),
            'new_window' => FALSE,
          ];
        }
      });
    }
    foreach ($languages as $langcode => $language) {
      // Show the untracked translations in the bulk management form, unless it's the
      // source one.
      if (!isset($translations[$langcode]) && $entity->hasTranslation($langcode) && $source_langcode !== $langcode) {
        $translations[$langcode] = [
          'status' => Lingotek::STATUS_UNTRACKED,
          'url' => NULL,
          'actions' => $this->getSecondaryTargetActionUrls($entity, Lingotek::STATUS_UNTRACKED, $langcode),
          'new_window' => FALSE,
        ];
      }
    }
    ksort($translations);
    foreach ($translations as $langcode => &$translation) {
      $translation['status_text'] = $this->getTargetStatusText($translation['status'], $langcode);
      $translation['language'] = $langcode;
    }
    return $translations;
  }

  protected function getTranslationsStatusesForConfigMapper(ConfigMapperInterface &$mapper, $source_langcode, array $statuses) {
    $translations = [];
    foreach ($statuses as $langcode => &$status) {
      $status['actions'] = $this->getSecondaryTargetActionUrlsForConfigMapper($mapper, $status['status'], $status['language']);
    }
    return $statuses;
    $languages = \Drupal::languageManager()->getLanguages();
    $languages = array_filter($languages, function (LanguageInterface $language) {
      $configLanguage = ConfigurableLanguage::load($language->getId());
      return \Drupal::service('lingotek.configuration')->isLanguageEnabled($configLanguage);
    });
    foreach ($statuses as $langcode => $status) {
      if ($langcode !== $source_langcode && array_key_exists($langcode, $languages)) {
        // We may have an existing translation already.
        if ($mapper instanceof ConfigEntityMapper && $mapper->getEntity()->hasTranslation($langcode) && $status == Lingotek::STATUS_REQUEST) {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_UNTRACKED,
            'url' => $this->getTargetActionUrlForConfigMapper($mapper, Lingotek::STATUS_UNTRACKED, $langcode),
            'actions' => $this->getSecondaryTargetActionUrlsForConfigMapper($mapper, Lingotek::STATUS_UNTRACKED, $langcode),
            'new_window' => FALSE,
          ];
        }
        else {
          $translations[$langcode] = [
            'status' => $status,
            'url' => $this->getTargetActionUrlForConfigMapper($mapper, $status, $langcode),
            'actions' => $this->getSecondaryTargetActionUrlsForConfigMapper($mapper, $status, $langcode),
            'new_window' => in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_INTERMEDIATE, Lingotek::STATUS_EDITED]),
          ];
        }
      }
      array_walk($languages, function ($language, $langcode) use ($mapper, &$translations) {
        if ($mapper instanceof ConfigEntityMapper) {
          if (!isset($translations[$langcode]) &&
            $langcode !== $mapper->getEntity()
              ->getUntranslated()
              ->language()
              ->getId()) {
            $translations[$langcode] = [
              'status' => Lingotek::STATUS_REQUEST,
              'url' => $this->getTargetActionUrlForConfigMapper($mapper, Lingotek::STATUS_REQUEST, $langcode),
              'actions' => $this->getSecondaryTargetActionUrlsForConfigMapper($mapper, Lingotek::STATUS_REQUEST, $langcode),
              'new_window' => FALSE,
            ];
          }
        }
      });
    }
    foreach ($languages as $langcode => $language) {
      // Show the untracked translations in the bulk management form, unless it's the
      // source one.
      if (!isset($translations[$langcode]) && $mapper instanceof ConfigEntityMapper && $mapper->getEntity()->hasTranslation($langcode) && $source_langcode !== $langcode) {
        $translations[$langcode] = [
          'status' => Lingotek::STATUS_UNTRACKED,
          'url' => NULL,
          'actions' => $this->getSecondaryTargetActionUrlsForConfigMapper($mapper, Lingotek::STATUS_UNTRACKED, $langcode),
          'new_window' => FALSE,
        ];
      }
    }
    ksort($translations);
    foreach ($translations as $langcode => &$translation) {
      $translation['status_text'] = $this->getTargetStatusText($translation['status'], $langcode);
      $translation['language'] = $langcode;
    }
    return $translations;
  }

  protected function getTranslationsStatusesForUI($component, $source_langcode, array $statuses) {
    $translations = [];
    $languages = \Drupal::languageManager()->getLanguages();
    $languages = array_filter($languages, function (LanguageInterface $language) {
      $configLanguage = ConfigurableLanguage::load($language->getId());
      return \Drupal::service('lingotek.configuration')->isLanguageEnabled($configLanguage);
    });
    foreach ($statuses as $langcode => $status) {
      if ($langcode !== $source_langcode && array_key_exists($langcode, $languages)) {
        // We may have an existing translation already.
        $translations[$langcode] = [
          'status' => $status,
          'url' => $this->getTargetActionUrlForUI($component, $status, $langcode),
          'new_window' => in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_INTERMEDIATE, Lingotek::STATUS_EDITED]),
        ];
      }
      array_walk($languages, function ($language, $langcode) use ($component, &$translations) {
        if (!isset($translations[$langcode]) &&
          $langcode !== 'en') {
          $translations[$langcode] = [
            'status' => Lingotek::STATUS_REQUEST,
            'url' => $this->getTargetActionUrlForUI($component, Lingotek::STATUS_REQUEST, $langcode),
            'new_window' => FALSE,
          ];
        }
      });
    }
    ksort($translations);
    foreach ($translations as $langcode => &$translation) {
      $translation['status_text'] = $this->getTargetStatusText($translation['status'], $langcode);
      $translation['language'] = $langcode;
    }
    return $translations;
  }

  protected function getTargetActionUrlForUI($component, $target_status, $langcode) {
    $url = NULL;
    $document_id = \Drupal::service('lingotek.interface_translation')
      ->getDocumentId($component);
    $locale = \Drupal::service('lingotek.language_locale_mapper')
      ->getLocaleForLangcode($langcode);
    if ($document_id) {
      if ($target_status == Lingotek::STATUS_REQUEST) {
        $url = Url::fromRoute('lingotek.interface_translation.request_translation', [],
          [
            'query' => [
                'component' => $component,
                'locale' => $locale,
              ] + $this->getDestinationWithQueryArray(),
          ]);
      }
      if ($target_status == Lingotek::STATUS_PENDING) {
        $url = Url::fromRoute('lingotek.interface_translation.check_translation', [],
          [
            'query' => [
                'component' => $component,
                'locale' => $locale,
              ] + $this->getDestinationWithQueryArray(),
          ]);
      }
      if ($target_status == Lingotek::STATUS_READY || $target_status == Lingotek::STATUS_ERROR) {
        $url = Url::fromRoute('lingotek.interface_translation.download', [],
          [
            'query' => [
                'component' => $component,
                'locale' => $locale,
              ] + $this->getDestinationWithQueryArray(),
          ]);
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
        $url = Url::fromRoute('lingotek.interface_translation.request_translation', [],
          [
            'query' => [
                'component' => $component,
                'locale' => $locale,
              ] + $this->getDestinationWithQueryArray(),
          ]);
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
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The entity.
   * @param string $target_status
   *   The target status.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of links.
   */
  protected function getSecondaryTargetActionUrlsForConfigMapper(ConfigMapperInterface &$mapper, $target_status, $langcode) {
    $url = NULL;
    $target_status = strtoupper($target_status);
    $language = \Drupal::languageManager()->getLanguage($langcode);
    $translationService = \Drupal::service('lingotek.config_translation');
    /** @var \Drupal\Core\Config\ConfigEntityInterface $entity */
    $entity = $mapper instanceof ConfigEntityMapper ? $mapper->getEntity() : NULL;
    $document_id = $mapper instanceof ConfigEntityMapper ?
      $translationService->getDocumentId($entity) :
      $translationService->getConfigDocumentId($mapper);
    $locale = \Drupal::service('lingotek.language_locale_mapper')->getLocaleForLangcode($language->getId());
    $langcode_upper = strtoupper($langcode);
    $language = \Drupal::languageManager()->getLanguage($langcode);
    $args = $this->getActionUrlArgumentsForConfigMapper($mapper);

    $actions = [];
    if ($document_id) {
      if ($target_status == Lingotek::STATUS_REQUEST) {
        $actions[] = [
          'title' => $this->t('Request translation'),
          'url' => Url::fromRoute('lingotek.config.request',
            $args + ['locale' => $locale],
            ['query' => $this->getDestinationWithQueryArray()]),
        ];
      }
      if ($target_status == Lingotek::STATUS_PENDING) {
        $actions[] = [
          'title' => $this->t('Check translation status'),
          'url' => Url::fromRoute('lingotek.config.check_download',
            $args + ['locale' => $locale],
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
          'url' => Url::fromRoute('lingotek.config.download',
            $args + ['locale' => $locale],
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
          'title' => $this->t('Retry'),
          'url' => Url::fromRoute('lingotek.config.request',
            $args + ['locale' => $locale],
            ['query' => $this->getDestinationWithQueryArray()]),
          'new_window' => FALSE,
        ];
      }
      if ($target_status == Lingotek::STATUS_CURRENT) {
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
      if ($document_id) {
        $actions[] = [
          'title' => $this->t('Request translation'),
          'url' => Url::fromRoute('lingotek.config.request',
            $args + ['locale' => $locale],
            ['query' => $this->getDestinationWithQueryArray()]),
          'new_window' => FALSE,
        ];
      }
    }

    return $actions;
  }

  protected function getActionUrlArgumentsForConfigMapper(ConfigMapperInterface &$mapper) {
    $args = [
      'entity_type' => $mapper->getPluginId(),
      'entity_id' => $mapper->getPluginId(),
    ];
    if ($mapper instanceof ConfigEntityMapper && !$mapper instanceof ConfigFieldMapper) {
      $args['entity_id'] = $mapper->getEntity()->id();
    }
    elseif ($mapper instanceof ConfigFieldMapper) {
      $args['entity_type'] = $mapper->getType();
      $args['entity_id'] = $mapper->getEntity()->id();
    }
    return $args;
  }

}
