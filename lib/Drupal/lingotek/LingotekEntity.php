<?php

/**
 * @file
 * Defines LingotekEntity.
 */
 
/**
 * A class wrapper for Lingotek-specific behavior on nodes.
 */
class LingotekEntity implements LingotekTranslatableEntity {  
  /**
   * A Drupal node.
   *
   * @var object
   */
  protected $node;
  
  /**
   * The Drupal entity type associated with this class
   */
  protected $entity_type;
  
  /**
   * Lingotek Lingonode properties.
   *
   * @var object
   */
  protected $lingonode;
  
  /**
   * A reference to the Lingotek API.
   *
   * @var LingotekApi
   */
  protected $api = NULL;
  
  public $language = '';

  /**
   * Constructor.
   *
   * This is private since we want consumers to instantiate via the factory methods.
   *
   * @param object $node
   *   A Drupal node.
   */
  private function __construct($node, $entity_type) {
    $this->node = $node;
    $this->nid = $node->nid;
    $this->language = $node->language;
    $this->entity_type = $entity_type;
  }
  
  /**
   * Injects reference to an API object.
   *
   * @param LingotekApi $api
   *   An instantiated Lingotek API object.
   */
  public function setApi(LingotekApi $api) {
    $this->api = $api;
  }
  
  /**
   * Factory method for getting a loaded LingotekNode object.
   *
   * @param object $node
   *   A Drupal node.
   *
   * @return LingotekNode
   *   A loaded LingotekNode object.
   */
  public static function load($node, $entity_type) {
    $node = new LingotekEntity($node, $entity_type);
    $node->setApi(LingotekApi::instance());
    return $node;
  }
  
  
  /**
   * Method for loading the values for the lingonode
   * 
   * @param none
   * 
   * @return boolean
   * 
   */
  private function loadLingonode(){
    if($this->nid){
    // add in all values from the lingonode table (when missing set use global defaults)
      $lingonode = (object)lingotek_lingonode($this->nid);
      if($lingonode){
        $lingonode->auto_download = $lingonode->sync_method === FALSE ? variable_get('lingotek_sync') : $lingonode->sync_method;
        $this->lingonode = $lingonode;
        return TRUE;
      }
    }
    return FALSE;
  }
  
  /**
   * Factory method for getting a loaded LingotekNode object.
   *
   * @param int $node_id
   *   A Drupal node ID.
   *
   * @return mixed
   *   A loaded LingotekNode object on success, FALSE on failure.
   */
  public static function loadById($node_id, $entity_type) {
    $node = FALSE;
    if ($drupal_node = lingotek_node_load_default($node_id)) {
      $node = self::load($drupal_node, $entity_type);
    }
    return $node;
  }
  
  
  /**
   * Loads a LingotekNode by Lingotek Document ID.
   *
   * @param string $lingotek_document_id
   *   The Document ID whose corresponding node should be loaded.
   *
   * @return mixed
   *   A LingotekNode object on success, FALSE on failure.
   */
  public static function loadByLingotekDocumentId($lingotek_document_id) {
    $node = FALSE;
    
    $query = db_select('lingotek_entity_metadata', 'l')->fields('l');
    $query->condition('entity_key', $key);
    $query->condition('value', $lingotek_document_id);
    $result = $query->execute();

    if ($record = $result->fetchAssoc()) {
      $id = $record['entity_id'];
      $entity_type = $record['entity_type'];
    }
    
    if ($id) {
      $node = self::loadById($id, $entity_type);
    }
    
    return $node;
  }


  /**
   * Gets the Lingotek document ID for this entity.
   *
   * @return mixed
   *   The integer document ID if the entity is associated with a 
   *   Lingotek document. FALSE otherwise.
   */
  public function lingotekDocumentId() {
    return $this->node->lingotek['document_id'];
  }
  
  /**
   * Gets the contents of this item formatted as XML that can be sent to Lingotek.
   *
   * @return string
   *   The XML document representing the entity's translatable content.
   */
  public function documentLingotekXML() {
    return lingotek_xml_node_body($this->entity_type, $this->node);
  }  
  
  /**
   * Magic get for access to node and node properties.
   */  
  public function __get($property_name) {
    $property = NULL;
    
    if ($property === 'node') {
      $property = $this->node;
    }
    elseif (isset($this->node->$property_name)) {
      $property = $this->node->$property_name;
    } else { // attempt to lookup the value in the lingonode table
      $val = lingotek_lingonode($this->node->nid,$property_name); 
      $property = ($val !== FALSE) ? $val : $property;
    } 
    
    return $property;
  }
  

