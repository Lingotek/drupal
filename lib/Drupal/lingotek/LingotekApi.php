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
   * Holds the static instance of the singleton object.
   *
   * @var LingotekApi
   */
  private static $instance;

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

    lingotek_trace('LingotekApi::addTranslationTarget()', array('document_id' => $lingotek_document_id, 'target_language' => $target_language_code));

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
   * Calls an API method.
   *
   * @return mixed
   *   On success, a stdClass object of the returned response data, FALSE on error.
   */
  private function request($method, $parameters = array()) {
    global $_lingotek_client;

    $response_data = FALSE;

    if ($_lingotek_client->canLogIn()) {
      $response = $_lingotek_client->request($method, $parameters);
      if ($response->results == self::RESPONSE_STATUS_SUCCESS) {
        $response_data = $response;
      }
      else {
        watchdog('lingotek', 'Failed API call. Method: @name. Parameters: @params.',
          array('@name' => $method, '@params' => serialize($parameters)), WATCHDOG_ERROR);
      }
    }

    return $response_data;
  }

  /**
   * Private constructor.
   */
  private function __construct() {}

  /**
   * Private clone implementation.
   */
  private function __clone() {}

}
