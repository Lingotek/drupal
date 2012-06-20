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
   * Gets available Lingotek projects.
   *
   * @return array
   *   An array of available projects with project IDs as keys, project labels as values.
   */
  public function listProjects() {
    $projects = array();
    
    if ($projects_raw = $this->request('listProjects')) {
      foreach ($projects_raw->projects as $project) {
        $options[$project->id] = $project->name;
      }      
    }

    return $options;
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
