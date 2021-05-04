<?php

namespace Drupal\lingotek;

use Drupal\Component\Gettext\PoItem;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;

/**
 * Service for managing Lingotek interface translations.
 */
class LingotekInterfaceTranslationService implements LingotekInterfaceTranslationServiceInterface {

  use StringTranslationTrait;

  /**
   * The Lingotek interface
   *
   * @var \Drupal\lingotek\LingotekInterface
   */
  protected $lingotek;

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
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a new LingotekContentTranslationService object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   An lingotek object.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, LanguageManagerInterface $language_manager, Connection $connection, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler) {
    $this->lingotek = $lingotek;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->languageManager = $language_manager;
    $this->connection = $connection;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
  }

  /**
   * Returns the component Lingotek metadata.
   *
   * @param string $component
   *   The component.
   *
   * @return array
   *   The metadata.
   */
  protected function getMetadata($component) {
    $state = \Drupal::state();
    $translations_metadata = $state->get('lingotek.interface_translations_metadata');
    $component_metadata = [];
    if ($translations_metadata) {
      if (isset($translations_metadata[$component])) {
        $component_metadata = $translations_metadata[$component];
      }
    }
    return $component_metadata;
  }

  protected function saveMetadata($component, $metadata) {
    $state = \Drupal::state();
    $translations_metadata = $state->get('lingotek.interface_translations_metadata');
    if (!$translations_metadata) {
      $translations_metadata = [];
    }
    $translations_metadata[$component] = $metadata;
    $state->set('lingotek.interface_translations_metadata', $translations_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function checkSourceStatus($component) {
    $document_id = $this->getDocumentId($component);
    if ($document_id) {
      // Document has successfully imported.
      if ($this->lingotek->getDocumentStatus($document_id)) {
        $this->setSourceStatus($component, Lingotek::STATUS_CURRENT);
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceStatus($component) {
    $source_language = 'en';
    return $this->getTargetStatus($component, $source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceStatus($component, $status) {
    $source_language = 'en';
    return $this->setTargetStatus($component, $source_language, $status);
  }

  /**
   * Clear the target statuses.
   *
   * @param string $component
   *   The component.
   */
  protected function clearTargetStatuses($component) {
    // Clear the target statuses. As we save the source status with the target,
    // we need to keep that one.
    $source_status = $this->getSourceStatus($component);

    $metadata = $this->getMetadata($component);
    if (!empty($metadata) && isset($metadata['translation_status'])) {
      unset($metadata['translation_status']);
      $this->saveMetadata($component, $metadata);
    }
    $this->setTargetStatus($component, 'en', $source_status);
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatuses($component) {
    $document_id = $this->getDocumentId($component);
    $translation_statuses = $this->lingotek->getDocumentTranslationStatuses($document_id);
    $source_status = $this->getSourceStatus($component);

    $statuses = [];
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $language) {
      $statuses[$language->getId()] = $this->getTargetStatus($component, $language->getId());
    }

    // Let's reset all statuses, but keep the source one.
    $this->clearTargetStatuses($component);

    foreach ($translation_statuses as $lingotek_locale => $progress) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($lingotek_locale);
      if ($drupal_language == NULL) {
        // Language existing in TMS, but not configured on Drupal.
        continue;
      }
      $langcode = $drupal_language->id();
      $current_target_status = $statuses[$langcode];
      if (in_array($current_target_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_DISABLED, Lingotek::STATUS_EDITED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_NONE, Lingotek::STATUS_READY, Lingotek::STATUS_PENDING, Lingotek::STATUS_CANCELLED, NULL])) {
        if ($progress === Lingotek::STATUS_CANCELLED) {
          $this->setTargetStatus($component, $langcode, Lingotek::STATUS_CANCELLED);
        }
        elseif ($progress === Lingotek::PROGRESS_COMPLETE) {
          $this->setTargetStatus($component, $langcode, Lingotek::STATUS_READY);
        }
        else {
          $this->setTargetStatus($component, $langcode, Lingotek::STATUS_PENDING);
        }
      }
      if ($source_status !== Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_EDITED && $langcode !== 'en') {
        $this->setTargetStatus($component, $langcode, Lingotek::STATUS_EDITED);
      }
      if ($source_status === Lingotek::STATUS_CURRENT && $statuses[$langcode] === Lingotek::STATUS_CURRENT && $langcode !== 'en') {
        $this->setTargetStatus($component, $langcode, Lingotek::STATUS_CURRENT);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkTargetStatus($component, $langcode) {
    $current_status = $this->getTargetStatus($component, $langcode);
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    $source_status = $this->getSourceStatus($component);
    $document_id = $this->getDocumentId($component);
    if ($langcode !== 'en') {
      if (($current_status == Lingotek::STATUS_PENDING ||
      $current_status == Lingotek::STATUS_EDITED) &&
      $source_status !== Lingotek::STATUS_EDITED) {
        $translation_status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        if ($translation_status === Lingotek::STATUS_CANCELLED) {
          $this->setTargetStatus($component, $langcode, Lingotek::STATUS_CANCELLED);
        }
        elseif ($translation_status === TRUE) {
          $current_status = Lingotek::STATUS_READY;
          $this->setTargetStatus($component, $langcode, $current_status);
        }
        // We may not be ready, but some phases must be complete. Let's try to
        // download data, and if there is anything, we can assume a phase is
        // completed.
        // ToDo: Instead of downloading would be nice if we could check phases.
        elseif ($this->lingotek->downloadDocument($document_id, $locale)) {
          // TODO: Set Status to STATUS_READY_INTERIM when that status is
          // available. See ticket: https://www.drupal.org/node/2850548
        }
      }
      elseif ($current_status == Lingotek::STATUS_REQUEST || $current_status == Lingotek::STATUS_UNTRACKED) {
        $translation_status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        if ($translation_status === TRUE) {
          $current_status = Lingotek::STATUS_READY;
          $this->setTargetStatus($component, $langcode, $current_status);
        }
        elseif ($translation_status !== FALSE) {
          $current_status = Lingotek::STATUS_PENDING;
          $this->setTargetStatus($component, $langcode, $current_status);
        } //elseif ($this->lingotek->downloadDocument($document_id, $locale)) {
        //   // TODO: Set Status to STATUS_READY_INTERIM when that status is
        //   // available. See ticket: https://www.drupal.org/node/2850548
        // }
      }
    }
    return $current_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatus($component, $langcode) {
    $status = Lingotek::STATUS_UNTRACKED;
    $statuses = $this->getTargetStatuses($component);
    if (isset($statuses[$langcode])) {
      $status = $statuses[$langcode];
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetStatuses($component) {
    $metadata = $this->getMetadata($component);
    return isset($metadata['translation_status']) ? $metadata['translation_status'] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatus($component, $langcode, $status) {
    $metadata = $this->getMetadata($component);
    $metadata['translation_status'][$langcode] = $status;
    $this->saveMetadata($component, $metadata);
    return $component;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetStatuses($component, $status) {
    $target_languages = $this->languageManager->getLanguages();
    $source_langcode = 'en';

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $source_langcode && $current_status = $this->getTargetStatus($component, $langcode)) {
        if ($current_status === Lingotek::STATUS_PENDING && $status === Lingotek::STATUS_REQUEST) {
          // Don't allow to pass from pending to request. We have been already
          // requested this one.
          continue;
        }
        if (in_array($current_status, [Lingotek::STATUS_UNTRACKED, Lingotek::STATUS_REQUEST, Lingotek::STATUS_DISABLED, NULL]) && $status === Lingotek::STATUS_PENDING) {
          continue;
        }
        if ($current_status == $status) {
          continue;
        }
        if ($current_status != Lingotek::STATUS_EDITED && $current_status !== Lingotek::STATUS_CURRENT) {
          $this->setTargetStatus($component, $langcode, $status);
        }
        elseif ($current_status == Lingotek::STATUS_EDITED && in_array($status, [Lingotek::STATUS_CURRENT, Lingotek::STATUS_PENDING])) {
          $this->setTargetStatus($component, $langcode, $status);
        }
        if ($status === Lingotek::STATUS_CANCELLED) {
          $this->setTargetStatus($component, $langcode, $status);
        }
        if ($status === Lingotek::STATUS_DISABLED) {
          $this->setTargetStatus($component, $langcode, $status);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function markTranslationsAsDirty($component) {
    $target_languages = $this->languageManager->getLanguages();
    $source_langcode = 'en';

    // Only mark as out of date the current ones.
    $to_change = [
      Lingotek::STATUS_CURRENT,
      // Lingotek::STATUS_PENDING,
      // Lingotek::STATUS_INTERMEDIATE,
      // Lingotek::STATUS_READY,
    ];

    foreach ($target_languages as $langcode => $language) {
      if ($langcode != $source_langcode && $current_status = $this->getTargetStatus($component, $langcode)) {
        if (in_array($current_status, $to_change)) {
          $this->setTargetStatus($component, $langcode, Lingotek::STATUS_PENDING);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentId($component) {
    $doc_id = NULL;
    $metadata = $this->getMetadata($component);
    if (!empty($metadata) && isset($metadata['document_id'])) {
      $doc_id = $metadata['document_id'];
    }
    return $doc_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setDocumentId($component, $doc_id) {
    $metadata = $this->getMetadata($component);
    $metadata['document_id'] = $doc_id;
    $this->saveMetadata($component, $metadata);
    return $component;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLocale($component) {
    $source_language = 'en';
    return $this->languageLocaleMapper->getLocaleForLangcode($source_language);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceData($component) {
    $data = [];
    $potx_strings = $this->extractPotxStrings($component);
    if (!empty($potx_strings)) {
      foreach ($potx_strings as $potx_string => $potx_string_meta) {
        // The key in the JSON download cannot have the null byte used by plurals.
        $translationStringKey = str_replace("\0", "<PLURAL>", $potx_string);
        // Plural strings have a null byte delimited format. We need to separate the
        // segments ourselves and nest those in.
        $explodedStrings = explode("\0", $potx_string);
        $translationData = [];
        foreach ($explodedStrings as $index => $explodedString) {
          $translationData[$explodedString] = $explodedString;
        }
        foreach ($potx_string_meta as $context => $potx_string_meta_locations) {
          $translationStringKeyWithContext = $translationStringKey . '<CONTEXT>' . $context;
          $data[$translationStringKeyWithContext] = $translationData;
          $data[$translationStringKeyWithContext]['_context'] = $context;
        }
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function updateEntityHash($component) {
    $source_data = json_encode($this->getSourceData($component));
    $metadata = $this->getMetadata($component);
    if (!empty($metadata)) {
      $metadata['lingotek_hash'] = md5($source_data);
      $this->saveMetadata($component, $metadata);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasEntityChanged($component) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function addTarget($component, $locale) {
    $source_langcode = 'en';
    $source_locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);

    if ($locale == $source_locale) {
      // We don't want to translate from one language to itself.
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($component)) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
      $source_status = $this->getSourceStatus($component);
      $current_status = $this->getTargetStatus($component, $drupal_language->id());

      // When a translation is in one of these states, we know that it hasn't yet been sent up to the Lingotek API,
      // which means that we'll have to call addTarget() on it.
      //
      // TODO: should we consider STATUS_NONE as a "pristine" status?
      $pristine_statuses = [
        Lingotek::STATUS_REQUEST,
        Lingotek::STATUS_UNTRACKED,
        Lingotek::STATUS_EDITED,
      ];

      if (in_array($current_status, $pristine_statuses)) {
        try {
          $result = $this->lingotek->addTarget($document_id, $locale, NULL);
        }
        catch (LingotekDocumentLockedException $exception) {
          $this->setDocumentId($component, $exception->getNewDocumentId());
          throw $exception;
        }
        catch (LingotekDocumentArchivedException $exception) {
          $this->setDocumentId($component, NULL);
          $this->deleteMetadata($component);
          throw $exception;
        }
        catch (LingotekPaymentRequiredException $exception) {
          throw $exception;
        }
        catch (LingotekApiException $exception) {
          throw $exception;
        }
        if ($result) {
          $this->setTargetStatus($component, $drupal_language->id(), Lingotek::STATUS_PENDING);
          // If the status was "Importing", and the target was added
          // successfully, we can ensure that the content is current now.
          if ($source_status == Lingotek::STATUS_IMPORTING) {
            $this->setSourceStatus($component, Lingotek::STATUS_CURRENT);
          }
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslations($component) {
    $languages = [];
    if ($document_id = $this->getDocumentId($component)) {
      $target_languages = $this->languageManager->getLanguages();
      $target_languages = array_filter($target_languages, function (LanguageInterface $language) {
        $configLanguage = ConfigurableLanguage::load($language->getId());
        return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
      });

      $source_langcode = 'en';

      foreach ($target_languages as $langcode => $language) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
        if ($langcode !== $source_langcode) {
          $source_status = $this->getSourceStatus($component);
          $current_status = $this->getTargetStatus($component, $langcode);
          if ($current_status !== Lingotek::STATUS_PENDING && $current_status !== Lingotek::STATUS_CURRENT && $current_status !== Lingotek::STATUS_EDITED  && $current_status !== Lingotek::STATUS_READY) {
            try {
              $result = $this->lingotek->addTarget($document_id, $locale, NULL);
            }
            catch (LingotekDocumentLockedException $exception) {
              $this->setDocumentId($component, $exception->getNewDocumentId());
              throw $exception;
            }
            catch (LingotekDocumentArchivedException $exception) {
              $this->setDocumentId($component, NULL);
              $this->deleteMetadata($component);
              throw $exception;
            }
            catch (LingotekPaymentRequiredException $exception) {
              throw $exception;
            }
            catch (LingotekApiException $exception) {
              throw $exception;
            }
            if ($result) {
              $languages[] = $langcode;
              $this->setTargetStatus($component, $langcode, Lingotek::STATUS_PENDING);
              // If the status was "Importing", and the target was added
              // successfully, we can ensure that the content is current now.
              if ($source_status == Lingotek::STATUS_IMPORTING) {
                $this->setSourceStatus($component, Lingotek::STATUS_CURRENT);
              }
            }
          }
        }
      }
    }
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument($component, $job_id = NULL) {
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($component) ?: NULL;
    }
    if ($document_id = $this->getDocumentId($component)) {
      return $this->updateDocument($component, $job_id);
    }
    $source_data = $this->getSourceData($component);
    $document_name = 'Interface translation: ' . $component;

    // Allow other modules to alter the data before is uploaded.
    \Drupal::moduleHandler()->invokeAll('lingotek_interface_translation_document_upload', [&$source_data, &$component]);

    try {
      $document_id = $this->lingotek->uploadDocument($document_name, $source_data, $this->getSourceLocale($component), NULL, NULL, $job_id);
    }
    catch (LingotekPaymentRequiredException $exception) {
      $this->setSourceStatus($component, Lingotek::STATUS_ERROR);
      throw $exception;
    }
    catch (LingotekApiException $exception) {
      $this->setSourceStatus($component, Lingotek::STATUS_ERROR);
      throw $exception;
    }
    if ($document_id) {
      $this->setDocumentId($component, $document_id);
      $this->setSourceStatus($component, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($component, Lingotek::STATUS_REQUEST);
      $this->setJobId($component, $job_id);
      $this->setLastUploaded($component, \Drupal::time()->getRequestTime());
      return $document_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocument($component, $locale) {
    if ($document_id = $this->getDocumentId($component)) {
      $source_status = $this->getSourceStatus($component);
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);
      $langcode = $drupal_language->id();
      $data = [];
      try {
        if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) !== FALSE) {
          $data = $this->lingotek->downloadDocument($document_id, $locale);
        }
        else {
          \Drupal::logger('lingotek')->warning('Avoided download for (%component): Source status is %source_status.', [
              '%component' => $component,
              '%source_status' => $this->getSourceStatus($component),
          ]);
          return NULL;
        }
      }
      catch (LingotekApiException $exception) {
        \Drupal::logger('lingotek')->error('Error happened downloading %document_id %locale: %message', [
          '%document_id' => $document_id,
          '%locale' => $locale,
          '%message' => $exception->getMessage(),
        ]);
        $this->setTargetStatus($component, $langcode, Lingotek::STATUS_ERROR);
        throw $exception;
      }

      if ($data) {
        // Check the real status, because it may still need review or anything.
        $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
        $transaction = $this->connection->startTransaction();
        try {
          $saved = $this->saveTargetData($component, $langcode, $data);
          if ($saved) {
            // If the status was "Importing", and the target was added
            // successfully, we can ensure that the content is current now.
            if ($source_status == Lingotek::STATUS_IMPORTING) {
              $this->setSourceStatus($component, Lingotek::STATUS_CURRENT);
            }
            if ($source_status == Lingotek::STATUS_EDITED) {
              $this->setTargetStatus($component, $langcode, Lingotek::STATUS_EDITED);
            }
            elseif ($status === TRUE) {
              $this->setTargetStatus($component, $langcode, Lingotek::STATUS_CURRENT);
            }
            else {
              $this->setTargetStatus($component, $langcode, Lingotek::STATUS_INTERMEDIATE);
            }
          }
        }
        catch (\Exception $exception) {
          $transaction->rollBack();
          \Drupal::logger('lingotek')->error('Error happened (unknown) saving %document_id %locale: %message', ['%document_id' => $document_id, '%locale' => $locale, '%message' => $exception->getMessage()]);
          $this->setTargetStatus($component, $langcode, Lingotek::STATUS_ERROR);
          return FALSE;
        }
        return TRUE;
      }
    }
    \Drupal::logger('lingotek')->warning('Error happened trying to download (%component): no document id found.', ['%component' => $component]);
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument($component, $job_id = NULL) {
    // If job id was not set in the form, it may be already assigned.
    if ($job_id === NULL) {
      $job_id = $this->getJobId($component) ?: NULL;
    }
    $source_data = $this->getSourceData($component);
    $document_id = $this->getDocumentId($component);

    $document_name = 'Interface translation: ' . $component;

    // Allow other modules to alter the data before is uploaded.
    \Drupal::moduleHandler()->invokeAll('lingotek_interface_translation_document_upload', [&$source_data, &$component]);

    try {
      $newDocumentID = $this->lingotek->updateDocument($document_id, $source_data, NULL, $document_name, NULL, $job_id, $this->getSourceLocale($component));
    }
    catch (LingotekDocumentLockedException $exception) {
      $this->setDocumentId($component, $exception->getNewDocumentId());
      throw $exception;
    }
    catch (LingotekDocumentArchivedException $exception) {
      $this->setDocumentId($component, NULL);
      $this->deleteMetadata($component);
      throw $exception;
    }
    catch (LingotekPaymentRequiredException $exception) {
      $this->setSourceStatus($component, Lingotek::STATUS_ERROR);
      throw $exception;
    }
    catch (LingotekApiException $exception) {
      $this->setSourceStatus($component, Lingotek::STATUS_ERROR);
      throw $exception;
    }

    if ($newDocumentID) {
      if (is_string($newDocumentID)) {
        $document_id = $newDocumentID;
        $this->setDocumentId($component, $newDocumentID);
      }
      $this->setSourceStatus($component, Lingotek::STATUS_IMPORTING);
      $this->setTargetStatuses($component, Lingotek::STATUS_PENDING);
      $this->setJobId($component, $job_id);
      $this->setLastUpdated($component, \Drupal::time()->getRequestTime());
      return $document_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocuments($component) {
    if ($document_id = $this->getDocumentId($component)) {
      $source_status = $this->getSourceStatus($component);
      $target_languages = $this->languageManager->getLanguages();
      $target_languages = array_filter($target_languages, function (LanguageInterface $language) {
        $configLanguage = ConfigurableLanguage::load($language->getId());
        return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
      });

      $source_langcode = 'en';

      foreach ($target_languages as $langcode => $language) {
        $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
        if ($langcode !== $source_langcode) {
          try {
            if ($this->lingotek->getDocumentTranslationStatus($document_id, $locale) !== FALSE) {
              $data = $this->lingotek->downloadDocument($document_id, $locale);
              if ($data) {
                // Check the real status, because it may still need review or anything.
                $status = $this->lingotek->getDocumentTranslationStatus($document_id, $locale);
                $transaction = $this->connection->startTransaction();
                try {
                  $saved = $this->saveTargetData($component, $langcode, $data);
                  if ($saved) {
                    // If the status was "Importing", and the target was added
                    // successfully, we can ensure that the content is current now.
                    if ($source_status == Lingotek::STATUS_IMPORTING) {
                      $this->setSourceStatus($component, Lingotek::STATUS_CURRENT);
                    }
                    if ($source_status == Lingotek::STATUS_EDITED) {
                      $this->setTargetStatus($component, $langcode, Lingotek::STATUS_EDITED);
                    }
                    elseif ($status === TRUE) {
                      $this->setTargetStatus($component, $langcode, Lingotek::STATUS_CURRENT);
                    }
                    else {
                      $this->setTargetStatus($component, $langcode, Lingotek::STATUS_INTERMEDIATE);
                    }
                  }
                }
                catch (LingotekApiException $exception) {
                  // TODO: log issue
                  $this->setTargetStatus($component, $langcode, Lingotek::STATUS_ERROR);
                  throw $exception;
                }
                catch (LingotekContentEntityStorageException $storageException) {
                  $this->setTargetStatus($component, $langcode, Lingotek::STATUS_ERROR);
                  throw $storageException;
                }
                catch (\Exception $exception) {
                  $transaction->rollBack();
                  $this->setTargetStatus($component, $langcode, Lingotek::STATUS_ERROR);
                }
              }
              else {
                return NULL;
              }
            }
          }
          catch (LingotekApiException $exception) {
            // TODO: log issue
            $this->setTargetStatus($component, $langcode, Lingotek::STATUS_ERROR);
            throw $exception;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocument($component) {
    $result = FALSE;
    $doc_id = $this->getDocumentId($component);
    if ($doc_id) {
      $result = $this->lingotek->cancelDocument($doc_id);
      $this->setDocumentId($component, NULL);
    }
    $this->setSourceStatus($component, Lingotek::STATUS_CANCELLED);
    $this->setTargetStatuses($component, Lingotek::STATUS_CANCELLED);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocumentTarget($component, $locale) {
    $source_langcode = 'en';
    $source_locale = $this->languageLocaleMapper->getLocaleForLangcode($source_langcode);

    if ($locale == $source_locale) {
      // This is not a target, but the source language itself.
      return FALSE;
    }
    if ($document_id = $this->getDocumentId($component)) {
      $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);

      if ($this->lingotek->cancelDocumentTarget($document_id, $locale)) {
        $this->setTargetStatus($component, $drupal_language->id(), Lingotek::STATUS_CANCELLED);
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMetadata($component) {
    $doc_id = $this->getDocumentId($component);
    if ($doc_id) {
      $this->cancelDocument($component);
    }
    $state = \Drupal::state();
    $translations_metadata = $state->get('lingotek.interface_translations_metadata');
    if ($translations_metadata) {
      unset($translations_metadata[$component]);
      $state->set('lingotek.interface_translations_metadata', $translations_metadata);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllMetadata() {
    $state = \Drupal::state();
    $translations_metadata = $state->get('lingotek.interface_translations_metadata');
    if ($translations_metadata) {
      foreach ($translations_metadata as $component => $componentMetadata) {
        if ($componentMetadata['document_id']) {
          $this->cancelDocument($component);
        }
      }
    }
    $state->delete('lingotek.interface_translations_metadata');
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDocumentId($document_id) {
    $state = \Drupal::state();
    $translations_metadata = $state->get('lingotek.interface_translations_metadata');
    if ($translations_metadata) {
      foreach ($translations_metadata as $component => $componentMetadata) {
        if ($componentMetadata['document_id'] === $document_id) {
          return $component;
        }
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllLocalDocumentIds() {
    $state = \Drupal::state();
    $translations_metadata = $state->get('lingotek.interface_translations_metadata');
    $docIds = [];
    if ($translations_metadata) {
      foreach ($translations_metadata as $component => $componentMetadata) {
        $docIds[] = $componentMetadata['document_id'];
      }
    }
    return $docIds;
  }

  /**
   * {@inheritdoc}
   */
  public function saveTargetData($component, $langcode, $data) {
    $customized = TRUE;
    $overwrite_options['customized'] = TRUE;
    $overwrite_options['not_customized'] = FALSE;
    foreach ($data as $sourceData => $translationData) {
      // We need to take and ignore the special _context entry.
      $context = $translationData['_context'];
      unset($translationData['_context']);
      // We need to manage plurals.
      if (count($translationData) == 1) {
        $keys = array_keys($translationData);
        $source = reset($keys);
        $translation = reset($translationData);
      }
      else {
        $keys = array_keys($translationData);
        $source = implode(PoItem::DELIMITER, $keys);
        $translation = implode(PoItem::DELIMITER, $translationData);
      }
      // Look up the source string and any existing translation.
      $strings = \Drupal::service('locale.storage')->getTranslations([
        'language' => $langcode,
        'source' => $source,
        'context' => $context,
      ]);
      $string = reset($strings);

      if (!empty($translation)) {
        // Skip this string unless it passes a check for dangerous code.
        if (!locale_string_is_safe($translation)) {
          \Drupal::logger('lingotek')->error('Import of string "%string" was skipped because of disallowed or malformed HTML.', ['%string' => $translation]);
        }
        elseif ($string) {
          $string->setString($translation);
          if ($string->isNew()) {
            // No translation in this language.
            $string->setValues([
              'language' => $langcode,
              'customized' => $customized,
            ]);
            $string->save();
          }
          elseif ($overwrite_options[$string->customized ? 'customized' : 'not_customized']) {
            // Translation exists, only overwrite if instructed.
            $string->customized = $customized;
            $string->save();
          }
        }
        else {
          // No such source string in the database yet.
          $string = \Drupal::service('locale.storage')->createString(['source' => $source, 'context' => $context])
            ->save();
          \Drupal::service('locale.storage')->createTranslation([
            'lid' => $string->getId(),
            'language' => $langcode,
            'translation' => $translation,
            'customized' => $customized,
          ])->save();
        }
      }
    }
    return $component;
  }

  /**
   * {@inheritdoc}
   */
  public function getJobId($component) {
    $job_id = NULL;
    $metadata = $this->getMetadata($component);
    if (!empty($metadata) && !empty($metadata['job_id'])) {
      $job_id = $metadata['job_id'];
    }
    return $job_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setJobId($component, $job_id, $update_tms = FALSE) {
    $metadata = $this->getMetadata($component);

    $newDocumentID = FALSE;
    if ($update_tms && $document_id = $this->getDocumentId($component)) {
      try {
        $newDocumentID = $this->lingotek->updateDocument($document_id, NULL, NULL, NULL, NULL, $job_id);
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->setDocumentId($component, $exception->getNewDocumentId());
        throw $exception;
      }
      catch (LingotekDocumentArchivedException $exception) {
        $old_job_id = $this->getJobId($component);
        $this->deleteMetadata($component);
        $metadata = $this->getMetadata($component);
        $metadata['job_id'] = $old_job_id;
        $this->saveMetadata($component, $metadata);
        throw $exception;
      }
      catch (LingotekPaymentRequiredException $exception) {
        throw $exception;
      }
      catch (LingotekApiException $exception) {
        throw $exception;
      }
    }
    if (is_string($newDocumentID)) {
      $metadata['document_id'] = $newDocumentID;
    }
    $metadata['job_id'] = $job_id;
    $this->saveMetadata($component, $metadata);
    return $component;
  }

  /**
   * Extract strings by using potx module.
   *
   * @param string $component
   *   The component we want to extract the strings from.
   *
   * @return array
   *   Collection of strings in the potx format:
   *     string => [
   *       context => context_info,
   *       context => context_info,
   *    ]
   */
  protected function extractPotxStrings($component) {
    global $_potx_strings;

    $this->moduleHandler->loadInclude('potx', 'inc');
    $this->moduleHandler->loadInclude('potx', 'inc', 'potx.local');

    // Silence status messages.
    potx_status('set', POTX_STATUS_MESSAGE);
    $pathinfo = pathinfo($component);
    if (!isset($pathinfo['filename'])) {
      // The filename key is only available in PHP 5.2.0+.
      $pathinfo['filename'] = substr($pathinfo['basename'], 0, strrpos($pathinfo['basename'], '.'));
    }
    if (isset($pathinfo['extension'])) {
      // A specific module or theme file was requested (otherwise there should
      // be no extension).
      potx_local_init($pathinfo['dirname']);
      $files = _potx_explore_dir($pathinfo['dirname'] . '/', $pathinfo['filename']);
      $strip_prefix = 1 + strlen($pathinfo['dirname']);
    }
    // A directory name was requested.
    else {
      potx_local_init($component);
      $files = _potx_explore_dir($component . '/');
      $strip_prefix = 1 + strlen($component);
    }

    // Collect every string in affected files. Installer related strings are
    // discarded.
    foreach ($files as $file) {
      _potx_process_file($file, $strip_prefix);
    }
    potx_finish_processing('_potx_save_string');
    return $_potx_strings;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastUploaded($component, int $timestamp) {
    $metadata = $this->getMetadata($component);
    $metadata['uploaded_timestamp'] = $timestamp;
    $this->saveMetadata($component, $metadata);

    return $component;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastUpdated($component, int $timestamp) {
    $metadata = $this->getMetadata($component);
    $metadata['updated_timestamp'] = $timestamp;
    $this->saveMetadata($component, $metadata);

    return $component;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastUploaded($component) {
    $metadata = $this->getMetadata($component);
    return isset($metadata['uploaded_timestamp']) ? $metadata['uploaded_timestamp'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedTime($component) {
    $metadata = $this->getMetadata($component);
    return isset($metadata['updated_timestamp']) ? $metadata['updated_timestamp'] : NULL;
  }

}
