<?php

/**
 * @file
 * Sync and management
 */


// ----  Other Functions


/**
 * Returns the site callback url.
 * */
function lingotek_get_notifications_url() {
  global $base_url;
  return $base_url . '/' . LINGOTEK_NOTIFICATIONS_URL;
}

/**
 * Registers the site translation notfication callback.
 * This URL will be called when a document translation is complete, and can be downloaded.
 *
 * Format:  ?doc_id={document_id}&target_code={target_language}&project_id={project_id}
 * */
function lingotek_notifications() {

  $document_id = ( isset($_GET['doc_id']) ) ? $_GET['doc_id'] : NULL;
  $lingotek_locale = ( isset($_GET['target_code']) ) ? $_GET['target_code'] : NULL;
  $project_id = ( isset($_GET['project_id']) ) ? $_GET['project_id'] : NULL;

  if (!isset($document_id) || !isset($lingotek_locale)) {
    return drupal_json_output(array("message" => "Missing Required Parameter(s).  Required: doc_id, target_code"));
  }

  // Adding a delay in the update.  Without the delay all the different language updates hit at once, causing node lock issues as multiple languages try to update the same node at once.
  $min = 0;
  $max = 3;
  $sleep = rand($min, $max);
  sleep($sleep);

  include_once('lingotek.batch.inc');
  $context = '';

  $target_drupal_language_code = Lingotek::convertLingotek2Drupal($lingotek_locale);
  $nid = LingotekSync::getNodeIdFromDocId($document_id);

  watchdog('lingotek_callback', '
      node: @node_id
      <br /><strong>doc:</strong> @document_id
      <br /><strong>code:</strong> @language_code
      <br /><strong>project:</strong> @project_id', array(
    '@node_id' => $nid,
    '@document_id' => $document_id,
    '@language_code' => $lingotek_locale,
    '@project_id' => $project_id
      ), WATCHDOG_DEBUG);

  if (!$nid) {
    // Look for and sync a comment if one is associated with the passed Lingotek Document ID.
    if (class_exists('LingotekComment')) {

      $source_language = lingotek_get_source_language(); //TO-DO: use the source_language of the comment?
      if ($comment = LingotekComment::loadByLingotekDocumentId($document_id, $source_language, $project_id)) {
        $replacements = array(
          '@id' => $comment->id,
          '@document' => $document_id,
          '@language_code' => $lingotek_locale,
          '@project_id' => $project_id,
        );

        if ($comment->updateLocalContent()) {
          if (variable_get('lingotek_api_debug', FALSE)) {
            watchdog('lingotek_debug', 'Updated local content for <strong>comment</strong> @id based on hit
                from external API for document: @document, language code @language_code, project ID: @project_id', $replacements, WATCHDOG_DEBUG);
          }
        }
        else {
          if (variable_get('lingotek_api_debug', FALSE)) {
            watchdog('lingotek_debug', 'Unable to update local content for <strong>comment</strong> @id based on hit
                from external API for document: @document, language code @language_code, project ID: @project_id', $replacements, WATCHDOG_ERROR);
          }
        }
      }
    }
    else {
      watchdog('lingotek', 'LingotekComment class not found.
          Please clear the Drupal cache to refresh the autoload registry', array(), WATCHDOG_ERROR);
    }
  }
  else {
    $node = node_load($nid);
    $source_language = $node->language;
    $node_setting = lingotek_lingonode($nid, 'sync_method');
    $auto_download = $node_setting !== FALSE ? $node_setting : variable_get('lingotek_sync', TRUE);

    if ($auto_download) { // download only when automatic download is enabled
      lingotek_sync_download_node_target($nid, $lingotek_locale, $context);
    }
  }

  $found = $nid || (isset($comment) && $comment) ? TRUE : FALSE;

  //print_r($comment);
  //Response
  $response = $found ? array_merge($_GET, array(
        'target_drupal_language_code' => $target_drupal_language_code,
        'source_language' => $source_language,
        'type' => isset($comment) ? 'comment' : 'node',
        'id' => isset($comment) ? $comment->cid : $nid,
        'found' => $found,
        'download' => $found && isset($comment) ? TRUE : (isset($auto_download) && $auto_download == TRUE)
      )) : array_merge($_GET, array(
        'found' => $found
      ));

  drupal_json_output($response);
}

/**
 * The main function responsible for syncing node/document translation.
 * 
 * */
function lingotek_sync() {
  $parameters = $_GET;
  $method = $_SERVER['REQUEST_METHOD'];
  $status = "200";
  $test = isset($parameters['test']) && $parameters['test'] && strcmp($parameters['test'], 'false') != 0 ? true : false;
  $request = array(
    'test' => $test,
    'method' => $method,
    'parameters' => $parameters
  );
  $request['upload'] = $upload = isset($parameters['upload']) && $parameters['upload'] && strcmp($parameters['upload'], 'false') != 0 ? true : false;
  $request['download'] = $download = isset($parameters['download']) && $parameters['download'] && strcmp($parameters['download'], 'false') != 0 ? true : false;
  $request['lingotek_locales'] = $lingotek_locales = isset($parameters['codes']) ? array_map(function($val) {
            return trim($val);
          }, explode(',', $parameters['codes'])) : array();
  $request['upload_et'] = $upload_et = isset($parameters['upload_et']) && $parameters['upload_et'] && strcmp($parameters['upload_et'], 'false') != 0 ? true : false;
  $request['download_incomplete'] = $download_incomplete = isset($parameters['download_incomplete']) && $parameters['download_incomplete'] && strcmp($parameters['download_incomplete'], 'false') != 0 ? true : false;
  
  if($test) {
    lingotek_json_output_cors($request, $status, array('methods_allowed' => 'GET,POST'));
  } else {
    lingotek_sync_batch_create($upload, $download, $upload_et, $download_incomplete);
  }
}

/**
 * The API endpoint for bulk translation management
 */
function lingotek_sync_endpoint() {
  $parameters = array();
  $method = $_SERVER['REQUEST_METHOD'];
  $status = "200";
  $request = array(
    'method' => $method,
  );
  $response = array();

  switch ($method) {
    case 'GET':
      $request['parameters'] = $parameters = $_GET;
      /*$request['doc_ids'] = $document_ids = isset($parameters['doc_ids']) ? array_map(function($val) {
              return trim($val);
            }, explode(',', $parameters['doc_ids'])) : array();*/
      $response = LingotekSync::getReport();
      break;
    case 'POST': case 'PUT': case 'DELETE':
    default:
      parse_str(file_get_contents("php://input"), $parameters);
      $status = "405 Method Not Allowed";
      break;
  }

  return lingotek_json_output_cors($response, $status, array('methods_allowed' => 'GET'));
}

function lingotek_sync_calc(){
  /*if ($report !== FALSE && $counts_only) {
      $counts = array();
      if (isset($report->byTargetLocale)) {
        
      }
      if (isset($report->byDocumentIdAndTargetLocale)){
        
      }
      return $counts;
    }*/
}