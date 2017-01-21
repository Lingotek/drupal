<?php
namespace Drupal\lingotek\Form;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\Form\FormatConverter;
use Drupal\node\Entity\Node;

/**
 * @file
 * Contains \Drupal\Lingotek\Form\LingotekManagementForm.
 */

class LingotekImportForm extends LingotekConfigFormBase {

  private $docs = array();
  private $table_docs = array();
  private $supported_extentions = array('json', 'xml');

  public function getFormId() {
    return 'lingotek.import_form';
  }

  private function get_projects(){
    $communities = $this->lingotek->getCommunities();

    $new_projects = array();
    foreach ($communities as $community_id => $community_name){
      /**
       * @todo lingotek->getProjects() does not accept a parameter like this and
       * so this is not doing what was expected. It just returns the projects form
       * the defualt community.
       *
       */
      $projects = $this->lingotek->getProjects($community_id);
      foreach ($projects as $project_id => $project_name){
        $new_projects[$project_id] = $project_name;
      }

    }
    return $new_projects;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    define('ITEMS_PER_PAGE', 10);

    // TODO: use get_projects function to list all project names
    $projects = $this->get_projects();

    $count = 0;

    $total = $this->documentsCount();
    $page = pager_default_initialize($total, ITEMS_PER_PAGE);
    $current_page = pager_find_page();
    $args = array('limit' => ITEMS_PER_PAGE, 'offset' => ($current_page * ITEMS_PER_PAGE));
    $this->downloadDocuments($args);

    foreach($this->docs as $doc){
      $unix_upload_time = $doc->properties->upload_date / 1000;
			$upload_date_str = gmdate("m/j/Y", $unix_upload_time);

      $project_name = '-';
			if (array_key_exists($doc->properties->project_id, $projects)){
			     $project_name = $projects[$doc->properties->project_id];
			}
      else {
           $project_name = $doc->properties->project_id;
      }
        $this->table_docs[] = array('id'=> $count,
         'title' => $doc->properties->title,
         'extension' => $doc->properties->extension,
         'locale' => $doc->entities[0]->properties->language." - ".$doc->entities[0]->properties->code,
         'upload_date' => $upload_date_str,
         'doc_id' => $doc->properties->id,
         'project_name' => $project_name);
       $count++;
    }

    $header = array(
      'title'         => t('Title'),
      'extension'     => t('Extension'),
      'locale'        => t('Locale'),
      'upload_date'   => t('Upload Date'),
      'project_name'  => t('Project Name'),
      'doc_id'        => t('ID'),
    );

    $options = array();
    foreach ($this->table_docs as $document) {
      $options[$document['id']] = array(
        'title' => $document['title'],
        'extension' => $document['extension'],
        'locale' => $document['locale'],
        'upload_date' => $document['upload_date'],
        'project_name' => $document['project_name'],
        'doc_id' => $document['doc_id'],
      );
    }
    $form['table'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => t('No documents found'),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Import'),
    );
    $form['pager'] = array(
      '#type' => 'pager',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $results = $form_state->getValue('table');
    $doc_ids_to_import = array();

    foreach($results as $id => $checked){
      if(is_string($checked) && $checked == $id){
        array_push($doc_ids_to_import, $id);
      }
    }

    $import_success_count = 0;
    $import_failure_count = 0;
    $import_failure_doc_ids = array();

    foreach($doc_ids_to_import as $id){
      $response = $this->import($id);
      if ($response == 0){
        $import_failure_count++;
        array_push($import_failure_doc_ids, $this->searchForDoc($id)->properties->id);
      }
      else {
        $import_success_count++;
      }
    }
    $importedCount = $import_success_count + $import_failure_count;
    if ($import_success_count > 0){
      $plural_or_singular = \Drupal::translation()->formatPlural($importedCount,
        'Successfully imported @import_success_count of @importedCount Document',
        'Successfully imported @import_success_count of @importedCount Documents',
        array('@import_success_count' => $import_success_count, '@importedCount' => $importedCount), array());
    drupal_set_message($plural_or_singular);
    if ($import_success_count != $importedCount){
      $document_plurality = \Drupal::translation()->formatPlural($import_failure_count,
        'The following document did not import: @failed_imports',
        'The following documents did not import: @failed_imports',
        array('@failed_imports' => $this->toStringUnsuccessfulImports($import_failure_doc_ids)), array());
      drupal_set_message($document_plurality, 'error');
    }
   }
   else {
     if ($import_success_count == 0 && $import_failure_count == 0){
        drupal_set_message($this->t('No files were selected to import. Please check the desired documents to import.'), 'error');
     }
     else {
        $file_plurality = \Drupal::translation()->formatPlural($importedCount,
          'There was an error importing your file. We currently only support Wordpress, Drupal, HTML, and Text files.',
          'There was an error importing your files. We currently only support Wordpress, Drupal, HTML, and Text files.',
          array(), array());
        drupal_set_message($file_plurality, 'error');
     }
   }

  }

  /**
  * This function is to list the captured doc ids in a string separated by commas
  * so it can be displayed for the end-user in a message.
  *
  * @author TJ Murphy
  * @param array $unsuccessful_imports an array of doc ids that did not import
  * @return string $unsuccessful_imports_string this creates an HTML string that
  *   creates an unordered list of doc ids that failed to import
  **/
  protected function toStringUnsuccessfulImports($unsuccessful_imports){
    $unsuccessful_imports_string = '';
    $count = 0;
    foreach ($unsuccessful_imports as $doc_id){
      if ($count != 0) {
        $unsuccessful_imports_string = $unsuccessful_imports_string.', '.(string)$doc_id;
      }
      else {
        $unsuccessful_imports_string = $unsuccessful_imports_string.(string)$doc_id;
      }
      $count++;
    }
    $unsuccessful_imports_string = $unsuccessful_imports_string.'.';
    return $unsuccessful_imports_string;
  }

  protected function downloadDocuments($args=array()){

    $translation_service = \Drupal::service('lingotek.content_translation');

    $response = $this->lingotek->downloadDocuments($args);
    $data = json_decode($response);
    $count = 0;
    foreach($data->entities as $entity ){
      $this->docs[] = $entity;
      $count++;
    }

    return $count;
  }

  protected function documentsCount($args=array()){

    $translation_service = \Drupal::service('lingotek.content_translation');

    $response = $this->lingotek->downloadDocuments($args);
    $data = json_decode($response);
    return $data->properties->total;
  }

  protected function downloadDocumentContent($doc_id){

    $translation_service = \Drupal::service('lingotek.content_translation');
    $response = $translation_service->downloadDocumentContent($doc_id);
    return $response;
  }

  public function import_standard_object(StandardImportObject $object){

    $content_cloud_import_format = $this->lingotek->get('preference.content_cloud_import_format');
    $content_cloud_import_status = $this->lingotek->get('preference.content_cloud_import_status');

    $node = Node::create(array(
        'nid' => NULL,
        'type' => $content_cloud_import_format,
        'title' => $object->getTitle(),
        'langcode' => 'en',
        'uid' => 1,
        'status' => $content_cloud_import_status,
        'body' => array(
          'value' => $object->getContent(),
          'format' => 'full_html',
        ),
        'field_fields' => array(),
    ));

    $node->save();

    if ($node->node_id) {
      $success = 0;
    }
    else {
      $success = 1;
    }

    return $success;


	}

  /**
  * This function is to import a document from the TMS.
  *
  * @author TJ Murphy
  * @param string id drupal table doc id, not lingotek document id
  * @return mixed will return a 0 if there is an error in the conversion, or it
  *   will return the $response if there is no error and it converts as expected
  **/
	public function import($id){ //


    $doc = $this->searchForDoc($id);
    $format = $doc->properties->extension;
    $content = $this->downloadDocumentContent($doc->properties->id);

    if ($content == null){
				$content == 'There is no content to display';
		}

	$formatConverter = new FormatConverter($doc, $content, $format);

	$importObject = $formatConverter->convert_to_standard();

    if ($importObject->hasError()){
      return 0;
    }

	$response = $this->import_standard_object($importObject);
	return $response;
	}

  function searchForDoc($id) {
    $doc_id = null;
     foreach ($this->table_docs as $table_doc) {
        if ($table_doc['id'] == $id) {
          $doc_id = $table_doc['doc_id'];
          break;
        }
     }
     foreach($this->docs as $docObject){
       if ($docObject->properties->id == $doc_id){
         return $docObject;
       }
     }
     return null;
  }

}
