<?php

namespace Drupal\lingotek;

use Drupal\Component\Serialization\Json;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The connecting class between Drupal and Lingotek
 */
class Lingotek implements LingotekInterface {

  protected static $instance;
  protected $api;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The Lingotek Filter manager.
   *
   * @var \Drupal\lingotek\LingotekFilterManagerInterface
   */
  protected $lingotekFilterManager;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  // Translation Status.
  const STATUS_EDITED = 'EDITED';
  const STATUS_IMPORTING = 'IMPORTING';
  const STATUS_NONE = 'NONE';
  const STATUS_REQUEST = 'REQUEST';
  const STATUS_PENDING = 'PENDING';
  const STATUS_INTERMEDIATE = 'INTERMEDIATE';
  const STATUS_CURRENT = 'CURRENT';
  const STATUS_READY = 'READY';
  const STATUS_DISABLED = 'DISABLED';
  const STATUS_ERROR = 'ERROR';
  const STATUS_CANCELLED = 'CANCELLED';

  /**
   * Status untracked means the target has not been added yet.
   */
  const STATUS_UNTRACKED = 'UNTRACKED';
  const PROGRESS_COMPLETE = 100;
  // Translation Profile.
  const PROFILE_AUTOMATIC = 'automatic';
  const PROFILE_MANUAL = 'manual';
  const PROFILE_DISABLED = 'disabled';

  const SETTINGS = 'lingotek.settings';

