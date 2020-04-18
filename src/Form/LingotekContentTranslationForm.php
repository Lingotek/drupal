<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LingotekContentTranslationForm extends LingotekConfigFormBase {

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekContentTranslationForm object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, ConfigFactoryInterface $config, UrlGeneratorInterface $url_generator = NULL, LinkGeneratorInterface $link_generator = NULL) {
    parent::__construct($lingotek, $config, $url_generator, $link_generator);
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('lingotek.configuration'),
      $container->get('config.factory'),
      $container->get('url_generator'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.content_translation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $build = NULL) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $entity = $build['#entity'];
    $entity_type = $entity->getEntityTypeId();

    $document_id = $translation_service->getDocumentId($entity);
    $source_language = $translation_service->getSourceLocale($entity);
    $source_status = $translation_service->getSourceStatus($entity);
    $status_check_needed = ($source_status == Lingotek::STATUS_EDITED) ? TRUE : FALSE;
    $targets_ready = FALSE;

    $form_state->set('entity', $entity);
    $overview = $build['content_translation_overview'];
    $form['#title'] = $this->t('Translations of @title', ['@title' => $entity->label()]);

    $form['languages'] = [
      '#type' => 'tableselect',
      '#header' => $overview['#header'],
      '#options' => [],
    ];

    $languages = \Drupal::languageManager()->getLanguages();

    foreach ($languages as $langcode => $language) {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);

      $option = array_shift($overview['#rows']);

      $configLanguage = ConfigurableLanguage::load($langcode);
      $enabled = $this->lingotekConfiguration->isLanguageEnabled($configLanguage) && \Drupal::currentUser()->hasPermission('manage lingotek translations');

      // Buttons for the ENTITY SOURCE LANGUAGE
      // We disable the checkbox for this row.
      $form['languages'][$langcode] = [
        '#type' => 'checkbox',
        '#disabled' => $source_language == $locale || !$enabled,
      ];

      if ($source_language == $locale) {
        // Check-Progress button if the source upload status is PENDING.
        if ($enabled && ($source_status === Lingotek::STATUS_IMPORTING || $source_status === Lingotek::STATUS_EDITED) && !empty($document_id)) {
          $checkPath = '/admin/lingotek/entity/check_upload/' . $document_id;
          $path = '/admin/lingotek/batch/uploadSingle/' . $entity_type . '/' . $entity->id();
          $this->addOperationLink($entity, $option, 'Check Upload Status', $checkPath, $language);
          $this->addOperationLink($entity, $option, 'Upload', $path, $language);
        }
        // Upload button if the status is EDITED or non-existent.
        elseif ($enabled && ($source_status === Lingotek::STATUS_EDITED || $source_status === Lingotek::STATUS_ERROR || $source_status === Lingotek::STATUS_UNTRACKED || $source_status === NULL)) {
          $path = '/admin/lingotek/batch/uploadSingle/' . $entity_type . '/' . $entity->id();
          $this->addOperationLink($entity, $option, 'Upload', $path, $language);
        }
        elseif ($enabled && ($source_status === Lingotek::STATUS_CURRENT)) {
          // Allow to re-upload if the status is current.
          $path = '/admin/lingotek/batch/uploadSingle/' . $entity_type . '/' . $entity->id();
          $this->addOperationLink($entity, $option, 'Upload', $path, $language);
        }
      }
      if ($source_language !== $locale && $enabled) {
        // Buttons for the ENTITY TARGET LANGUAGE
        $target_status = $translation_service->getTargetStatus($entity, $langcode);

        // Add-Targets button if languages haven't been added, or if target status is UNTRACKED.
        if (($source_status === Lingotek::STATUS_CURRENT || $source_status === Lingotek::STATUS_IMPORTING)
              && !empty($document_id) && (!isset($target_status) || $target_status === Lingotek::STATUS_UNTRACKED || $target_status === Lingotek::STATUS_EDITED || $target_status === Lingotek::STATUS_REQUEST)) {
          $path = '/admin/lingotek/entity/add_target/' . $document_id . '/' . $locale;
          $this->addOperationLink($entity, $option, 'Request translation', $path, $language);
        }
        elseif ($target_status === Lingotek::STATUS_PENDING) {
          $path = '/admin/lingotek/entity/check_target/' . $document_id . '/' . $locale;
          $this->addOperationLink($entity, $option, 'Check translation status', $path, $language, TRUE);
          $status_check_needed = TRUE;
        }
        // Download button if translations are READY or CURRENT.
        elseif (($target_status === Lingotek::STATUS_READY || $target_status === Lingotek::STATUS_CURRENT)) {
          $path = '/admin/lingotek/workbench/' . $document_id . '/' . $locale;
          $this->addOperationLink($entity, $option, 'Edit in Lingotek Workbench', $path, $language, TRUE);
          $path = '/admin/lingotek/entity/download/' . $document_id . '/' . $locale;
          if ($target_status === Lingotek::STATUS_READY) {
            $this->addOperationLink($entity, $option, 'Download completed translation', $path, $language, TRUE);
          }
          elseif ($target_status === Lingotek::STATUS_CURRENT) {
            $this->addOperationLink($entity, $option, 'Re-download completed translation', $path, $language, TRUE);
          }
          $targets_ready = TRUE;
        }
      }

      $form['languages']['#options'][$langcode] = $option;
    }

    if (\Drupal::currentUser()->hasPermission('manage lingotek translations')) {
      $form['actions']['#type'] = 'actions';

      if ($status_check_needed) {
        $form['actions']['request'] = [
          '#type' => 'submit',
          '#value' => $this->t('Check Progress'),
          '#submit' => ['::submitForm'],
          '#button_type' => 'primary',
        ];
      }
      elseif ($targets_ready) {
        $form['actions']['request'] = [
          '#type' => 'submit',
          '#value' => $this->t('Download selected translations'),
          '#submit' => ['::submitForm'],
          '#button_type' => 'primary',
        ];
      }
    }
    $form['fieldset']['entity'] = [
      '#type' => 'value',
      '#value' => $entity,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $entity = $form_values['entity'];
    $selected_langcodes = $form_values['languages'];
    $locales = [];
    foreach ($selected_langcodes as $langcode => $selected) {
      if ($selected) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
        $locales[] = $locale;
      }
    }

  }

  protected function getOperationColumnId(ContentEntityInterface $entity, array $option) {
    $found = -1;
    foreach ($option as $index => $column) {
      if (is_array($column) && isset($column['data']) && isset($column['data']['#type']) && $column['data']['#type'] === 'operations') {
        $found = $index;
        break;
      }
    }
    return $found;
  }

  /**
 * Add an operation to the list of available operations for each language.
 */
  protected function addOperationLink(ContentEntityInterface $entity, array &$option, $name, $path, LanguageInterface $language, $first = FALSE) {
    $operation_col = $this->getOperationColumnId($entity, $option);
    $open_in_window = FALSE;

    if (!isset($option[$operation_col]['data']['#links'])) {
      $option[$operation_col]['data']['#links'] = [];
    }
    if (is_string($path)) {
      if (strpos($path, '/admin/lingotek/batch/') === 0) {
        $path = str_replace('/admin/lingotek/batch/', '', $path);
        list($action, $entity_type, $entity_id) = explode('/', $path);
        $url = Url::fromRoute('lingotek.batch', [
          'action' => $action,
          'entity_type' => $entity_type,
          'entity_id' => $entity_id,
        ]);
      }
      elseif (strpos($path, '/admin/lingotek/entity/check_upload/') === 0) {
        $doc_id = str_replace('/admin/lingotek/entity/check_upload/', '', $path);
        $url = Url::fromRoute('lingotek.entity.check_upload', ['doc_id' => $doc_id]);
      }
      elseif (strpos($path, '/admin/lingotek/entity/add_target/') === 0) {
        $path = str_replace('/admin/lingotek/entity/add_target/', '', $path);
        list($doc_id, $locale) = explode('/', $path);
        $url = Url::fromRoute('lingotek.entity.request_translation', [
          'doc_id' => $doc_id,
          'locale' => $locale,
        ]);
      }
      elseif (strpos($path, '/admin/lingotek/entity/check_target/') === 0) {
        $path = str_replace('/admin/lingotek/entity/check_target/', '', $path);
        list($doc_id, $locale) = explode('/', $path);
        $url = Url::fromRoute('lingotek.entity.check_target', [
          'doc_id' => $doc_id,
          'locale' => $locale,
        ]);
      }
      elseif (strpos($path, '/admin/lingotek/entity/download/') === 0) {
        $path = str_replace('/admin/lingotek/entity/download/', '', $path);
        list($doc_id, $locale) = explode('/', $path);
        $url = Url::fromRoute('lingotek.entity.download', [
          'doc_id' => $doc_id,
          'locale' => $locale,
        ]);
      }
      elseif (strpos($path, '/admin/lingotek/workbench/') === 0) {
        $path = str_replace('/admin/lingotek/workbench/', '', $path);
        list($doc_id, $locale) = explode('/', $path);
        $url = Url::fromRoute('lingotek.workbench', [
          'doc_id' => $doc_id,
          'locale' => $locale,
        ]);
        $open_in_window = TRUE;
      }
      else {
        die("failed to get known operation in addOperationLink: $path");
      }
    }
    else {
      $url = $path;
    }

    if ($first) {
      $previous = $option[$operation_col]['data']['#links'];
      $option[$operation_col]['data']['#links'] = [];
      $option[$operation_col]['data']['#links'][strtolower($name)] = [
        'title' => $name,
        'url' => $url,
      ];
      $option[$operation_col]['data']['#links'] += $previous;
    }
    else {
      $option[$operation_col]['data']['#links'][strtolower($name)] = [
        'title' => $name,
        'url' => $url,
      ];
    }
    if ($open_in_window) {
      $option[$operation_col]['data']['#links'][strtolower($name)]['attributes']['target'] = '_blank';
    }
  }

  protected function removeOperationLink(ContentEntityInterface $entity, array &$option, $name) {
    $operation_col = $this->getOperationColumnId($entity, $option);

    unset($option[$operation_col]['data']['#links'][strtolower($name)]);
  }

}