  /**
   * Gets the local Lingotek metadata for this comment.
   *
   * @return array
   *   An array of key/value data for the current comment.
   */
  protected function metadata() {
    $metadata = array();

    $results = db_select('lingotek_entity_metadata', 'meta')
      ->fields('meta')
      ->condition('entity_id', $this->comment->cid)
      ->condition('entity_type', 'comment')
      ->execute();

    foreach ($results as $result) {
      $metadata[$result->entity_key] = $result->value;
    }

    return $metadata;
  }

  /**
   * Gets a Lingotek metadata value for this item.
   *
   * @param string $key
   *   The key whose value should be returned.
   *
   * @return string
   *   The value for the specified key, if it exists.
   */
  public function getMetadataValue($key) {
    return db_select('lingotek_entity_metadata', 'meta')
      ->fields('meta', array('value'))
      ->condition('entity_key', $key)
      ->condition('entity_id', $this->comment->cid)
      ->condition('entity_type', self::DRUPAL_ENTITY_TYPE)
      ->execute()
      ->fetchField();
  }

  /**
   * Sets a Lingotek metadata value for this item.
   *
   * @param string $key
   *   The key for a name/value pair.
   * @param string $value
   *   The value for a name/value pair.
   */
  public function setMetadataValue($key, $value) {
    $metadata = $this->metadata();
    if (!isset($metadata[$key])) {
      db_insert('lingotek_entity_metadata')
        ->fields(array(
          'entity_id' => $this->comment->cid,
          'entity_type' => self::DRUPAL_ENTITY_TYPE,
          'entity_key' => $key,
          'value' => $value,
        ))
        ->execute();

    }
    else {
      db_update('lingotek_entity_metadata')
        ->fields(array(
          'value' => $value
        ))
        ->condition('entity_id', $this->comment->cid)
        ->condition('entity_type', self::DRUPAL_ENTITY_TYPE)
        ->condition('entity_key', $key)
        ->execute();
    }
  }
  
  /**
   * Deletes a Lingotek metadata value for this item
   * 
   * @param string $key
   *  The key for a name/value pair
   */
  public function deleteMetadataValue($key) {
    $metadata = $this->metadata();
    if (isset($metadata[$key])) {
      db_delete('lingotek_entity_metadata')
        ->condition('entity_id', $this->comment->cid)
        ->condition('entity_type', self::DRUPAL_ENTITY_TYPE)
        ->condition('entity_key', $key, 'LIKE')
        ->execute();
    }
  }
  
  /**
   * Updates the local content with data from a Lingotek Document.
   *
   * @return bool
   *   TRUE if the content updates succeeded, FALSE otherwise.
   */
  public function updateLocalContent() {
    
  }
  
  /**
   * Updates the local content of $target_code with data from a Lingotek Document
   *
   * @param string $lingotek_locale
   *   The code for the language that needs to be updated.
   * @return bool
   *   TRUE if the content updates succeeded, FALSE otherwise.
   */
  public function updateLocalContentByTarget($lingotek_locale) {
    // Necessary to fully implement the interface, but we don't do anything
    // on LingotekNode objects, explicitly.
    lingotek_entity_download($this->node, $this->entity_type, $lingotek_locale);
  }
  
  public function getWorkflowId() {
    return $this->node->lingotek['workflow_id'];
  }
  
  public function getProjectId() {
    return $this->node->lingotek['project_id'];
  }
  
  public function getVaultId() {
    return $this->node->lingotek['vault_id'];
  }
  
  public function getTitle() {
    if ($this->entity_type == 'node') {
      return $this->node->title;
    } else if ($this->entity_type == 'comment') {
      return $this->node->subject;
    }
  }
  
  public function getDescription() {
    return $this->getTitle();
  }
  
    /**
   * Return the Drupal Entity type
   *
   * @return string
   *   The entity type associated with this object
   */
  public function getEntityType() {
    return $this->entity_type;
  }

  /**
   * Return the node ID
   *
   * @return int
   *   The ID associated with this object
   */
  public function getId() {
    return $this->node->nid;
  }
  
  public function getSourceLocale() {
    return Lingotek::convertDrupal2Lingotek($this->node->language);
  }
}