  /**
   * Constructs a Lingotek object.
   *
   * @param \Drupal\lingotek\Remote\LingotekApiInterface $api
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param LingotekFilterManagerInterface $lingotek_filter_manager
   *   The Lingotek Filter manager.
   */
  public function __construct(LingotekApiInterface $api, LanguageLocaleMapperInterface $language_locale_mapper, ConfigFactoryInterface $config_factory, LingotekFilterManagerInterface $lingotek_filter_manager, LingotekConfigurationServiceInterface $lingotek_configuration = NULL) {
    $this->api = $api;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->configFactory = $config_factory;
    $this->lingotekFilterManager = $lingotek_filter_manager;
    if (!$lingotek_configuration) {
      @trigger_error('The lingotek.configuration service must be passed to Lingotek::__construct, it is included in lingotek:3.1.0 and required for lingotek:4.0.0.', E_USER_DEPRECATED);
      $lingotek_configuration = \Drupal::service('lingotek.configuration');
    }
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.api'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('config.factory'),
      $container->get('lingotek.filter_manager'),
      $container->get('lingotek.configuration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    return $this->configFactory->get(static::SETTINGS)->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getEditable($key) {
    return $this->configFactory->getEditable(static::SETTINGS)->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    $this->configFactory->getEditable(static::SETTINGS)->set($key, $value)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountInfo() {
    try {
      $response = $this->api->getAccountInfo();
    }
    catch (LingotekApiException $e) {
      // TODO: log a warning
      return FALSE;
    }
    if ($response) {
      return json_decode($response->getBody(), TRUE);
    }
    // TODO: log a warning
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResources($force = FALSE) {
    return [
        'community' => $this->getCommunities($force),
        'project' => $this->getProjects($force),
        'vault' => $this->getVaults($force),
        'workflow' => $this->getWorkflows($force),
        'filter' => $this->getFilters($force),
    ];
  }

  public function getDefaults() {
    return $this->configFactory->get(static::SETTINGS)->get('default');
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunities($force = FALSE) {
    $resources_key = 'account.resources.community';
    $data = $this->configFactory->get(static::SETTINGS)->get($resources_key);
    if (empty($data) || $force) {
      $data = $this->api->getCommunities($force);
      $this->configFactory->getEditable(static::SETTINGS)->set($resources_key, $data)->save();
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getVaults($force = FALSE) {
    return $this->getResource('account.resources.vault', 'getVaults', $force);
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects($force = FALSE) {
    return $this->getResource('account.resources.project', 'getProjects', $force);
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflows($force = FALSE) {
    return $this->getResource('account.resources.workflow', 'getWorkflows', $force);
  }

  /**
   * {@inheritdoc}
   */
  public function getProject($project_id) {
    return $this->api->getProject($project_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters($force = FALSE) {
    return $this->getResource('account.resources.filter', 'getFilters', $force);
  }

  /**
   * {@inheritdoc}
   */
  public function setProjectCallBackUrl($project_id, $callback_url) {
    $args = [
      'format' => 'JSON',
      'callback_url' => $callback_url,
    ];

    $response = $this->api->setProjectCallBackUrl($project_id, $args);

    if ($response->getStatusCode() == Response::HTTP_NO_CONTENT) {
      return TRUE;
    }
    // TODO: Log item
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadDocument($title, $content, $locale, $url = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL) {
    if (!is_array($content)) {
      $data = json_decode($content, TRUE);
      // This is the quickest way if $content is not a valid json object.
      $content = ($data === NULL) ? $content : $data;
    }
    // Handle adding site defaults to the upload here, and leave
    // the handling of the upload call itself to the API.
    $defaults = [
      'format' => 'JSON',
      'project_id' => $this->configFactory->get(static::SETTINGS)->get('default.project'),
      'fprm_id' => $this->lingotekFilterManager->getFilterId($profile),
      'fprm_subfilter_id' => $this->lingotekFilterManager->getSubfilterId($profile),
      'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
    ];
    // Remove filters set to NULL
    $defaults = array_filter($defaults);
    $workflow_id = NULL;
    $metadata = $this->getIntelligenceMetadata($content);

    if ($profile !== NULL && $project = $profile->getProject()) {
      if ($project !== 'default') {
        $defaults['project_id'] = $project;
      }
    }

    if ($profile !== NULL && $vault = $profile->getVault()) {
      if ($vault === 'default') {
        $vault = $this->configFactory->get(static::SETTINGS)->get('default.vault');
      }
      $defaults['vault_id'] = $vault;
      // If we use the project workflow template default vault, we omit the
      // vault parameter and the TMS will decide.
      if ($vault === 'project_default') {
        unset($defaults['vault_id']);
      }
    }
    if ($profile !== NULL && ($workflow_id = $profile->getWorkflow()) && $workflow_id !== 'default') {
      $defaults['translation_workflow_id'] = $workflow_id;
    }
    else {
      $defaults['translation_workflow_id'] = $this->configFactory->get(static::SETTINGS)->get('default.workflow');
    }

    $args = array_merge($metadata, $defaults);

    $request_locales = [];
    $request_workflows = [];
    $request_translation_vaults = [];

    if ($profile) {
      $languages = $this->lingotekConfiguration->getEnabledLanguages();
      if (!empty($languages)) {
        foreach ($languages as $language) {
          if ($profile->hasAutomaticRequestForTarget($language->getId())) {
            $target_locale = $this->languageLocaleMapper->getLocaleForLangcode($language->getId());
            if ($target_locale !== $locale) {
              $workflow_id = $profile->getWorkflowForTarget($language->getId());
              if ($workflow_id === 'default') {
                $workflow_id = $this->configFactory->get(static::SETTINGS)
                  ->get('default.workflow');
              }
              $translation_vault_id = $profile->getVaultForTarget($language->getId());
              if ($translation_vault_id === 'default') {
                // If using overrides, we can never specify the document vault
                // as this cannot be empty, nor force to use the project template
                // vault, as it is unknown to us.
                $translation_vault_id = $this->configFactory->get(static::SETTINGS)
                  ->get('default.vault');
              }
              $request_locales[] = $target_locale;
              $request_workflows[] = $workflow_id;
              $request_translation_vaults[] = $translation_vault_id;
            }
          }
        }
      }
    }

    if (!empty($request_locales) && !empty($request_workflows)) {
      $args['translation_locale_code'] = $request_locales;
      $args['translation_workflow_id'] = $request_workflows;
      $args['translation_vault_id'] = $request_translation_vaults;
    }
    if (($workflow_id && $workflow_id === 'project_default') || empty($request_locales)) {
      unset($args['translation_workflow_id']);
    }

    $args = array_merge(['content' => json_encode($content), 'title' => $title, 'locale_code' => $locale], $args);
    if ($url !== NULL) {
      $args['external_url'] = $url;
    }
    if ($job_id !== NULL) {
      $args['job_id'] = $job_id;
    }
    $response = $this->api->addDocument($args);

    $statusCode = $response->getStatusCode();
    if ($statusCode == Response::HTTP_ACCEPTED) {
      $responseBody = Json::decode($response->getBody(), TRUE);
      if (!empty($responseBody) && !empty($responseBody['properties']['id'])) {
        return $responseBody['properties']['id'];
      }
      else {
        return FALSE;
      }
    }
    elseif ($statusCode == Response::HTTP_PAYMENT_REQUIRED) {
      // This is only applicable to subscription-based connectors, but the
      // recommended action is to present the user with a message letting them
      // know their Lingotek account has been disabled, and to please contact
      // support to re-enable their account.
      $responseBody = Json::decode($response->getBody());
      $message = '';
      if (!empty($responseBody) && isset($responseBody['messages'])) {
        $message = $responseBody['messages'][0];
      }
      throw new LingotekPaymentRequiredException($message);
    }
    else {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDocument($doc_id, $content, $url = NULL, $title = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL, $locale = NULL) {
    // TODO: Fix the order of the arguments to be consistent with uploadDocument. We can't do this right now without breaking backwards compatibility
    if (!is_array($content)) {
      if ($content !== NULL) {
        $data = json_decode($content, TRUE);
        // This is the quickest way if $content is not a valid json object.
        $content = ($data === NULL) ? $content : $data;
      }
    }

    $defaults = [
      'format' => 'JSON',
      'fprm_id' => $this->lingotekFilterManager->getFilterId($profile),
      'fprm_subfilter_id' => $this->lingotekFilterManager->getSubfilterId($profile),
      'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
    ];

    if ($profile && $project = $profile->getProject()) {
      if ($project !== 'default') {
        $defaults['project_id'] = $project;
      }
    }
    $metadata = $this->getIntelligenceMetadata($content);
    if ($profile !== NULL && ($workflow_id = $profile->getWorkflow()) && $workflow_id !== 'default') {
      $defaults['translation_workflow_id'] = $workflow_id;
    }
    else {
      $defaults['translation_workflow_id'] = $this->configFactory->get(static::SETTINGS)->get('default.workflow');
    }
    $args = array_merge($metadata, $defaults);

    if ($url !== NULL) {
      $args['external_url'] = $url;
    }
    if ($title !== NULL) {
      $args['title'] = $title;
    }
    if ($job_id !== NULL) {
      $args['job_id'] = $job_id;
    }
    if ($content !== NULL && !empty($content)) {
      $args = array_merge(['content' => json_encode($content)], $args);
    }
    else {
      // IF there's no content, let's remove filters, we may want to update only
      // the Job ID.
      unset($args['format']);
      unset($args['fprm_id']);
      unset($args['fprm_subfilter_id']);
      unset($args['external_application_id']);
    }

    $request_locales = [];
    $request_workflows = [];
    $workflow_id = NULL;
    if ($profile) {
      $languages = $this->lingotekConfiguration->getEnabledLanguages();
      if (!empty($languages)) {
        foreach ($languages as $language) {
          if ($profile->hasAutomaticRequestForTarget($language->getId())) {
            $target_locale = $this->languageLocaleMapper->getLocaleForLangcode($language->getId());
            if ($locale !== NULL && $target_locale !== $locale) {
              $workflow_id = $profile->getWorkflowForTarget($language->getId());
              if ($workflow_id === 'default') {
                $workflow_id = $this->configFactory->get(static::SETTINGS)->get('default.workflow');
              }
              $request_locales[] = $target_locale;
              $request_workflows[] = $workflow_id;
            }
          }
        }
      }
    }

    if (!empty($request_locales) && !empty($request_workflows)) {
      $args['translation_locale_code'] = $request_locales;
      $args['translation_workflow_id'] = $request_workflows;
    }
    if (($workflow_id && $workflow_id === 'project_default') || empty($request_locales)) {
      unset($args['translation_workflow_id']);
    }
    $response = $this->api->patchDocument($doc_id, $args);
    $statusCode = $response->getStatusCode();
    if ($statusCode == Response::HTTP_ACCEPTED) {
      $responseBody = Json::decode($response->getBody(), TRUE);
      if (empty($responseBody)) {
        return TRUE;
      }
      else {
        $nextDocId = $responseBody['next_document_id'];
        return $nextDocId;
      }
    }
    elseif ($statusCode == Response::HTTP_PAYMENT_REQUIRED) {
      // This is only applicable to subscription-based connectors, but the
      // recommended action is to present the user with a message letting them
      // know their Lingotek account has been disabled, and to please contact
      // support to re-enable their account.
      $responseBody = Json::decode($response->getBody());
      $message = '';
      if (!empty($responseBody) && isset($responseBody['messages'])) {
        $message = $responseBody['messages'][0];
      }
      throw new LingotekPaymentRequiredException($message);
    }
    elseif ($statusCode == Response::HTTP_GONE) {
      // Set the status of the document back to its pre-uploaded state.
      // Typically this means the state would be set to Upload, or None but this
      // may vary depending on connector. Essentially, the content’s status
      // indicator should show that the source content needs to be re-uploaded
      // to Lingotek.
      throw new LingotekDocumentArchivedException($doc_id, sprintf('Document %s has been archived.', $doc_id));
    }
    elseif ($statusCode == Response::HTTP_LOCKED) {
      // Update the connector’s document mapping with the ID provided in the
      // next_document_id within the API response. This new ID represents the
      // new version of the document.
      $responseBody = Json::decode($response->getBody());
      $nextDocId = '';
      if (!empty($responseBody) && isset($responseBody['next_document_id'])) {
        $nextDocId = $responseBody['next_document_id'];
      }
      throw new LingotekDocumentLockedException($doc_id, $nextDocId, sprintf('Document %s has been updated with a new version. Use document %s for all future interactions.', $doc_id, $nextDocId));
    }
    return FALSE;
  }

  /**
   * Pulls the Intelligence Metadata from the Lingotek Metadata and returns it.
   *
   * @param array $data
   *   The structure of a document content.
   *
   * @return array
   */
  public function getIntelligenceMetadata(&$data) {
    $metadata = [];
    if (is_array($data) && isset($data['_lingotek_metadata']['_intelligence'])) {
      $metadata = $data['_lingotek_metadata']['_intelligence'];
      unset($data['_lingotek_metadata']['_intelligence']);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocument($doc_id) {
    $result = FALSE;
    try {
      $response = $this->api->cancelDocument($doc_id);
      $status_code = $response->getStatusCode();
      if ($status_code == Response::HTTP_NO_CONTENT) {
        $result = TRUE;
      }
    }
    catch (LingotekApiException $ltkException) {
      if ($ltkException->getCode() === 400) {
        if (strpos($ltkException->getMessage(), '"Unable to cancel documents which are already in a completed state. Current status: COMPLETE"') > 0) {
          // We ignore errors for complete documents.
          $result = TRUE;
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelDocumentTarget($doc_id, $locale) {
    $result = FALSE;
    try {
      $response = $this->api->cancelDocumentTarget($doc_id, $locale);
      $status_code = $response->getStatusCode();
      if ($status_code == Response::HTTP_NO_CONTENT) {
        $result = TRUE;
      }
    }
    catch (LingotekApiException $ltkException) {
      if ($ltkException->getCode() === 400) {
        if (strpos($ltkException->getMessage(), '"Unable to cancel translations which are already in a completed state. Current status: COMPLETE"') > 0) {
          // We ignore errors for complete documents.
          $result = TRUE;
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function addTarget($doc_id, $locale, LingotekProfileInterface $profile = NULL) {
    $workflow_id = NULL;
    $vault_id = NULL;
    $drupal_language = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale);

    if ($profile !== NULL && $workflow_id = $profile->getWorkflowForTarget($drupal_language->getId())) {
      switch ($workflow_id) {
        case 'project_default':
          $workflow_id = NULL;
          break;

        case 'default':
          $workflow_id = $this->configFactory->get(static::SETTINGS)->get('default.workflow');
          break;
      }
    }

    if ($profile !== NULL && $vault_id = $profile->getVaultForTarget($drupal_language->getId())) {
      if ($vault_id === 'default') {
        $vault_id = NULL;
      }
    }
    $response = $this->api->addTranslation($doc_id, $locale, $workflow_id, $vault_id);
    $statusCode = $response->getStatusCode();
    if ($statusCode == Response::HTTP_CREATED) {
      return TRUE;
    }
    elseif ($statusCode == Response::HTTP_PAYMENT_REQUIRED) {
      // This is only applicable to subscription-based connectors, but the
      // recommended action is to present the user with a message letting them
      // know their Lingotek account has been disabled, and to please contact
      // support to re-enable their account.
      $responseBody = Json::decode($response->getBody());
      $message = '';
      if (!empty($responseBody) && isset($responseBody['messages'])) {
        $message = $responseBody['messages'][0];
      }
      throw new LingotekPaymentRequiredException($message);
    }
    elseif ($statusCode == Response::HTTP_GONE) {
      // Set the status of the document back to its pre-uploaded state.
      // Typically this means the state would be set to Upload, or None but this
      // may vary depending on connector. Essentially, the content’s status
      // indicator should show that the source content needs to be re-uploaded
      // to Lingotek.
      return FALSE;
    }
    elseif ($statusCode == Response::HTTP_LOCKED) {
      // Update the connector’s document mapping with the ID provided in the
      // next_document_id within the API response. This new ID represents the
      // new version of the document.
      $responseBody = Json::decode($response->getBody());
      if (empty($responseBody)) {
        return FALSE;
      }
      else {
        $nextDocId = $responseBody['next_document_id'];
        return FALSE;
      }
    }
    return FALSE;
  }

  public function getLocales() {
    $data = $this->api->getLocales();
    $locales = [];
    if ($data) {
      foreach ($data['entities'] as $locale) {
        $locales[] = $locale['properties']['code'];
      }
    }
    return $locales;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalesInfo() {
    $data = $this->api->getLocales();
    $locales = [];
    if ($data) {
      foreach ($data['entities'] as $locale) {
        $languageCode = $locale['properties']['language_code'];
        $countryCode = $locale['properties']['country_code'];
        $title = $locale['properties']['title'];
        $language = $locale['properties']['language'];
        $country = $locale['properties']['country'];
        $code = $locale['properties']['code'];

        $locales[$code] = [
          'code' => $code,
          'language_code' => $languageCode,
          'title' => $title,
          'language' => $language,
          'country_code' => $countryCode,
          'country' => $country,
        ];
      }
    }
    return $locales;
  }

  protected function getResource($resources_key, $func, $force = FALSE) {
    $data = $this->configFactory->get(static::SETTINGS)->get($resources_key);
    if (empty($data) || $force) {
      $community_id = $this->configFactory->get(static::SETTINGS)->get('default.community');
      $data = $this->api->$func($community_id);
      $this->configFactory->getEditable(static::SETTINGS)->set($resources_key, $data)->save();
      $keys = explode(".", $resources_key);
      $default_key = 'default.' . end($keys);
      $this->setValidDefaultIfNotSet($default_key, $data);
    }
    return $data;
  }

  protected function setValidDefaultIfNotSet($default_key, $resources) {
    $default_value = $this->configFactory->get(static::SETTINGS)->get($default_key);
    $valid_resource_ids = array_keys($resources);
    if ($default_key === 'default.filter') {
      $valid_resource_ids[] = 'drupal_default';
    }
    if (empty($default_value) || !in_array($default_value, $valid_resource_ids)) {
      $value = current($valid_resource_ids);
      $this->configFactory->getEditable(static::SETTINGS)->set($default_key, $value)->save();
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getUploadedTimestamp($doc_id) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $modified_date = FALSE;
    try {
      $response = $this->api->getDocumentInfo($doc_id);
      if ($response->getStatusCode() == Response::HTTP_OK) {
        $response_json = json_decode($response->getBody(), TRUE);
        // We have millisecond precision in Lingotek.
        $modified_date = intval(floor($response_json['properties']['last_uploaded_date'] / 1000));
      }
    }
    catch (LingotekApiException $exception) {
      return FALSE;
    }
    return $modified_date;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentStatus($doc_id) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    try {
      $response = $this->api->getDocumentStatus($doc_id);
      if ($response->getStatusCode() == Response::HTTP_OK) {
        // If an exception didn't happen, the document is succesfully imported.
        // The status value there is related with translation status, so we must
        // ignore it.
        return TRUE;
      }
    }
    catch (LingotekApiException $exception) {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentTranslationStatus($doc_id, $locale) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $response = $this->api->getDocumentTranslationStatus($doc_id, $locale);
    $progress = FALSE;
    if ($response->getStatusCode() == Response::HTTP_OK) {
      $progress_json = json_decode($response->getBody(), TRUE);
      $lingotek_locale = str_replace("_", "-", $locale);
      if (!empty($progress_json['entities'])) {

        foreach ($progress_json['entities'] as $index => $data) {
          if ($data['properties']['locale_code'] === $lingotek_locale) {
            $progress = $data['properties']['percent_complete'];
            if ($data['properties']['status'] === Lingotek::STATUS_CANCELLED) {
              $progress = $data['properties']['status'];
            }
            break;
          }
        }
      }
      if ($progress === self::PROGRESS_COMPLETE) {
        return TRUE;
      }
    }
    return $progress;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentTranslationStatuses($doc_id) {
    $statuses = [];
    try {
      $response = $this->api->getDocumentTranslationStatuses($doc_id);
    }
    catch (LingotekApiException $e) {
      // No targets found for this doc
      return $statuses;
    }
    if ($response->getStatusCode() == Response::HTTP_OK) {
      $progress_json = json_decode($response->getBody(), TRUE);
      if (!empty($progress_json['entities'])) {
        foreach ($progress_json['entities'] as $index => $data) {
          $lingotek_locale = $data['properties']['locale_code'];
          $statuses[$lingotek_locale] = $data['properties']['percent_complete'];
          // ToDo: We should have an structure for this, instead of treating this as an snowflake.
          if ($data['properties']['status'] === Lingotek::STATUS_CANCELLED) {
            $statuses[$lingotek_locale] = $data['properties']['status'];
          }
        }
      }
    }
    return $statuses;
  }

  /**
   * {@inheritdoc}
   */
  public function downloadDocument($doc_id, $locale) {
    // For now, a passthrough to the API object so the controllers do not
    // need to include that class.
    $response = $this->api->getTranslation($doc_id, $locale, $this->configFactory->get(static::SETTINGS)->get('preference.enable_download_source'));
    $statusCode = $response->getStatusCode();
    if ($statusCode == Response::HTTP_OK) {
      return json_decode($response->getBody(), TRUE);
    }
    elseif ($statusCode == Response::HTTP_GONE) {
      // Set the status of the document back to its pre-uploaded state.
      // Typically this means the state would be set to Upload, or None but this
      // may vary depending on connector. Essentially, the content’s status
      // indicator should show that the source content needs to be re-uploaded
      // to Lingotek.
      return FALSE;
    }
    return FALSE;
  }

}
