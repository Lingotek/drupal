<?php

namespace Drupal\lingotek_test;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekProfileInterface;
use Drupal\lingotek\Remote\LingotekApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LingotekFake implements LingotekInterface {

  const SETTINGS = 'lingotek.settings';

  protected $api;
  protected $config;

  public function __construct(LingotekApiInterface $api, LanguageLocaleMapperInterface $language_locale_mapper, ConfigFactoryInterface $config) {
    $this->api = $api;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->config = $config;
  }

  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('lingotek.api'),
        $container->get('lingotek.language_locale_mapper'),
        $container->get('lingotek_test.config.factory')
      );
  }

  public function getEditable($key) {
    throw new \Exception('This method is deprecated, so should never be called from our own code.');
  }

  public function set($key, $value) {
    throw new \Exception('This method is deprecated, so should never be called from our own code.');
  }

  public function get($key) {
    throw new \Exception('This method is deprecated, so should never be called from our own code.');
  }

  public function getAccountInfo() {
    \Drupal::state()->set('lingotek_fake.setup_completed', TRUE);
    // We need to store the resources. Let's force that.
    return [
      'id' => 'test',
      'type' => 'token',
      'client_id' => 'test_default_client_id',
      'user_id' => 'testUser',
      'login_id' => 'testUser@example.com',
      'expires_at' => -1,
    ];
  }

  public function getVaults($force = FALSE) {
    $vaults = [
      'test_vault' => 'Test vault',
      'test_vault2' => 'Test vault 2',
    ];
    return $vaults;
  }

  public function getWorkflows($force = FALSE) {
    $workflows = [
      'test_workflow' => 'Test workflow',
      'test_workflow2' => 'Test workflow 2',
    ];
    return $workflows;
  }

  public function getCommunities($force = FALSE) {
    $communities = [
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ];
    return $communities;
  }

  public function getProjects($force = FALSE) {
    $projects = [
      'test_project' => 'Test project',
      'test_project2' => 'Test project 2',
    ];
    return $projects;
  }

  public function getFilters($force = FALSE) {
    $default_filters = [
      'test_filter' => 'Test filter',
      'test_filter2' => 'Test filter 2',
      'test_filter3' => 'Test filter 3',
    ];
    $filters = [];
    if (!\Drupal::state()->get('lingotek.no_filters', FALSE)) {
      $filters = $default_filters;
    }
    return $filters;
  }

  public function getProject($project_id) {
    return [
      'properties' => [
        'creation_date' => 1284940800000,
        'workflow_id' => 'test_workflow',
        'callback_url' => '',
        'title' => 'Test project',
        'community_id' => 'test_community',
        'id' => 'test_project',
      ],
    ];
  }

  public function setProjectCallBackUrl($project_id, $callback_url) {
    // We ignore the call and simulate a success.
    return TRUE;
  }

  public function getResources($force = FALSE) {
    $resources = [
      'project' => $this->getProjects($force),
      'vault' => $this->getVaults($force),
      'community' => $this->getCommunities($force),
      'workflow' => $this->getWorkflows($force),
      'filter' => $this->getFilters($force),
    ];
    return $resources;
  }

  public function getDefaults() {
    return $this->config->get(static::SETTINGS)->get('default');
  }

  public function uploadDocument($title, $content, $locale, $url = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL) {
    if (\Drupal::state()->get('lingotek.must_error_in_upload', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    if (\Drupal::state()->get('lingotek.must_payment_required_error_in_upload', FALSE)) {
      throw new LingotekPaymentRequiredException('Error was forced.');
    }
    if (is_array($content)) {
      $content = json_encode($content);
    }

    // If the upload is successful, we must return a valid hash.
    \Drupal::state()->set('lingotek.uploaded_title', $title);
    \Drupal::state()->set('lingotek.uploaded_content', $content);
    \Drupal::state()->set('lingotek.uploaded_locale', $locale);
    \Drupal::state()->set('lingotek.uploaded_url', $url);
    \Drupal::state()->set('lingotek.uploaded_job_id', $job_id);
    \Drupal::state()->set('lingotek.used_profile', $profile ? $profile->id() : NULL);

    $count = \Drupal::state()->get('lingotek.uploaded_docs', 0);

    $doc_id = 'dummy-document-hash-id';
    if ($count > 0) {
      $doc_id .= '-' . $count;
    }
    ++$count;
    \Drupal::state()->set('lingotek.last_used_id', $count);
    \Drupal::state()->set('lingotek.uploaded_docs', $count);

    // Save the timestamp of the upload.
    $timestamps = \Drupal::state()->get('lingotek.upload_timestamps', []);
    $timestamps[$doc_id] = \Drupal::time()->getRequestTime();
    \Drupal::state()->set('lingotek.upload_timestamps', $timestamps);

    return $doc_id;
  }

  public function updateDocument($doc_id, $content, $url = NULL, $title = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL, $locale = NULL) {
    $newId = TRUE;
    if (\Drupal::state()->get('lingotek.must_error_in_upload', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    if (\Drupal::state()->get('lingotek.must_payment_required_error_in_update', FALSE)) {
      throw new LingotekPaymentRequiredException('Error was forced.');
    }
    if (\Drupal::state()->get('lingotek.must_document_archived_error_in_update', FALSE)) {
      throw new LingotekDocumentArchivedException($doc_id, 'Error was forced.');
    }
    if (\Drupal::state()->get('lingotek.must_document_locked_error_in_update', FALSE)) {
      throw new LingotekDocumentLockedException($doc_id, 'new-doc-id', 'Error was forced.');
    }
    if (is_array($content)) {
      $content = json_encode($content);
    }
    if ($content === NULL) {
      $newId = FALSE;
    }
    \Drupal::state()->set('lingotek.uploaded_content', $content);
    \Drupal::state()->set('lingotek.uploaded_content_url', $url);
    \Drupal::state()->set('lingotek.uploaded_title', $title);
    \Drupal::state()->set('lingotek.uploaded_job_id', $job_id);

    if ($newId) {
      $last_doc_id = \Drupal::state()->get('lingotek.last_used_id', 0);
      $new_doc_id = 'dummy-document-hash-id';
      if ($last_doc_id > 0) {
        $new_doc_id .= '-' . $last_doc_id;
      }
      ++$last_doc_id;
      \Drupal::state()->set('lingotek.last_used_id', $last_doc_id);
    }
    else {
      $new_doc_id = $doc_id;
    }

    $requested_locales = \Drupal::state()->get('lingotek.requested_locales', []);
    if (isset($requested_locales[$doc_id])) {
      $new_requested_locales = [];
      foreach ($requested_locales as $id => $requested_locale) {
        if ($doc_id === $id) {
          $new_requested_locales[$new_doc_id] = $requested_locale;
        }
        else {
          $new_requested_locales[$new_doc_id] = $requested_locale;
        }
      }
      $requested_locales = $new_requested_locales;
    }
    \Drupal::state()->set('lingotek.requested_locales', $requested_locales);

    // Save the timestamp of the upload.
    $timestamps = \Drupal::state()->get('lingotek.upload_timestamps', []);
    $timestamps[$doc_id] = \Drupal::time()->getRequestTime();
    \Drupal::state()->set('lingotek.upload_timestamps', $timestamps);

    // Our document is always imported correctly.
    return $new_doc_id;
  }

  public function documentImported($doc_id) {
    // Our document is always imported correctly.
    return TRUE;
  }

  public function addTarget($doc_id, $locale, LingotekProfileInterface $profile = NULL) {
    if (\Drupal::state()->get('lingotek.must_error_in_request_translation', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    if (\Drupal::state()->get('lingotek.must_payment_required_error_in_request_translation', FALSE)) {
      throw new LingotekPaymentRequiredException('Error was forced.');
    }
    if (\Drupal::state()->get('lingotek.must_document_archived_error_in_request_translation', FALSE)) {
      throw new LingotekDocumentArchivedException($doc_id, 'Error was forced.');
    }
    if (\Drupal::state()->get('lingotek.must_document_locked_error_in_request_translation', FALSE)) {
      throw new LingotekDocumentLockedException($doc_id, 'new-doc-id', 'Error was forced.');
    }
    $requested_locales = \Drupal::state()->get('lingotek.requested_locales', []);
    $requested_locales[$doc_id][] = $locale;
    \Drupal::state()->set('lingotek.requested_locales', $requested_locales);

    \Drupal::state()->set('lingotek.added_target_locale', $locale);
    \Drupal::state()->set('lingotek.used_profile', $profile ? $profile->id() : NULL);
    // Added locale as target.
    return TRUE;
  }

  public function getDocumentStatus($doc_id) {
    if (\Drupal::state()->get('lingotek.must_error_in_check_source_status', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    return \Drupal::state()->get('lingotek.document_status_completion', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentTranslationStatus($doc_id, $locale) {
    if (\Drupal::state()->get('lingotek.must_error_in_check_target_status', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    \Drupal::state()->set('lingotek.checked_target_locale', $locale);
    // Return true if translation is done.
    if (\Drupal::state()->get('lingotek.document_completion', NULL) === NULL) {
      $result = TRUE;
      $requested_locales = \Drupal::state()->get('lingotek.requested_locales', []);
      if (!isset($requested_locales[$doc_id]) || !in_array($locale, $requested_locales[$doc_id])) {
        $result = FALSE;
      }
      $cancelled_locales = \Drupal::state()->get('lingotek.cancelled_locales', []);
      if (isset($cancelled_locales[$doc_id]) && in_array($locale, $cancelled_locales[$doc_id])) {
        $result = 'CANCELLED';
      }
      return $result;
    }
    return \Drupal::state()->get('lingotek.document_completion', TRUE);
  }

  public function downloadDocument($doc_id, $locale) {
    if (\Drupal::state()->get('lingotek.must_error_in_download', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }

    // We need to avoid this in some cases.
    if (\Drupal::state()->get('lingotek.document_completion', NULL) === NULL) {
      $requested_locales = \Drupal::state()->get('lingotek.requested_locales', []);
      if (!in_array($locale, $requested_locales[$doc_id])) {
        throw new LingotekApiException('Locale was not requested before.');
      }
    }

    \Drupal::state()->set('lingotek.downloaded_locale', $locale);
    $type = \Drupal::state()->get('lingotek.uploaded_content_type', 'node');
    $typeWithLocale = $type . '.' . $locale;
    $path = drupal_get_path('module', 'lingotek') . '/tests/modules/lingotek_test/document_responses/' . $typeWithLocale . '.json';

    if (!file_exists($path)) {
      $path = drupal_get_path('module', 'lingotek') . '/tests/modules/lingotek_test/document_responses/' . $type . '.json';
    }

    $input = file_get_contents($path);
    $dataReplacements = \Drupal::state()->get('lingotek.data_replacements', []);
    if (!empty($dataReplacements)) {
      foreach ($dataReplacements as $original => $new) {
        $input = str_replace($original, $new, $input);
      }
    }
    return json_decode($input, TRUE);
  }

  /**
   * {@inheritDoc}
   */
  public function getUploadedTimestamp($doc_id) {
    $timestamps = \Drupal::state()->get('lingotek.upload_timestamps', []);
    $timestamp = isset($timestamps[$doc_id]) ? $timestamps[$doc_id] : NULL;
    return $timestamp;
  }

  /**
   * {@inheritDoc}
   */
  public function cancelDocument($doc_id) {
    if (\Drupal::state()->get('lingotek.must_error_in_cancel', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    $cancelled_docs = \Drupal::state()->get('lingotek.cancelled_docs', []);
    $cancelled_docs[] = $doc_id;
    \Drupal::state()->set('lingotek.cancelled_docs', $cancelled_docs);
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function cancelDocumentTarget($doc_id, $locale) {
    if (\Drupal::state()->get('lingotek.must_error_in_cancel', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    $cancelled_locales = \Drupal::state()->get('lingotek.cancelled_locales', []);
    $cancelled_locales[$doc_id][] = $locale;
    \Drupal::state()->set('lingotek.cancelled_locales', $cancelled_locales);

    // Cancelled locale target.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getLocales() {
    if (\Drupal::state()->get('lingotek.locales_error', FALSE)) {
      throw new LingotekApiException('{"messages":["HTTP 401 Unauthorized"]}', 401);
    }
    return ['es-ES', 'de-AT', 'de-DE'];
  }

  /**
   * {@inheritDoc}
   */
  public function getLocalesInfo() {
    if (\Drupal::state()->get('lingotek.locales_error', FALSE)) {
      throw new LingotekApiException('{"messages":["HTTP 401 Unauthorized"]}', 401);
    }
    return [
      'es-ES' => [
        'code' => 'es-ES',
        'language_code' => 'ES',
        'title' => 'Spanish (Spain)',
        'language' => 'Spanish',
        'country_code' => 'ES',
        'country' => 'Spain',
      ],
      'de-AT' => [
        'code' => 'de-AT',
        'language_code' => 'DE',
        'title' => 'German (Austria)',
        'language' => 'German',
        'country_code' => 'AT',
        'country' => 'Austria',
      ],
      'de-DE' => [
        'code' => 'de-DE',
        'language_code' => 'DE',
        'title' => 'German (Germany)',
        'language' => 'German',
        'country_code' => 'DE',
        'country' => 'Germany',
      ],
    ];
  }

  public function getDocumentTranslationStatuses($doc_id) {
    if (!$doc_id) {
      throw new LingotekApiException('Error requesting statuses without document id.');
    }
    if (\Drupal::state()->get('lingotek.must_error_in_check_target_status', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    $statuses = \Drupal::state()->get('lingotek.document_completion_statuses', []);
    if (!empty($statuses)) {
      return $statuses;
    }
    if (\Drupal::state()->get('lingotek.document_completion', TRUE)) {
      return ['es-MX' => 100, 'es-ES' => 100, 'de-AT' => 100, 'de-DE' => 100];
    }
    return ['es-MX' => 80, 'es-ES' => 80, 'de-AT' => 80, 'de-DE' => 80];
  }

}
