<?php

/**
 * @file
 * Defines Drupal\lingotek\LingotekApi
 */

class LingotekApi {
  /**
   * The status string representing a successful API call.
   */
  const RESPONSE_STATUS_SUCCESS = 'success';
  
  /**
   * The server name of the Lingotek production instance.
   */
  const LINGOTEK_SERVER_PRODUCTION = 'myaccount.lingotek.com';

  /**
   * Holds the static instance of the singleton object.
   *
   * @var LingotekApi
   */
  private static $instance;

  /**
   * Debug status for extra logging of API calls.
   *
   * @var bool
   */
  private $debug;
  
  /**
   * The endpoint for API calls.
   *
   * @var string
   */
  private $api_url;

  /**
   * Gets the singleton instance of the API class.
   *
   * @return LingotekApi
   *   An instantiated LingotekApi object.
   */
  public static function instance() {
    if (!isset(self::$instance)) {
      $class_name = __CLASS__;
      self::$instance = new $class_name();
    }

    return self::$instance;
  }

  /**
   * Add a document to the Lingotek platform.
   *
   * Uploads the node's field content in the node's selected language.
   *
   * @param object $node
   *   A Drupal node object.
   */
  public function addContentDocument($node) {
    global $_lingotek_locale;
    $success = TRUE;

    $project_id = (!empty($node->lingotek_project_id)) ? $node->lingotek_project_id : variable_get('lingotek_project', NULL);

    if ($project_id) {
      $parameters = array(
        'projectId' => $project_id,
        'documentName' => $node->title,
        'documentDesc' => $node->title,
        'format' => $this->xmlFormat(),
        'sourceLanguage' => $_lingotek_locale[$node->language],
        'tmVaultId' => (!empty($node->lingotek_vault_id)) ? $node->lingotek_vault_id : variable_get('lingotek_vault', 1),
        'content' => lingotek_xml_node_body($node),
        'note' => url('node/' . $node->nid, array('absolute' => TRUE, 'alias' => TRUE))
      );

      $this->addAdvancedParameters($parameters, $node);

      if ($result = $this->request('addContentDocument', $parameters)) {
        lingotek_lingonode($node->nid, 'document_id_' . $node->language, $result->id);
      }
      else {
        $success = FALSE;
      }
    }
    else {
      watchdog('lingotek', 'Skipping addContentDocument call for node @node_id. Could not locate Lingotek project ID.',
        array('@node_id' => $node->nid), WATCHDOG_ERROR);

      $success = FALSE;
    }

    return $success;
  }


