<?php

/**
 * @file
 * LingotekSync
 */

/**
 * A utility class for Lingotek Syncing.
 */
class LingotekSync {

  const STATUS_CURRENT = 'CURRENT';  // The node or target translation is current
  const STATUS_EDITED = 'EDITED';    // The node has been edited, but has not been uploaded to Lingotek
  const STATUS_PENDING = 'PENDING';  // The target translation is awaiting to receive updated content from Lingotek
  const STATUS_LOCKED = 'LOCKED';    // A locked node should neither be uploaded nor downloaded by Lingotek

  public static function getTargetStatus($node_id, $lingotek_locale) { //lingotek_get_target_sync_status($node_id, $lingotek_locale) {
    $key = 'target_sync_status_' . $lingotek_locale;
    return lingotek_lingonode($node_id, $key);
  }

  public static function setTargetStatus($node_id, $lingotek_locale, $status) {//lingotek_set_target_sync_status($node_id, $lingotek_locale, $node_status)
    $key = 'target_sync_status_' . $lingotek_locale;
    return lingotek_lingonode($node_id, $key, $status);
  }

  public static function setNodeStatus($node_id, $status) {
    return lingotek_lingonode($node_id, 'node_sync_status', $status);
  }

  public static function getNodeStatus($node_id) {
    return lingotek_lingonode($node_id, 'node_sync_status');
  }

  //lingotek_set_node_and_targets_sync_status
  public static function setNodeAndTargetsStatus($node_id, $node_status, $targets_status) {
    // Set the Node to EDITED.
    self::setNodeStatus($node_id, $node_status);

    // Loop though each target language, and set that target to EDITED.
    $languages = lingotek_get_target_locales();
    foreach ($languages as $lingotek_locale) {
      self::setTargetStatus($node_id, $lingotek_locale, $targets_status);
    }
  }

  // Add the node sync target language entries to the lingotek table.
  public static function insertTargetEntriesForAllNodes($lingotek_locale) {
    $query = db_select('lingotek', 'l')->fields('l');
    $query->condition('lingokey', 'node_sync_status');
    $result = $query->execute();

    while ($record = $result->fetchAssoc()) {
      $node_id = $record['nid'];
      // If the Node is CURRENT or PENDING, then we just need to pull down the new translation (because the source will have been uploaded), so set the Node and Target to PENDING.
      if ($record['lingovalue'] == self::STATUS_CURRENT) {
        self::setTargetStatus($node_id, $lingotek_locale, self::STATUS_PENDING);
      }
      else { // Otherwise, set it to EDITED
        self::setNodeStatus($node_id, self::STATUS_EDITED);
        self::setTargetStatus($node_id, $lingotek_locale, self::STATUS_EDITED);
      }
    }
  }

  // Remove the node sync target language entries from the lingotek table lingotek_delete_target_sync_status_for_all_nodes
  public static function deleteTargetEntriesForAllNodes($lingotek_locale) {
    $key = 'target_sync_status_' . $lingotek_locale;
    db_delete('lingotek')->condition('lingokey', $key)->execute();
  }

  public static function getDownloadableReport() {
     $project_id = variable_get('lingotek_project', NULL);
     $document_ids = LingotekSync::getDocIdsByStatus(LingotekSync::STATUS_PENDING);
     $api = LingotekApi::instance();
     $response = $api->getProgressReport($project_id, $document_ids, TRUE);
     
     $report = array(
       'complete' => 0,
       'complete_download_targets' => array(),
       'incomplete' => 0,
       'incomplete_download_targets' => array()
     );
     $report['complete'] = 0;// workflow complete and ready for download
     $report['incomplete'] = 0;// not workflow complete
     if(isset($response->byDocumentIdAndTargetLocale)){
       $progress_report = $response->byDocumentIdAndTargetLocale;
       foreach($progress_report as $doc_id => $target_locales){
         foreach($target_locales as $lingotek_locale => $pct_complete){
           $doc_target = array(
             'document_id' => $doc_id,
             'lingotek_locale' => $lingotek_locale
           );
           if($pct_complete == 100){
             $report['complete_download_targets'][] = $doc_target;
             $report['complete']++;
           }
           else {
             $report['incomplete_download_targets'][] = $doc_target;
             $report['incomplete']++;
           }
         }
       }
     }
     return $report;
  }
  
  //lingotek_count_nodes
  public static function getNodeCountByStatus($status) {
    $query = db_select('lingotek', 'l')->fields('l');
    $query->condition('lingokey', 'node_sync_status');
    $query->condition('lingovalue', $status);
    $result = $query->countQuery()->execute()->fetchField();
    return $result;
  }
  
  //lingotek_count_node_targets
  public static function getTargetCountByStatus($status, $lingotek_locale) {
    $node_language_target_key = 'target_sync_status_' . $lingotek_locale;

    $query = db_select('lingotek', 'l')->fields('l');
    $query->condition('lingokey', $node_language_target_key);
    $query->condition('lingovalue', $status);

    $result = $query->countQuery()->execute()->fetchAssoc();

    if (is_array($result)) {
      $count = array_shift($result);
    }
    else {
      $count = 0;
    }

    return $count;
  }

  public static function getUploadableReport() {
    $edited_nodes = self::getNodeIdsByStatus(self::STATUS_EDITED);
    $et_nodes = array();//TO-DO get nids for entity_translation nodes
    $report = array(
      'nids' => $edited_nodes,
      'count' => count($edited_nodes),
      'nids_et' => $et_nodes,
      'count_et' => count($et_nodes)
    );
    return $report;
  }
  
  public static function getReport() {
    $report = array(
      'upload' => self::getUploadableReport(),
      'download' => self::getDownloadableReport()
    );
    return $report;
  }
  
  public static function getNodeIdsByStatus($status){
    $query = db_select('lingotek', 'l');
    $query->fields('l', array('nid'));
    $query->condition('lingovalue', $status);
    $query->distinct();
    $result = $query->execute();
    $nids = $result->fetchCol();
    return $nids;
  }

  public static function getDocIdsByStatus($status) {
    $nids = self::getNodeIdsByStatus($status);

    $query = db_select('lingotek', 'l');
    $query->fields('l', array('lingovalue'));
    $query->condition('lingokey', 'document_id');
    $query->condition('nid', $nids, 'IN');
    //$query->distinct();
    $result = $query->execute();
    $doc_ids = $result->fetchCol();

    return $doc_ids;
  }

  //lingotek_get_node_id_from_document_id
  public static function getNodeIdFromDocId($lingotek_document_id) {
    $found = FALSE;
    $key = 'document_id';

    $query = db_select('lingotek', 'l')->fields('l');
    $query->condition('lingokey', $key);
    $query->condition('lingovalue', $lingotek_document_id);
    $result = $query->execute();

    if ($record = $result->fetchAssoc()) {
      $found = $record['nid'];
    }

    return $found;
  }

  public static function getDocIdFromNodeId($drupal_node_id) {
    $found = FALSE;

    $query = db_select('lingotek', 'l')->fields('l');
    $query->condition('nid', $drupal_node_id);
    $query->condition('lingokey', 'document_id');
    $result = $query->execute();

    if ($record = $result->fetchAssoc()) {
      $found = $record['lingovalue'];
    }

    return $found;
  }

}

?>