  /**
   * Adds a target language to an existing Lingotek Document.
   *
   * @param int $lingotek_document_id
   *   The document to which the new translation target should be added.
   * @param string $target_language_code
   *   The two letter code representing the language which should be added as a translation target.
   *
   * @return mixed
   *  The ID of the new translation target in the Lingotek system, or FALSE on error.
   */
  public function addTranslationTarget($lingotek_document_id, $target_language_code) {
    global $_lingotek_client, $_lingotek_locale;

    $parameters = array(
      'documentId' => $lingotek_document_id,
      'applyWorkflow' => 'true', // Ensure that as translation targets are added, the associated project's Workflow template is applied.
      'targetLanguage' => $_lingotek_locale[$target_language_code]
    );

    if ($new_translation_target = $this->request('addTranslationTarget', $parameters)) {
      return $new_translation_target->id;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Gets the phase data for the active phase of the specified translation target.
   *
   * @param int $translation_target_id
   *   The ID of a translation target on the Lingotek system.
   *
   * @return mixed
   *   An object representing data for the current translation phase, or FALSE on error.
   */
  public function currentPhase($translation_target_id) {
    if ($target = $this->getTranslationTarget($translation_target_id)) {
      if (!empty($target->phases)) {
        $current_phase = FALSE;
        foreach ($target->phases as $phase) {

          if (!$phase->isMarkedComplete) {
            $current_phase = $phase;
            break;
          }
        }

        // Return either the first uncompleted phase, or the last phase if all phases are complete.
        return ($current_phase) ? $current_phase : end($target->phases);
      }
      else {
        return FALSE;
      }
    }
    else {
      return FALSE;
    }
  }

  /**
   * Gets Lingotek Document data for the specified document.
   *
   * @param int $document_id
   *   The ID of the Lingotek Document to retrieve.
   *
   * @return mixed
   *  The API response object with Lingotek Document data, or FALSE on error.
   */
  public function getDocument($document_id) {
    $params = array('documentId' => $document_id);

    if ($document = $this->request('getDocument', $params)) {
      return $document;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Gets a translation target.
   *
   * This fetches an target language object for a specific document.
   *
   * @param int $translation_target_id
   *   ID for the target language object.
   * @return
   *   Object representing a target language for a specific document in the Lingotek platform, or FALSE on error.
   */
  function getTranslationTarget($translation_target_id) {
    $targets = &drupal_static(__FUNCTION__);

    $params = array(
      'translationTargetId' => $translation_target_id
    );

    if (isset($targets[$translation_target_id])) {
      return $targets[$translation_target_id];
    }
    elseif ($output = $this->request('getTranslationTarget', $params)) {
      $targets[$translation_target_id] = $output;
      return $output;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Gets available Lingotek projects.
   *
   * @return array
   *   An array of available projects with project IDs as keys, project labels as values.
   */
  public function listProjects() {
    $projects = array();

    if ($projects_raw = $this->request('listProjects')) {
      foreach ($projects_raw->projects as $project) {
        $projects[$project->id] = $project->name;
      }
    }

    return $projects;
  }

  /**
   * Gets available Lingotek Workflows.
   *
   * @return array
   *   An array of available Workflows with workflow IDs as keys, workflow labels as values.
   */
  public function listWorkflows() {
    $workflows = array();

    if ($workflows_raw = $this->request('listWorkflows')) {
      foreach ($workflows_raw->workflows as $workflow) {
        $workflows[$workflow->id] = $workflow->name;
      }
    }

    return $workflows;
  }

  /**
   * Gets available Lingotek Translation Memory vaults.
   *
   * @return array
   *   An array of available vaults.
   */
  public function listVaults() {
    $vaults = array();

    if ($vaults_raw = $this->request('listTMVaults')) {
      if (!empty($vaults_raw->personalVaults)) {
        foreach ($vaults_raw->personalVaults as $vault) {
          $vaults['Personal Vaults'][$vault->id] = $vault->name;
        }
      }

      if (!empty($vaults_raw->publicVaults)) {
        foreach ($vaults_raw->publicVaults as $vault) {
          $vaults['Public Vaults'][$vault->id] = $vault->name;
        }
      }
    }

    return $vaults;
  }

  /**
   * Updates the content of an existing Lingotek document with the current node contents.
   *
   * @param stdClass $node
   *   A Drupal node object.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function updateContentDocument($node) {
    $parameters = array(
      'documentId' => lingotek_lingonode($node->nid, 'document_id_' . $node->language),
      'content' => lingotek_xml_node_body($node),
      'format' => $this->xmlFormat(),
    );

    $this->addAdvancedParameters($parameters, $node);

    return ($this->request('updateContentDocument', $parameters)) ? TRUE : FALSE;
  }

  /**
   * Gets the appropriate format code for the current system state
   */
  public function xmlFormat() {
    return (variable_get('lingotek_advanced_parsing', FALSE)) ? 'XML_OKAPI' : 'XML';
  }
  
  /**
   * Tests the current configuration to ensure that API calls can be made.
   */
  public function testAuthentication() {
    $valid_connection = &drupal_static(__FUNCTION__);
    
    if (!isset($valid_connection)) {
      $valid_connection = ($this->listProjects()) ? TRUE : FALSE;
    }

    return $valid_connection; 
  }  

  /**
   * Calls a Lingotek API method.
   *
   * @return mixed
   *   On success, a stdClass object of the returned response data, FALSE on error.
   */
  public function request($method, $parameters = array()) {
    $response_data = FALSE;
    
    // Every v4 API request needs to have the externalID (Lingotek ID) parameter present.
    $parameters += array('externalId' => variable_get('lingotek_login_id', ''));
    
    module_load_include('php', 'lingotek', 'lib/oauth-php/library/OAuthStore');
    module_load_include('php', 'lingotek', 'lib/oauth-php/library/OAuthRequester');
    
    $consumer_options = array(
      'consumer_key' => variable_get('lingotek_oauth_consumer_id', ''),
      'consumer_secret' => variable_get('lingotek_oauth_consumer_secret', '')
    );

    $request_method = "POST";

    $timer_name = $method . '-' . microtime(TRUE);
    timer_start($timer_name);

    $response = NULL;
    try {
      OAuthStore::instance('2Leg', $consumer_options);   
    	$request = new OAuthRequester($this->api_url . '/' . $method, $request_method, $parameters);
    	$result = $request->doRequest();
    	$response = json_decode($result['body']);
    }
    catch (OAuthException2 $e) {
      watchdog('lingotek', 'Failed OAuth request.
      <br />Message: @message. <br />Method: @name. <br />Parameters: !params. <br />Response: !response',
        array('@message' => $e->getMessage(), '@name' => $method, '!params' => $this->watchdogFormatObject($parameters),
        '!response' => $this->watchdogFormatObject($response)), WATCHDOG_ERROR);      
    }

    $timer_results = timer_stop($timer_name);

    if ($this->debug) {
      $message_params = array(
        '@method' => $method,
        '!params' => $this->watchdogFormatObject($parameters),
        '!response' => $this->watchdogFormatObject($response),
        '@response_time' => number_format($timer_results['time']) . ' ms',
      );

      watchdog('lingotek_debug', '<strong>Called API method</strong>: @method
      <br /><strong>Response Time:</strong> @response_time<br /><strong>Params</strong>: !params<br /><strong>Response:</strong> !response',
      $message_params, WATCHDOG_DEBUG);
    }
    if (!is_null($response) && $response->results == self::RESPONSE_STATUS_SUCCESS) {
      $response_data = $response;
    }
    else {
      watchdog('lingotek', 'Failed API call.<br />Method: @name. <br />Parameters: !params. <br />Response: !response',
        array('@name' => $method, '!params' => $this->watchdogFormatObject($parameters),
        '!response' => $this->watchdogFormatObject($response)), WATCHDOG_ERROR);
    }

    return $response_data;
  }

  /**
   * Formats a complex object for presentation in a watchdog message.
   */
  private function watchdogFormatObject($object) {
    return '<pre>' . htmlspecialchars(var_export($object, TRUE)) . '</pre>';
  }

  /**
   * Adds advanced parameters for use with addContentDocument and updateContentDocument.
   *
   * @param array $parameters
   *   An array of API request parameters.
   * @param object $node
   *   A Drupal node object.
   */
  private function addAdvancedParameters(&$parameters, $node) {
    // Extra parameters when using advanced XML configuration.
    $advanced_parsing_enabled = variable_get('lingotek_advanced_parsing', FALSE);
    $use_advanced_parsing = ($advanced_parsing_enabled ||
      (!$advanced_parsing_enabled && lingotek_lingonode($node->nid, 'use_advanced_parsing')));

    if ($use_advanced_parsing) {
      $advanced_parameters = array(
        'fprmFileContents' => variable_get('lingotek_advanced_xml_config1',''),
        'secondaryFprmFileContents' => variable_get('lingotek_advanced_xml_config2',''),
        'secondaryFilter' => 'okf_html',
      );

      $parameters = array_merge($parameters, $advanced_parameters);
    }
  }

  /**
   * Private constructor.
   */
  private function __construct() {
    $this->debug = variable_get('lingotek_api_debug', FALSE);
    $this->api_url = variable_get('lingotek_url', 'http://' . self::LINGOTEK_SERVER_PRODUCTION) . '/lingopoint/api';
  }

  /**
   * Private clone implementation.
   */
  private function __clone() {}

}
